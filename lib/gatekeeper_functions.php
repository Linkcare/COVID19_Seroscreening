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
 * @param Prescription $prescription
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

    $timezone = "0";
    $session = null;

    try {
        LinkcareSoapAPI::setEndpoint($GLOBALS["WS_LINK"]);
        LinkcareSoapAPI::session_init($GLOBALS['SERVICE_USER'], $GLOBALS['SERVICE_PASSWORD'], 0, true);
        $team = $GLOBALS['SERVICE_TEAM'];
        $role = 47;
        $session = LinkcareSoapAPI::getInstance()->getSession();
        // Ensure to set the correct active ROLE and TEAM
        if ($team && $team != $session->getTeamCode() && $team != $session->getTeamId()) {
            LinkcareSoapAPI::getInstance()->session_set_team($team);
        }
        if ($role && $session->getRoleId() != $role) {
            LinkcareSoapAPI::getInstance()->session_role($role);
        }
    } catch (APIException $e) {
        $results->error = 'Service user cannot connect to API. Contact a system administrator to solve the problem';
        return $results;
    } catch (Exception $e) {
        $results->error = 'Service user cannot connect to API. Contact a system administrator to solve the problem';
        return $results;
    }

    if (!$prescription) {
        $results->error = 'Invalid QR';
        return $results;
    }

    $patient = null;
    $found = false;

    $api = LinkcareSoapAPI::getInstance();

    if ($prescription->getAdmissionId()) {
        // We know the ADMISSION. Use it to obtain the PATIENT and the PROGRAM
        $admission = $api->admission_get($prescription->getAdmissionId());
        $patient = $api->case_get($admission->getCaseId());
        $program = $admission->getSubscription()->getProgram();
        $found = true;
    } else {
        $program = $api->program_get($prescription->getProgram());
    }

    if (!$program) {
        $results->error = 'PROGRAM missing';
        return $results;
    }

    if (!$found && $prescription->getParticipantId()) {
        /*
         * We know the participant ID. Use it to obtain the PATIENT
         */
        $searchCondition = new StdClass();
        $searchCondition->identifier = new StdClass();
        $searchCondition->program = $program->getId();
        $searchCondition->identifier->code = $GLOBALS['PARTICIPANT_IDENTIFIER'];
        $participantId = $prescription->getParticipantId();
        if (strpos($prescription->getParticipantId(), '@') === false && $prescription->getTeam()) {
            $participantId = $participantId . '@' . $prescription->getTeam();
        }

        $searchCondition->identifier->value = $participantId;
        $patientsFound = $api->case_search(json_encode($searchCondition));

        if (count($patientsFound) == 1) {
            /* @var APICase $p */
            $patient = reset($patientsFound);
            $found = true;
        } else {
            $results->error = 'Not enough information to find participant with ref: ' . $prescription->getParticipantId();
            return $results;
        }
    }

    if (!$found && $prescription->getId()) {
        /* We know the PRESCRIPTION ID. Use it to obtain the ADMISSION, and then the PATIENT and PROGRAM */
        $searchCondition = new StdClass();
        $searchCondition->program = $program->getId();
        $searchCondition->data_code = new StdClass();
        $searchCondition->data_code->name = 'PRESCRIPTION_ID';
        $searchCondition->data_code->value = $prescription->getId();
        $patientsFound = $api->case_search(json_encode($searchCondition));

        if (count($patientsFound) > 1) {
            $results->error = 'More than one participant found with the same prescription ' . $prescription->getId();
            return $results;
        } elseif (count($patientsFound) == 1) {
            /* @var APICase $p */
            $patient = reset($patientsFound);
            $found = true;
        }
    }

    if (!$patient) {
        // Couldn't find the patient
        return $results;
    }

    $results->patientId = $patient->getId();
    /*
     * Find the last ADMISSION of the PATIENT in the desired PROGRAM with a finished TEST and obtain the OUTPUT
     * Only use ENROLLED, ACTIVE or DISCHARGED ADMISSIONs
     */
    $filter = new TaskFilter();
    $filter->setObjectType('TASKS');
    $filter->setTaskCodes('KIT_RESULTS,KIT_RESULTS_INTRODUCTION');
    $filter->setProgramIds($program->getId());
    $tasks = $api->case_get_task_list($patient->getId(), null, null, $filter);

    $sortedTasks = [];
    foreach ($tasks as $t) {
        $date = $t->getDate() ? $t->getDate() : '9999-99-99';
        $time = $t->getHour() ? $t->getHour() : '99:99:99';
        $key = sprintf("%s %s/%010d", $date, $time, $t->getId());
        $sortedTasks[$key] = $t;
    }
    // Sort by date descending (null dates first)
    krsort($sortedTasks);

    $existsTestInProgress = false;
    foreach ($sortedTasks as $t) {
        $admission = $api->admission_get($t->getAdmissionId());
        if (in_array($t->getStatus(), ['OPEN', 'ASSIGNED/NOT DONE'])) {
            $existsTestInProgress = true;
            $results->result = DIAGNOSTIC_IN_PROGRESS;
            continue;
        }
        // Verify that the result is still valid
        $output = $admission->getPerformance()->getOutput();
        $results->output = $output->getValue();
        $results->date = $t->getDate() . ' ' . $t->getHour();
        $results->admissionId = $admission->getId();

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

        $expiration = $output->getValidity();
        if ($expiration) {
            // Verify that the test is still valid
            $results->expiration = $expiration;
            $timezone = $patient->getTimezone();

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
