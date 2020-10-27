<?php

// Link the config params
require_once ("default_conf.php");

setSystemTimeZone();
const PATIENT_IDENTIFIER = 'PARTICIPANT_REF';

/**
 *
 * @param string $token
 * @param KitInfo $kitInfo
 * @return string
 */
function service_dispatch_kit($token = null, $kitInfo) {
    $timezone = "0";

    if (!$GLOBALS["OMIT_DB_CONNECTION"]) {
        try {
            $dbConnResult = Database::init($GLOBALS["DBConnection_URI"]);
            if ($dbConnResult !== true) {
                $lc2Action = new LC2Action(LC2Action::ACTION_ERROR_MSG);
                $lc2Action->setErrorMessage("Error connecting to DB");
                service_log("ERROR: Cannot connect to DB");
            }
        } catch (Exception $e) {
            $lc2Action = new LC2Action(LC2Action::ACTION_ERROR_MSG);
            $lc2Action->setErrorMessage("Cannot initialize DB. Contact a system administrator to solve the problem");
            service_log("ERROR: Cannot initialize DB: " . $e->getMessage());
            return $lc2Action->toString();
        }
    }

    if (!preg_match('/^(\w{5,7})$/', $kitInfo->getId())) {
        $error = new ErrorInfo(ErrorInfo::INVALID_KIT);
        $lc2Action = new LC2Action(LC2Action::ACTION_ERROR_MSG);
        $lc2Action->setErrorMessage($error->getErrorMessage());
    } else {
        try {
            LinkcareSoapAPI::init($GLOBALS["WS_LINK"], $timezone, $token);
            $session = LinkcareSoapAPI::getInstance()->getSession();
            Localization::init($session->getLanguage());
            // Find the SUBSCRIPTION of the PROGRAM "Seroscreening" of the active user
            $lc2Action = processKit($kitInfo);
        } catch (APIException $e) {
            $lc2Action = new LC2Action(LC2Action::ACTION_ERROR_MSG);
            $lc2Action->setErrorMessage($e->getMessage());
        } catch (KitException $k) {
            $lc2Action = new LC2Action(LC2Action::ACTION_ERROR_MSG);
            $lc2Action->setErrorMessage($k->getMessage());
        } catch (Exception $e) {
            service_log("ERROR: " . $e->getMessage());
        }
    }

    return $lc2Action->toString();
}

/**
 *
 * @param KitInfo $kitInfo
 * @throws Exception
 * @return LC2Action
 */
function processKit($kitInfo) {
    $lc2Action = null;
    $api = LinkcareSoapAPI::getInstance();

    $admissionForKit = null;
    $activeAdmission = null;
    $finishedAdmissions = 0;
    $caseId = null;

    $prescription = trim($kitInfo->getPrescriptionId()) != '' ? new Prescription($kitInfo->getPrescriptionId(), true) : null;

    if ($prescription) {
        // Find the subscription for the PROGRAM/TEAM provided in the prescription information
        $subscriptionId = findSubscription($prescription);
        /*
         * Find if there exists a patient with the PARTICIPANT_ID
         */
        $casesByPrescription = [];
        $casesByParticipant = [];
        if ($prescription->getParticipantId()) {
            $searchCondition = new StdClass();
            $searchCondition->identifier = new StdClass();
            $searchCondition->identifier->code = PATIENT_IDENTIFIER;
            $searchCondition->identifier->value = $prescription->getParticipantId();
            if ($api->errorCode()) {
                throw new APIException($api->errorCode(), $api->errorMessage());
            }
            $casesByParticipant = $api->case_search(json_encode($searchCondition));
        }
        if (!empty($casesByParticipant)) {
            $caseId = $casesByParticipant[0]->getId();
        }
        if ($prescription->getId()) {
            $searchCondition = new StdClass();
            $searchCondition->subscription = $subscriptionId;
            $searchCondition->data_code = new StdClass();
            $searchCondition->data_code->name = 'PRESCRIPTION_ID';
            $searchCondition->data_code->value = $prescription->getId();
            $casesByPrescription = $api->case_search(json_encode($searchCondition));
            if ($api->errorCode()) {
                throw new APIException($api->errorCode(), $api->errorMessage());
            }
            if (!empty($casesByPrescription)) {
                // Ensure that the PRESCRIPTION ID does not correspond to another CASE
                $found = false;
                foreach ($casesByPrescription as $c) {
                    if ($c->getId() == $caseId) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    /*
                     * Error: We have found a CASE by the PRESCRIPTION ID, but it is not the same than the one found by PARTICIPANT_ID. This means
                     * that the PRESCRIPTION ID was used in another CASE
                     */
                    throw new KitException(ErrorInfo::PRESCRIPTION_ALREADY_USED);
                }
            }
        }
    }

    // Find out if there exists a patient assigned to the Kit ID
    $casesByDevice = $api->case_search("SEROSCREENING:" . $kitInfo->getId() . "");
    if ($api->errorCode()) {
        throw new APIException($api->errorCode(), $api->errorMessage());
    }

    if ($prescription && empty($casesByParticipant) && !empty($casesByDevice)) {
        /*
         * If we have the information of the PARTICIPANT but there exists no CASE for him (no ADMISSION created yet), then the CASE found by the KIT
         * ID must be a different participant, what means that the KIT ID has already been used
         */
        throw new KitException(ErrorInfo::KIT_ALREADY_USED);
    }

    if (!empty($casesByDevice)) {
        if ($caseId && $caseId != $casesByDevice[0]->getId()) {
            /*
             * ERROR: the case associated to the PARTICIPANT_REF provided is different than the one associated to the KIT_ID. This means that we are
             * scanning a KitID that was previously used for another CASE
             */
            throw new KitException(ErrorInfo::KIT_ALREADY_USED);
        } else {
            $caseId = $casesByDevice[0]->getId();
        }

        $searchCondition = new StdClass();
        $searchCondition->data_code = new StdClass();
        $searchCondition->data_code->name = 'KIT_ID';
        $searchCondition->data_code->value = $kitInfo->getId();
        $kitAdmissions = $api->case_admission_list($casesByDevice[0]->getId(), true, $subscriptionId, json_encode($searchCondition));
        if ($api->errorCode()) {
            throw new APIException($api->errorCode(), $api->errorMessage());
        }

        $admissionForKit = count($kitAdmissions) > 0 ? $kitAdmissions[0] : null;
        if ($admissionForKit &&
                in_array($admissionForKit->getStatus(), [APIAdmission::STATUS_ACTIVE, APIAdmission::STATUS_ENROLLED, APIAdmission::STATUS_INCOMPLETE])) {
            $activeAdmission = $admissionForKit;
        }
    }

    if ($prescription && $prescription->getId()) {
        /*
         * At this point we are sure that the KIT ID is not used, or it is used and corresponds to the participant, but it is alse necessary to check
         * another thing:
         * There may exist more than one prescription for the same participant, so we must ensure that the KIT ID is not assigned to a different
         * prescription
         */
        $prescriptionAdmissions = null;
        if ($caseId) {
            // Find the ADMISSIONs of the CASE (with the correct prescription ID)
            $searchCondition = new StdClass();
            $searchCondition->data_code = new StdClass();
            $searchCondition->data_code->name = 'PRESCRIPTION_ID';
            $searchCondition->data_code->value = $prescription->getId();
            $prescriptionAdmissions = $api->case_admission_list($caseId, true, $subscriptionId, json_encode($searchCondition));
            if ($api->errorCode()) {
                throw new APIException($api->errorCode(), $api->errorMessage());
            }
            foreach ($prescriptionAdmissions as $a) {
                if (in_array($a->getStatus(), [APIAdmission::STATUS_ACTIVE, APIAdmission::STATUS_ENROLLED, APIAdmission::STATUS_INCOMPLETE])) {
                    $activeAdmission = $a;
                    break;
                } else {
                    $finishedAdmissions++;
                }
            }

            if ($admissionForKit) {
                /*
                 * One of the ADMISSIONs of the prescription must be the one found by the KIT ID. Otherwise it means that the KIT was used in another
                 * prescription of the same patient
                 */
                $found = false;
                foreach ($prescriptionAdmissions as $a) {
                    if ($a->getId() == $admissionForKit->getId()) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    throw new KitException(ErrorInfo::KIT_ALREADY_USED);
                }
            }
        }
    }

    if ($admissionForKit &&
            !in_array($admissionForKit->getStatus(), [APIAdmission::STATUS_ACTIVE, APIAdmission::STATUS_ENROLLED, APIAdmission::STATUS_INCOMPLETE])) {
        // The ADMISSION exists and it is finished
        $lc2Action->setActionType(LC2Action::ACTION_REDIRECT_TO_CASE);
        $lc2Action->setCaseId($admissionForKit->getCaseId());
        $lc2Action->setAdmissionId($admissionForKit->getId());
        return $lc2Action;
    }

    if ($prescription) {
        // Everything looks right so far, but there is one last verification: There cannont be more ADMISSIONS for the prescription ID than permitted
        // rounds
        if ($finishedAdmissions >= $prescription->getRounds()) {
            throw new KitException(ErrorInfo::MAX_ROUNDS_EXCEEDED);
        }
    }

    if (!$activeAdmission && !$prescription) {
        // It is not possible to create a new ADMISSION without the prescription information
        throw new KitException(ErrorInfo::PRESCRIPTION_MISSING);
    } elseif (!$activeAdmission) {
        $tz_object = new DateTimeZone('UTC');
        $datetime = new DateTime();
        $datetime->setTimezone($tz_object);
        $today = $datetime->format('Y\-m\-d');

        if ($prescription && $prescription->getExpirationDate() && $prescription->getExpirationDate() < $today) {
            throw new KitException(ErrorInfo::PRESCRIPTION_EXPIRED);
        }

        // The KIT ID is new. Create a new ADMISSION
        $lc2Action = createNewAdmission($kitInfo, $prescription, $caseId, $subscriptionId);
    } else {
        // We have found an active ADMISSION for this Kit ID
        $lc2Action = updateAdmission($activeAdmission, $kitInfo);
    }

    return $lc2Action;
}

/**
 * Searches the subscription of the active user that corresponds to the PROGRAM "Seroscreening"
 */
/**
 *
 * @param Prescription $prescription
 * @throws APIException
 * @return string
 */
function findSubscription($prescription) {
    $api = LinkcareSoapAPI::getInstance();
    $subscriptionId = null;
    $teamId = null;
    $programId = null;
    $programCode = null;

    if ($prescription->getTeam()) {
        $team = $api->team_get($prescription->getTeam());
        if ($api->errorCode()) {
            throw new APIException($api->errorCode(), $api->errorMessage());
        }
        $teamId = $team->getId();
    } else {
        $teamId = $api->getSession()->getTeamId();
    }

    if ($api->getSession()->getTeamId() != $teamId) {
        $api->session_set_team($teamId);
    }
    if ($api->getSession()->getRoleId() != 24) {
        $api->session_role(24);
    }

    if ($prescription->getProgram()) {
        $program = $api->program_get($prescription->getProgram());
        if ($api->errorCode()) {
            throw new APIException($api->errorCode(), $api->errorMessage());
        }
        $programId = $program->getId();
        $programCode = $program->getCode();
    } else {
        $programCode = $GLOBALS["PROGRAM_CODE"];
    }

    $filter = ["member_role" => 24, "member_team" => $teamId, "program" => $$programId];
    $subscriptions = $api->subscription_list($filter);
    foreach ($subscriptions as $s) {
        $p = $s->getProgram();
        if ($p && $p->getCode() == $programCode) {
            $subscriptionId = $s->getId();
            break;
        }
    }

    if (!$subscriptionId) {
        $e = new KitException(ErrorInfo::SUBSCRIPTION_NOT_FOUND);
        throw $e;
    }

    return $subscriptionId;
}

/**
 *
 * @param KitInfo $kitInfo
 * @param Prescription $prescription
 * @param int $subscriptionId
 * @return LC2Action
 */
function createNewAdmission($kitInfo, $prescription, $caseId, $subscriptionId) {
    $lc2Action = new LC2Action();
    $api = LinkcareSoapAPI::getInstance();
    $isNewCase = false;

    if (!$caseId) {
        $isNewCase = true;
        // Create the case
        $contactInfo = new APIContact();

        $device = new APIContactChannel();
        $device->setValue("SEROSCREENING:" . $kitInfo->getId());
        $contactInfo->addDevice($device);

        if ($prescription->getParticipantId()) {
            $studyRef = new APIIdentifier(PATIENT_IDENTIFIER, $prescription->getParticipantId());
            $contactInfo->addIdentifier($studyRef);
        }

        // Create a new CASE with incomplete data (only the KIT_ID)
        $caseId = $api->case_insert($contactInfo, $subscriptionId, true);

        if ($api->errorCode()) {
            $lc2Action->setActionType(LC2Action::ACTION_ERROR_MSG);
            $lc2Action->setErrorMessage($api->errorMessage());
            return $lc2Action;
        }
    }

    $lc2Action->setCaseId($caseId);
    $failed = false;
    // Create an ADMISSION
    $admission = $api->admission_create($caseId, $subscriptionId, null, null, true);

    if (!$admission || $api->errorCode()) {
        // An unexpected error happened while creating the ADMISSION: Delete the CASE
        $failed = true;
        $lc2Action->setActionType(LC2Action::ACTION_ERROR_MSG);
        $lc2Action->setErrorMessage($api->errorMessage());
    }

    $lc2Action->setAdmissionId($admission->getId());
    if (!$admission->isNew()) {
        // There already exists an active Admission for the patient. Cannot create a new Admission
        $lc2Action->setActionType(LC2Action::ACTION_ERROR_MSG);
        $lc2Action->setErrorMessage(Localization::translate('Admission.AlreadyActive', ['kit_id' => $kitInfo->getId()]));
        return $lc2Action;
    }

    if (!$isNewCase) {
        // The CASE already existed, but we need to add the KIT_ID to his list of devices
        $contactInfo = new APIContact();

        $device = new APIContactChannel();
        $device->setValue("SEROSCREENING:" . $kitInfo->getId());
        $contactInfo->addDevice($device);
        $api->case_set_contact($caseId, $contactInfo);
    }

    try {
        createKitInfoTask($admission->getId(), $kitInfo);
        createPrescriptionInfoTask($admission->getId(), $prescription);
        list($taskId, $formId) = createRegisterKitTask($admission->getId());
        $lc2Action->setActionType(LC2Action::ACTION_REDIRECT_TO_FORM);
        $lc2Action->setTaskId($taskId);
        $lc2Action->setFormId($formId);
    } catch (APIException $e) {
        $failed = true;
        $lc2Action->setActionType(LC2Action::ACTION_ERROR_MSG);
        $lc2Action->setErrorMessage($api->errorMessage());
    }

    if ($failed) {
        if ($admission) {
            $api->admission_delete($admission->getId());
        }
        if ($isNewCase) {
            $api->case_delete($caseId, "DELETE");
        }
    } else {
        updateKitStatus($kitInfo->getId(), KitInfo::STATUS_ASSIGNED);
    }

    return $lc2Action;
}

/**
 * Updates the ADMISSION adding a new "SCAN_KIT" TASK to inform the system that a new KIT has been scanned.
 * Returns a LC2Action to redirect LC2 to the "KIT_RESULTS" TASK
 *
 * @param APIAdmission $admission
 * @param KitInfo $kitInfo
 * @return LC2Action
 */
function updateAdmission($admission, $kitInfo) {
    $api = LinkcareSoapAPI::getInstance();

    $lc2Action = new LC2Action();
    $lc2Action->setAdmissionId($admission->getId());
    $lc2Action->setCaseId($admission->getCaseId());

    $tasks = $api->case_get_task_list($admission->getCaseId(), null, null, '{"admission" : "' . $admission->getId() . '"}');
    $kitResultsTask = null;
    foreach ($tasks as $t) {
        if ($t->getTaskCode() == $GLOBALS["TASK_CODES"]["KIT_RESULTS"]) {
            // There exists an open REGISTER KIT TASK
            $kitResultsTask = $t;
            break;
        }
    }

    // Create a new "SCAN KIT" TASK
    createScanKitTask($admission->getId());
    if ($kitResultsTask) {
        $lc2Action->setActionType(LC2Action::ACTION_REDIRECT_TO_TASK);
        $lc2Action->setTaskId($kitResultsTask->getId());
    } else {
        // The "KIT_RESULTS" TASK was not found, so we will ask LC2 to redirect to the CASE
        $lc2Action->setActionType(LC2Action::ACTION_REDIRECT_TO_CASE);
    }

    return $lc2Action;
}

/**
 * Inserts a "KIT_INFO" TASK in the ADMISSION and fills its questions with Kit Information
 * Return the ID of the inserted TASK
 *
 * @param int $admissionId
 * @param KitInfo $kitInfo
 * @throws APIException
 * @return string
 */
function createKitInfoTask($admissionId, $kitInfo) {
    $api = LinkcareSoapAPI::getInstance();
    $taskId = $api->task_insert_by_task_code($admissionId, $GLOBALS["TASK_CODES"]["KIT_INFO"]);
    if ($api->errorCode() || !$taskId) {
        // An unexpected error happened while creating the TASK
        throw new APIException($api->errorCode(), $api->errorMessage());
    }

    $task = $api->task_get($taskId);
    if ($api->errorCode()) {
        // An unexpected error happened while getting TASK information
        throw new APIException($api->errorCode(), $api->errorMessage());
    }

    // TASK inserted. Now update the questions with the Kit Information
    $forms = $api->task_activity_list($taskId);
    if ($api->errorCode()) {
        // An unexpected error happened while obtaining the list of activities
        throw new APIException($api->errorCode(), $api->errorMessage());
    }
    $targetForm = null;
    foreach ($forms as $form) {
        if ($form->getFormCode() == $GLOBALS["FORM_CODES"]["KIT_INFO"]) {
            // The KIT_INFO FORM was found => update the questions with Kit Information
            $targetForm = $api->form_get_summary($form->getId(), true, false);
            break;
        }
    }

    $arrQuestions = [];
    if ($targetForm) {
        if ($q = $targetForm->findQuestion($GLOBALS["KIT_INFO_Q_ID"]["KIT_ID"])) {
            $q->setValue($kitInfo->getId());
            $arrQuestions[] = $q;
        }
        if ($q = $targetForm->findQuestion($GLOBALS["KIT_INFO_Q_ID"]["MANUFACTURE_PLACE"])) {
            $q->setValue($kitInfo->getManufacture_place());
            $arrQuestions[] = $q;
        }
        $date = explode(" ", trim($kitInfo->getManufacture_date()));

        if ($q = $targetForm->findQuestion($GLOBALS["KIT_INFO_Q_ID"]["MANUFACTURE_DATE"])) {
            $q->setValue($date[0]);
            $arrQuestions[] = $q;
        }
        if (count($date) > 1 && $q = $targetForm->findQuestion($GLOBALS["KIT_INFO_Q_ID"]["MANUFACTURE_TIME"])) {
            $q->setValue($date[1]);
            $arrQuestions[] = $q;
        }
        if ($q = $targetForm->findQuestion($GLOBALS["KIT_INFO_Q_ID"]["EXPIRATION_DATE"])) {
            $q->setValue($kitInfo->getExp_date());
            $arrQuestions[] = $q;
        }
        if ($q = $targetForm->findQuestion($GLOBALS["KIT_INFO_Q_ID"]["BATCH_NUMBER"])) {
            $q->setValue($kitInfo->getBatch_number());
            $arrQuestions[] = $q;
        }
        $api->form_set_all_answers($targetForm->getId(), $arrQuestions, true);
        if ($api->errorCode()) {
            // An unexpected error happened while obtaining the list of activities
            throw new APIException($api->errorCode(), $api->errorMessage());
        }
    } else {
        throw new APIException("FORM NOT FOUND", "KIT INFO FORM NOT FOUND: (" . $GLOBALS["FORM_CODES"]["KIT_INFO"] . ")");
    }

    $task->clearAssignments();
    $a = new APITaskAssignment(APITaskAssignment::SERVICE, null, null);
    $task->addAssignments($a);
    $api->task_set($task);

    return $taskId;
}

/**
 * Inserts a "PRESCRIPTION_INFO" TASK in the ADMISSION and fills its questions with prescription Information
 * Return the ID of the inserted TASK
 *
 * @param int $admissionId
 * @param Prescription $prescription
 * @throws APIException
 * @return string
 */
function createPrescriptionInfoTask($admissionId, $prescription) {
    $api = LinkcareSoapAPI::getInstance();
    $taskId = $api->task_insert_by_task_code($admissionId, $GLOBALS["TASK_CODES"]["PRESCRIPTION_INFO"]);
    if ($api->errorCode() || !$taskId) {
        // An unexpected error happened while creating the TASK
        throw new APIException($api->errorCode(), $api->errorMessage());
    }

    $task = $api->task_get($taskId);
    if ($api->errorCode()) {
        // An unexpected error happened while getting TASK information
        throw new APIException($api->errorCode(), $api->errorMessage());
    }

    // TASK inserted. Now update the questions with the prescription Information
    $forms = $api->task_activity_list($taskId);
    if ($api->errorCode()) {
        // An unexpected error happened while obtaining the list of activities
        throw new APIException($api->errorCode(), $api->errorMessage());
    }
    $targetForm = null;
    foreach ($forms as $form) {
        if ($form->getFormCode() == $GLOBALS["FORM_CODES"]["PRESCRIPTION_INFO"]) {
            // The KIT_INFO FORM was found => update the questions with Kit Information
            $targetForm = $api->form_get_summary($form->getId(), true, false);
            break;
        }
    }

    $arrQuestions = [];
    if ($targetForm) {
        if ($q = $targetForm->findQuestion($GLOBALS["PRESCRIPTION_INFO_Q_ID"]["PRESCRIPTION_ID"])) {
            $q->setValue($prescription->getId());
            $arrQuestions[] = $q;
        }
        if ($q = $targetForm->findQuestion($GLOBALS["PRESCRIPTION_INFO_Q_ID"]["PRESCRIPION_EXPIRATION"])) {
            $q->setValue($prescription->getExpirationDate());
            $arrQuestions[] = $q;
        }
        if ($q = $targetForm->findQuestion($GLOBALS["PRESCRIPTION_INFO_Q_ID"]["ROUNDS"])) {
            $q->setValue($prescription->getRounds());
            $arrQuestions[] = $q;
        }
        $api->form_set_all_answers($targetForm->getId(), $arrQuestions, true);
        if ($api->errorCode()) {
            // An unexpected error happened while obtaining the list of activities
            throw new APIException($api->errorCode(), $api->errorMessage());
        }
    } else {
        throw new APIException("FORM NOT FOUND", "PRESCRIPTION INFO FORM NOT FOUND: (" . $GLOBALS["FORM_CODES"]["PRESCRIPTION_INFO"] . ")");
    }

    $task->clearAssignments();
    $a = new APITaskAssignment(APITaskAssignment::SERVICE, null, null);
    $task->addAssignments($a);
    $api->task_set($task);

    return $taskId;
}

/**
 * Inserts a new "REGISTER_KIT" TASK in the ADMISSION
 * Returns an array with 2 elements:
 * 1- The ID of the inserted TASK
 * 2- The ID of the "REGISTER_KIT" FORM (the TASK contains more FORMs, and we want to redirect to this specific FORM)
 *
 * @param string $admissionId
 * @throws APIException
 * @return string[]
 */
function createRegisterKitTask($admissionId) {
    $api = LinkcareSoapAPI::getInstance();
    $taskId = $api->task_insert_by_task_code($admissionId, $GLOBALS["TASK_CODES"]["REGISTER_KIT"]);
    if ($api->errorCode() || !$taskId) {
        // An unexpected error happened while creating the TASK
        throw new APIException($api->errorCode(), $api->errorMessage());
    }

    // TASK inserted. Now update the questions with the Kit Information
    $forms = $api->task_activity_list($taskId);
    if ($api->errorCode()) {
        // An unexpected error happened while obtaining the list of activities
        throw new APIException($api->errorCode(), $api->errorMessage());
    }

    /* @var APIForm $targetForm */
    $targetForm = null;
    foreach ($forms as $form) {
        if ($form->getFormCode() == $GLOBALS["FORM_CODES"]["REGISTER_KIT"]) {
            // The KIT_INFO FORM was found => update the questions with Kit Information
            $targetForm = $api->form_get_summary($form->getId(), true, false);
            break;
        }
    }

    return [$taskId, $targetForm ? $targetForm->getId() : null];
}

/**
 * Inserts a new "SCAN_KIT" TASK in the ADMISSION and assigns it to role "SERVICE"
 *
 * @param string $admissionId
 * @throws APIException
 * @return string
 */
function createScanKitTask($admissionId) {
    $api = LinkcareSoapAPI::getInstance();
    $taskId = $api->task_insert_by_task_code($admissionId, $GLOBALS["TASK_CODES"]["SCAN_KIT"]);
    if ($api->errorCode() || !$taskId) {
        // An unexpected error happened while creating the TASK
        throw new APIException($api->errorCode(), $api->errorMessage());
    }

    $task = $api->task_get($taskId);
    if ($api->errorCode()) {
        // An unexpected error happened while getting TASK information
        throw new APIException($api->errorCode(), $api->errorMessage());
    }

    $task->clearAssignments();
    $a = new APITaskAssignment(APITaskAssignment::SERVICE, null, null);
    $task->addAssignments($a);
    $api->task_set($task);

    return $taskId;
}

/**
 * Sends a request to the remote service that manages the Kits to change the status of a Kit
 *
 * @param string $kitId
 * @param string $status
 */
function updateKitStatus($kitId, $status) {
    $endpoint = $GLOBALS["KIT_INFO_LINK"];
    $uri = parse_url($endpoint)['scheme'] . '://' . parse_url($endpoint)['host'] . ":" . parse_url($endpoint)['port'];

    $client = new SoapClient(null, ['location' => $endpoint, 'uri' => $uri, "connection_timeout" => 10]);
    try {
        $result = $client->update_kit_status($kitId, $status);
    } catch (SoapFault $fault) {
        service_log("ERROR: SOAP Fault: (faultcode: {$fault->faultcode}, faultstring: {$fault->faultstring})");
    }
}

$kitInfo = new KitInfo();

error_reporting(0);
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $kitInfo->setId($_POST["kit_id"]);
    $kitInfo->setPrescriptionId(urldecode($_POST["prescription_id"]));
    $kitInfo->setBatch_number($_POST["batch_number"]);
    $kitInfo->setManufacture_place($_POST["manufacture_place"]);
    $kitInfo->setManufacture_date($_POST["manufacture_date"]);
    $kitInfo->setExp_date($_POST["expiration_date"]);
    header('Content-type: application/json');
    echo service_dispatch_kit($_POST["token"], $kitInfo);
}
