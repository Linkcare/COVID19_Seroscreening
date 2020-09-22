<?php

// Link the config params
require_once ("default_conf.php");

setSystemTimeZone();

/**
 *
 * @param string $token
 * @param KitInfo $kitInfo
 * @return string[]
 */
function service_dispatch_kit($token = null, $kitInfo) {
    $timezone = "0";

    if (!preg_match('/^(\w{5,7})$/', $kitInfo->getId())) {
        $error = new ErrorInfo(ErrorInfo::INVALID_KIT);
        $lc2Action = new LC2Action(LC2Action::ACTION_ERROR_MSG);
        $lc2Action->setErrorMessage($error->getErrorMessage());
        service_log("ERROR: Invalid KIT ID");
    } else {
        try {
            LinkcareSoapAPI::init($GLOBALS["WS_LINK"], $timezone, $token);
            $session = LinkcareSoapAPI::getInstance()->getSession();
            $lang = $session->getLanguage();
            Localization::init($session->getLanguage());
            // Find the SUBSCRIPTION of the PROGRAM "Seroscreening" of the active user
            $lc2Action = processKit($kitInfo);
        } catch (APIException $e) {
            $lc2Action = new LC2Action(LC2Action::ACTION_ERROR_MSG);
            $lc2Action->setErrorMessage($e->getMessage());
            service_log("ERROR: " . $e->getMessage());
        } catch (KitException $k) {
            $lc2Action = new LC2Action(LC2Action::ACTION_ERROR_MSG);
            $lc2Action->setErrorMessage($k->getMessage());
            service_log("ERROR: " . $k->getMessage());
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

    $subscriptionId = findSubscription();

    // Find out if there exists a patient assigned to the Kit ID
    /* @var APIResponse $result */
    $cases = $api->case_search("SEROSCREENING:" . $kitInfo->getId() . "");

    if ($api->errorCode()) {
        throw new APIException($api->errorCode(), $api->errorMessage());
    }

    $caseId = null;
    if (!empty($cases)) {
        $caseId = $cases[0]->getId();
    }

    if (!$caseId) {
        // The KIT ID is new. Create a new ADMISSION
        $lc2Action = createNewAdmission($kitInfo, $subscriptionId);
    } else {
        if ($kitInfo->getPrescriptionId()) {
            // A PRESCRIPTION_REF has been provided, so we must ensure that the CASE found has the indicated PRESCRIPTION_REF
            $caseContact = $api->case_get_contact($caseId, $subscriptionId);
            $studyRef = $caseContact->findIdentifier("PRESCRIPTION_REF");
            if ($studyRef && $studyRef->getValue() != $kitInfo->getPrescriptionId()) {
                /*
                 * ERROR: the STUDY REF provided is different than the one assigned to the CASE. This means that we are scanning that the KitID was
                 * previously used for another CASE
                 */
                throw new KitException(ErrorInfo::KIT_ALREADY_USED);
            }
        }
        // Find the active ADMISSION for the CASE found
        $admissions = $api->case_admission_list($caseId, true, $subscriptionId);
        if ($api->errorCode()) {
            throw new APIException($api->errorCode(), $api->errorMessage());
        }
        $curAdmission = null;
        $finishedAdmission = null;
        foreach ($admissions as $a) {
            if (in_array($a->getStatus(), [APIAdmission::STATUS_ACTIVE, APIAdmission::STATUS_ENROLLED, APIAdmission::STATUS_INCOMPLETE])) {
                $curAdmission = $a;
                break;
            } else {
                $finishedAdmission = $a;
            }
        }
        if (!$curAdmission) {
            $lc2Action = new LC2Action();
            if ($finishedAdmission) {
                // We have found a finished ADMISSION. Redirect LC2 to the patient
                $lc2Action->setActionType(LC2Action::ACTION_REDIRECT_TO_CASE);
                $lc2Action->setCaseId($finishedAdmission->getCaseId());
                $lc2Action->setAdmissionId($finishedAdmission->getId());
            } else {
                // The Kit ID exists for the patient, but there are no ADMISSIONs. This could mean that the Kit was processed in another SUBSCRIPTION
                throw new KitException(ErrorInfo::KIT_ALREADY_USED);
            }
        } else {
            // We have found an existing ADMISSION for this Kit ID
            $lc2Action = updateAdmission($curAdmission, $kitInfo);
        }
    }

    return $lc2Action;
}

/**
 * Searches the subscription of the active user that corresponds to the PROGRAM "Seroscreening"
 */
function findSubscription() {
    $api = LinkcareSoapAPI::getInstance();
    $subscriptionId = null;
    $filter = ["member_role" => 24, "member_team" => $api->getSession()->getTeamId()];
    $subscriptions = $api->subscription_list($filter);
    foreach ($subscriptions as $s) {
        $p = $s->getProgram();
        if ($p && $p->getCode() == $GLOBALS["PROGRAM_CODE"]) {
            $subscriptionId = $s->getId();
            break;
        }
    }

    if (!$subscriptionId) {
        $e = new APIException("SUBSCRIPTION.NOT_FOUND", "No subscription found for the active user in Seroscreening program");
        throw $e;
    }

    return $subscriptionId;
}

/**
 *
 * @param KitInfo $kitInfo
 * @param int $subscriptionId
 * @return LC2Action
 */
function createNewAdmission($kitInfo, $subscriptionId) {
    $lc2Action = new LC2Action();
    $api = LinkcareSoapAPI::getInstance();

    // Create the case
    $contactInfo = new APIContact();

    $device = new APIContactChannel();
    $device->setValue("SEROSCREENING:" . $kitInfo->getId());
    $contactInfo->addDevice($device);

    if ($kitInfo->getPrescriptionId()) {
        $studyRef = new APIIdentifier('PRESCRIPTION_REF', $kitInfo->getPrescriptionId());
        $contactInfo->addIdentifier($studyRef);
        $contactInfo->setName($kitInfo->getPrescriptionId());
    }

    // Create a new CASE with incomplete data (only the KIT_ID)
    $caseId = $api->case_insert($contactInfo, $subscriptionId, true);
    $lc2Action->setCaseId($caseId);

    if ($api->errorCode()) {
        $lc2Action->setActionType(LC2Action::ACTION_ERROR_MSG);
        $lc2Action->setErrorMessage($api->errorMessage());
        return $lc2Action;
    }

    $failed = false;
    // Create an ADMISSION
    $admissionId = $api->admission_create($caseId, $subscriptionId, null, null, true);
    $lc2Action->setAdmissionId($admissionId);

    if ($api->errorCode()) {
        // An unexpected error happened while creating the ADMISSION: Delete the CASE
        $failed = true;
        $lc2Action->setActionType(LC2Action::ACTION_ERROR_MSG);
        $lc2Action->setErrorMessage($api->errorMessage());
    }

    try {
        createKitInfoTask($admissionId, $kitInfo);
        list($taskId, $formId) = createRegisterKitTask($admissionId, ($studyRef !== null ? 2 : 1));
        $lc2Action->setActionType(LC2Action::ACTION_REDIRECT_TO_FORM);
        $lc2Action->setTaskId($taskId);
        $lc2Action->setFormId($formId);
    } catch (APIException $e) {
        $failed = true;
        $lc2Action->setActionType(LC2Action::ACTION_ERROR_MSG);
        $lc2Action->setErrorMessage($api->errorMessage());
    }

    if ($failed) {
        if ($admissionId) {
            $api->admission_delete($admissionId);
        }
        $api->case_delete($caseId, "DELETE");
    } else {
        updateKitStatus($kitInfo->getId(), KitInfo::STATUS_ASSIGNED);
    }

    return $lc2Action;
}

/**
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
    $registerKitTask = null;
    foreach ($tasks as $t) {
        if ($t->getTaskCode() == $GLOBALS["TASK_CODES"]["REGISTER_KIT"] && $t->getStatus() != "DONE") {
            // There exists an open REGISTER KIT TASK
            $registerKitTask = $t;
            break;
        }
    }

    $registerStatus = ($admission->getStatus() == APIAdmission::STATUS_INCOMPLETE) ? 2 : 3;

    if (!$registerKitTask) {
        // Create a new REGISTER KIT TASK
        list($taskId, $formId) = createRegisterKitTask($admission->getId(), $registerStatus);
        $lc2Action->setActionType(LC2Action::ACTION_REDIRECT_TO_FORM);
        $lc2Action->setTaskId($taskId);
        $lc2Action->setFormId($formId);
    } else {
        /*
         * The REGISTER KIT TASK already exists and it is OPEN. Redirect to the FORM if possible, or to the TASK if the FORM is not found
         */
        $lc2Action->setActionType(LC2Action::ACTION_REDIRECT_TO_TASK);
        $lc2Action->setTaskId($registerKitTask->getId());
        foreach ($registerKitTask->getForms() as $f) {
            if ($f->getFormCode() == $GLOBALS["FORM_CODES"]["REGISTER_KIT"]) {
                $lc2Action->setActionType(LC2Action::ACTION_REDIRECT_TO_FORM);
                $lc2Action->setFormId($f->getId());
                break;
            }
        }
    }

    return $lc2Action;
}

/**
 * Inserts in an ADMISSION the TASK with Kit Information
 *
 * @param int $admissionId
 * @param KitInfo $kitInfo
 * @return int
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
        // An unexpected error happened while creating the TASK
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

function createRegisterKitTask($admissionId, $registerStatus) {
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

    if ($targetForm) {
        $api->form_set_answer($targetForm->getId(), $GLOBALS["REGISTER_KIT_Q_ID"]["REGISTER_STATUS"], $registerStatus);
        if ($api->errorCode()) {
            // An unexpected error happened while obtaining the list of activities
            throw new APIException($api->errorCode(), $api->errorMessage());
        }
    } else {
        throw new APIException("FORM NOT FOUND", "REGISTER KIT FORM NOT FOUND: (" . $GLOBALS["FORM_CODES"]["REGISTER_KIT"] . ")");
    }

    return [$taskId, $targetForm ? $targetForm->getId() : null];
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
    $kitInfo->setPrescriptionId($_POST["prescription_id"]);
    $kitInfo->setBatch_number($_POST["batch_number"]);
    $kitInfo->setManufacture_place($_POST["manufacture_place"]);
    $kitInfo->setManufacture_date($_POST["manufacture_date"]);
    $kitInfo->setExp_date($_POST["expiration_date"]);
    header('Content-type: application/json');
    echo service_dispatch_kit($_POST["token"], $kitInfo);
}
