<?php
const DIAGNOSTIC_UNKNOWN = 0;
const DIAGNOSTIC_NEGATIVE = 1;
const DIAGNOSTIC_POSITIVE = 2;
const DIAGNOSTIC_IN_PROGRESS = 3;
const DIAGNOSTIC_EXPIRED = 4;

/**
 * Given a $participantQR, the functions locates the participant and returns the result of the last test.
 * The function returns an object with the following properties:
 * <ul>
 * <li>result: numeric value with the result of the test. Possible values are:</li>
 * <ul>
 * <li>0 = No valid test found</li>
 * <li>1 = Negative diagnostic</li>
 * <li>2 = Positive diagnostic</li>
 * </ul>
 * <li>date: date when the test was done (format yyyy-mm-dd hh:mm:ss)</li>
 * <li>error: error message (if any)
 * </ul>
 *
 * @param string $participantQR
 * @return StdClass
 */
function checkTestResults($prescription) {
    $results = new StdClass();
    $results->result = DIAGNOSTIC_UNKNOWN;
    $results->date = '';
    $results->error = '';
    $results->patientId = null;
    $results->admissionId = null;
    $results->output = null;
    $results->expiration = null;

    if (!$prescription || !$prescription->isValid()) {
        $results->error = 'Invalid QR';
        return $results;
    }

    try {
        $dbConnResult = Database::init($GLOBALS["APIDBConnection_URI"]);
        if ($dbConnResult !== true) {
            $results->error = "Cannot initialize DB. Contact a system administrator to solve the problem";
            service_log("ERROR: Cannot connect to DB");
            return $results;
        }
    } catch (Exception $e) {
        $results->error = "Cannot initialize DB. Contact a system administrator to solve the problem";
        service_log("ERROR: Cannot initialize DB: " . $e->getMessage());
        return $results;
    }

    $patientId = null;
    $programId = null;

    if ($prescription->getAdmissionId()) {
        // We know the ADMISSION. Use it to obtain the PATIENT and the PROGRAM
        $sql = 'SELECT IIDPATPATIENT,ID_PROGRAMA FROM TBPRGPATIENTPROGRAMME t WHERE IIDPATIENTPROGRAMME = :id';
        $rst = Database::getInstance()->ExecuteBindQuery($sql, $prescription->getAdmissionId());
        if ($rst->Next()) {
            $patientId = $rst->GetField('IIDPATPATIENT');
            $programId = $rst->GetField('ID_PROGRAMA');
        }
    } elseif ($prescription->getParticipantId()) {
        /*
         * We know the participant ID. Use it to obtain the PATIENT
         * It is necessary to know also the PROGRAM and TEAM because PARTICIPANT_REF is a SUBSCRIPTION IDENTIFIER
         */
        $teamId = null;
        if (!is_numeric($prescription->getProgram())) {
            // We have a PROGRAM CODE. Find the PROGRAM ID
            $sql = 'SELECT ID_PROGRAMA FROM PROGRAMAS p WHERE PROG_CODE = :id';
            $rst = Database::getInstance()->ExecuteBindQuery($sql, $prescription->getProgram());
            if ($rst->Next()) {
                $programId = $rst->GetField('ID_PROGRAMA');
            } else {
                $results->error = 'PROGRAM not found';
                return $results;
            }
        } else {
            $programId = $prescription->getProgram();
        }
        if (!is_numeric($prescription->getTeam())) {
            // We have a PROGRAM CODE. Find the TEAM ID
            $sql = 'SELECT IIDGNRCENTRE FROM TBGNRCENTRE WHERE TEAM_CODE = :id';
            $rst = Database::getInstance()->ExecuteBindQuery($sql, $prescription->getTeam());
            if ($rst->Next()) {
                $teamId = $rst->GetField('IIDGNRCENTRE');
            } else {
                $results->error = 'TEAM not found';
                return $results;
            }
        } else {
            $teamId = $prescription->getTeam();
        }

        $arrVariables = [':programId' => $programId, ':teamId' => $teamId, ':participantId' => $prescription->getParticipantId()];
        $sql = "SELECT p.IIDPATPATIENT FROM IDENTIFIERS i, TBPATPATIENT p
            WHERE i.CODE ='PARTICIPANT_REF' AND VALUE = :participantId
                AND p.IIDGNRPERSON = i.PERSON_ID AND PROGRAM_ID = :programId AND TEAM_ID = :teamId";
        $rst = Database::getInstance()->ExecuteBindQuery($sql, $arrVariables);
        if ($rst->Next()) {
            $patientId = $rst->GetField('IIDPATPATIENT');
        }
    } elseif ($prescription->getId()) {
        /* We know the PRESCRIPTION ID. Use it to obtain the ADMISSION, and then the PATIENT and PROGRAM */
        $arrVariables = ['prescriptionId' => $prescription->getId()];
        $sql = "SELECT DISTINCT a.IIDPATPATIENT, a.ID_PROGRAMA FROM TBPRGPATIENTPROGRAMME a, PRESTACIONES_INGRESO t, CUESTIONARIOS c ,RESPUESTAS r
                WHERE
                	a.DELETED IS NULL
                	AND t.ID_INGRESO = a.IIDPATIENTPROGRAMME AND t.DELETED IS NULL
                	AND c.TASK_ID  = t.ID_PRESTACION_INGRESO AND c.DELETED IS NULL
                	AND r.ID_CUESTIONARIO = c.ID_CUESTIONARIO AND r.DATA_CODE = 'PRESCRIPTION_ID' AND r.RESPUESTA = :prescriptionId";

        $rst = Database::getInstance()->ExecuteBindQuery($sql, $arrVariables);
        $count = 0;
        while ($rst->Next()) {
            $patientId = $rst->GetField('IIDPATPATIENT');
            $programId = $rst->GetField('ID_PROGRAMA');
            $count++;
        }
        if ($count > 1) {
            $results->error = 'More than one participant found with the same prescription ' . $prescription->getId();
            return $results;
        }
    }

    if (!$programId || !$patientId) {
        // It is necessary to know PROGRAM and PATIENT
        $results->error = 'PROGRAM or PATIENT not found';
        return $results;
    }

    $results->patientId = $patientId;
    /*
     * Find the last ADMISSION of the PATIENT in the desired PROGRAM with a finished TEST and obtain the OUTPUT
     * Only use ENROLLED, ACTIVE or DISCHARGED ADMISSIONs
     */
    $arrVariables = [':patientId' => $patientId, ':programId' => $programId];
    $sql = "SELECT adm.IIDPATIENTPROGRAMME, adm.OUTPUT, adm.OUTPUT_VALIDITY, pi.FECHA_HORA_FIN AS TEST_DATE, pi.ID_ESTADO
        	FROM TBPRGPATIENTPROGRAMME adm , PRESTACIONES_INGRESO pi
        	WHERE IIDPATPATIENT = :patientId AND ID_PROGRAMA = :programId
        	    AND adm.DELETED IS NULL
        	    AND adm.IIDPRGPATIENTPROGRAMMESTATE IN (1,4,5)
        	    AND pi.ID_INGRESO = adm.IIDPATIENTPROGRAMME
        	    AND pi.DELETED IS NULL
        	    AND pi.TASK_CODE = 'KIT_RESULTS'            
            ORDER BY pi.FECHA_HORA_FIN DESC NULLS FIRST";

    $rst = Database::getInstance()->ExecuteBindQuery($sql, $arrVariables);
    $existsTestInProgress = false;
    while ($rst->Next()) {
        if ($rst->GetField('ID_ESTADO') != APITask::STATUS_DONE) {
            $existsTestInProgress = true;
        }
        // Verify that the result is still valid
        $results->output = $rst->GetField('OUTPUT');
        $results->date = $rst->getField('TEST_DATE');
        $results->admissionId = $rst->GetField('IIDPATIENTPROGRAMME');

        switch ($results->output) {
            case 2 :
                $results->result = DIAGNOSTIC_NEGATIVE;
                break;
            case 5 :
                $results->result = DIAGNOSTIC_POSITIVE;
                break;
            default :
                if ($existsTestInProgress) {
                    // ACTIVE or ENROLLED
                    $results->result = DIAGNOSTIC_IN_PROGRESS;
                }
        }

        $expiration = $rst->GetField('OUTPUT_VALIDITY');
        if ($expiration) {
            // Verify that the test is still valid
            $results->expiration = $expiration;

            $sql = 'SELECT u.TIMEZONE FROM TBPATPATIENT p, TBGNRUSER u WHERE p.IIDPATPATIENT = :id AND u.IIDGNRPERSON = p.IIDGNRPERSON';
            $rstTimezone = Database::getInstance()->ExecuteBindQuery($sql, $patientId);
            if ($rstTimezone->Next()) {
                $timezone = $rstTimezone->GetField('TIMEZONE');
            } else {
                $timezone = 0;
            }

            $currentDate = currentDate($timezone);
            if ($results->expiration < $currentDate) {
                // The test has expired
                $results->result = $existsTestInProgress ? DIAGNOSTIC_IN_PROGRESS : DIAGNOSTIC_EXPIRED;
            }
        }
        // We have found a finished test. There is no need to check older tests
        break;
    }

    return $results;
}

/**
 * Generates an entry in the table KIT_TRACKING to know who is creating Admissions in Linkcare with a KIT_ID
 *
 * @param string $prescriptionString
 */
function storeGatekeeperTracking($testResult, $qr) {
    if (!$testResult) {
        return;
    }
    $tz_object = new DateTimeZone('UTC');
    $datetime = new DateTime();
    $datetime->setTimezone($tz_object);
    $today = $datetime->format('Y\-m\-d\ H:i:s');

    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ipAddress = $_SERVER['REMOTE_ADDR'];
    }

    $arrVariables[':id'] = getNextTrackingId();
    $arrVariables[':instanceId'] = $GLOBALS['GATEKEEPER_INSTANCE'];
    $arrVariables[':created'] = $today;
    $arrVariables[':patientId'] = $testResult->patientId;
    $arrVariables[':admissionId'] = $testResult->admissionId;
    $arrVariables[':outcome'] = $testResult->output;
    $arrVariables[':testResult'] = $testResult->result;
    $arrVariables[':ipAddress'] = $ipAddress;
    $arrVariables[':qr'] = $qr;
    $sql = "INSERT INTO GATEKEEPER_TRACKING (ID_TRACKING, CREATED, ID_INSTANCE, ID_CASE, ID_ADMISSION, OUTCOME, TEST_RESULT, IP, QR) VALUES (:id, :created, :instanceId, :patientId, :admissionId, :outcome, :testResult, :ipAddress, :qr)";
    Database::getInstance()->ExecuteBindQuery($sql, $arrVariables);
}

function getNextTrackingId() {
    // if ($GLOBALS["BBDD"] == "ORACLE") {
    $sql = "SELECT SEQ_GATEKEEPER.NEXTVAL AS NEXTV FROM DUAL";
    $rst = Database::getInstance()->ExecuteQuery($sql);
    $rst->Next();
    return $rst->GetField("NEXTV");
}
