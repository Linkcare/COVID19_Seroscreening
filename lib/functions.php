<?php

/**
 *
 * @param string $token
 * @param KitInfo $kitInfo
 * @return LC2Action
 */
function service_dispatch_kit($token = null, $kitInfo, $subscriptionId = null) {
    $timezone = "0";

    if (!$GLOBALS["KIT_INFO_MGR"]) {
        // If no url has been provided to access the service that manages del KIT INFO database, then we assume that we have local access to DB
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
            return $lc2Action;
        }
    }

    if (!preg_match('/^([-_A-Za-z0-9]{5,7})$/', $kitInfo->getId())) {
        $error = new ErrorInfo(ErrorInfo::INVALID_KIT);
        $lc2Action = new LC2Action(LC2Action::ACTION_ERROR_MSG);
        $lc2Action->setErrorMessage($error->getErrorMessage());
    } else {
        try {

            LinkcareSoapAPI::setEndpoint($GLOBALS["WS_LINK"]);
            LinkcareSoapAPI::session_join($token, $timezone);
            $session = LinkcareSoapAPI::getInstance()->getSession();
            Localization::init($session->getLanguage());
            // Find the SUBSCRIPTION of the PROGRAM "Seroscreening" of the active user
            $lc2Action = processKit($kitInfo, $subscriptionId);
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

    return $lc2Action;
}

/**
 *
 * @param string $sharedKeyStr
 * @param KitInfo $kitInfo
 * @return string[]
 */
function generateSignUpUrl($sharedKeyStr, $kitInfo) {
    $session = null;
    $errorStr = null;
    $url = null;
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
        $errorStr = 'Service user cannot connect to API. Contact a system administrator to solve the problem';
    } catch (Exception $e) {
        $errorStr = 'Service user cannot connect to API. Contact a system administrator to solve the problem';
    }

    if (!$errorStr) {
        $api = LinkcareSoapAPI::getInstance();
        try {
            $extendedSharedKey = $api->shared_key_extend($sharedKeyStr, $kitInfo->toJson());
            $url = $kitInfo->getInstance_url();
            $matches = null;
            if (preg_match('~^(http[s]?://[^/]+)~', $url, $matches)) {
                $url = $matches[1];
                $url .= '/sign_up?shared_key=' . urlencode($extendedSharedKey);
            }
        } catch (APIException $e) {
            $errorStr = $e->getMessage();
        } catch (Exception $e) {
            $errorStr = $e->getMessage();
        }
    }

    return ['url' => $url, 'error' => $errorStr];
}

/**
 *
 * @param KitInfo $kitInfo
 * @throws Exception
 * @return LC2Action
 */
function processKit($kitInfo, $subscriptionId = null) {
    $lc2Action = null;
    $api = LinkcareSoapAPI::getInstance();

    $foundAdmission = null; // Existing Admission that should be used. It corresponds to the information provided (KIT ID, PRESCRIPTION or both)
    $existingCaseId = null;
    $programId = null;
    $teamId = null;
    $subscription = null;

    $tz_object = new DateTimeZone('UTC');
    $datetime = new DateTime();
    $datetime->setTimezone($tz_object);
    $today = $datetime->format('Y\-m\-d');
    if ($kitInfo->getExp_date() && $kitInfo->getExp_date() < $today) {
        throw new KitException(ErrorInfo::KIT_EXPIRED);
    }

    // Find out if there exists a patient assigned to the Kit ID
    $casesByDevice = $api->case_search('SEROSCREENING:' . $kitInfo->getId());
    if (!empty($casesByDevice)) {
        /* @var APICase $caseByDevice */
        $caseByDevice = $casesByDevice[0];
    }

    if ($api->getSession()->getRoleId() == 39) {
        $existingCaseId = $api->getSession()->getCaseId();
        /*
         * We are in 'patient' mode and the KIT_ID is associated to a CASE.
         * In this case the active session user must be the same CASE found
         */
        if ($caseByDevice) {
            if ($api->getSession()->getCaseId() != $caseByDevice->getId()) {
                // ERROR! The kit is not assigned to the active session user
                $lc2Action = new LC2Action(LC2Action::ACTION_ERROR_MSG);
                $error = new ErrorInfo(ErrorInfo::KIT_ALREADY_USED);
                $lc2Action->setErrorMessage($error->getErrorMessage());
                return $lc2Action;
            }
        }
    }

    /*
     * The prescription information generally is provided only when creating a new ADMISSION.
     * If it is not provided, it means that we are looking for an existing ADMISSION than can be found using the KIT_ID
     */
    if (trim($kitInfo->getPrescriptionString()) != '' || trim($kitInfo->getParticipantRef())) {
        $prescription = new Prescription($kitInfo->getPrescriptionString(), $kitInfo->getParticipantRef());
        if (!$prescription->isValid()) {
            throw new KitException(ErrorInfo::PRESCRIPTION_WRONG_FORMAT);
        }
    }

    $alreadyInitialized = false;

    if ($prescription && $prescription->getExpirationDate() && $prescription->getExpirationDate() < $today) {
        throw new KitException(ErrorInfo::PRESCRIPTION_EXPIRED);
    }

    // Find the target SUBSCRIPTION (only if we don't know the ADMISSION
    if (!$prescription || !$prescription->getAdmissionId()) {
        // We are in professional mode
        if ($subscriptionId) {
            // An specific SUBSCRIPTION ID has been provided
            $subscription = $api->subscription_get(null, null, $subscriptionId);
        } else {
            $subscriptions = findSubscription($prescription, $kitInfo->getProgramCode(), $kitInfo->getTeamCode());
            /* @var APISubscription $subscription */
            $subscription = empty($subscriptions) ? null : reset($subscriptions);
            if (count($subscriptions) > 1) {
                // The user must select a SUBSCRIPTION
                $lc2Action = new LC2Action(LC2Action::ACTION_SERVICE_REQUEST);
                $lc2Action->setProgramId($subscription->getProgram()->getId());
                $lc2Action->setRequestType(LC2Action::REQUEST_SUBSCRIPTION);
                return $lc2Action;
            }
        }
    }

    if ($prescription && $prescription->getAdmissionId()) {
        // The prescription contains information about the ADMISSION that should be used
        list($foundAdmission, $alreadyInitialized, $lc2Action) = loadExistingAdmission($prescription->getAdmissionId(), $kitInfo, $caseByDevice);
        if ($lc2Action) {
            // There is something wrong with the ADMISSION
            return $lc2Action;
        }
        $subscription = $foundAdmission->getSubscription();
        $programId = $subscription->getProgram()->getId();
        $existingCaseId = $foundAdmission->getCaseId();
        $teamId = $subscription->getTeam()->getId();
        // Check if the active professional has access to the subscription
        if (empty($api->subscription_list(['subscription' => $subscription->getId()]))) {
            $error = new ErrorInfo(ErrorInfo::SUBSCRIPTION_FORBIDDEN);
            $lc2Action = new LC2Action(LC2Action::ACTION_ERROR_MSG);
            $lc2Action->setErrorMessage($error->getErrorMessage());
            return $lc2Action;
        }
    } elseif ($prescription) {
        if (!$subscription) {
            throw new KitException(ErrorInfo::SUBSCRIPTION_NOT_FOUND);
        }
        list($foundAdmission, $existingCaseId) = loadAdmissionFromPrescription($subscription, $kitInfo, $prescription, $caseByDevice);
        /* If we finally find an existing a n ADMISSION from the PRESCRIPTION information, it must be initialized when it was created */
        $alreadyInitialized = $foundAdmission != null;
        $programId = $subscription->getProgram()->getId();
        $teamId = $subscription->getTeam()->getId();
    } elseif (!$caseByDevice) {
        // We only have the KIT_ID and no PATIENT is assigned to that device. Create a new ADMISSION
        if (!$subscription) {
            throw new KitException(ErrorInfo::SUBSCRIPTION_NOT_FOUND);
        }
    } else {
        // We only have the KIT_ID and we have found a PATIENT for that device. Select the first admission of the CASE assigned to the KIT

        $searchCondition = new StdClass();
        $searchCondition->data_code = new StdClass();
        $searchCondition->data_code->name = 'KIT_ID';
        $searchCondition->data_code->value = $kitInfo->getId();
        $kitAdmissions = $api->case_admission_list($caseByDevice->getId(), true, $subscription ? $subscription->getId() : null,
                json_encode($searchCondition));

        if (count($kitAdmissions) == 0) {
            // It is not possible to create a new ADMISSION without the prescription information
            throw new KitException(ErrorInfo::PRESCRIPTION_MISSING);
        }

        $foundAdmission = $kitAdmissions[0]; // There can only exist one Admission per device
        $alreadyInitialized = true;
    }

    if ($foundAdmission &&
            !in_array($foundAdmission->getStatus(), [APIAdmission::STATUS_ACTIVE, APIAdmission::STATUS_ENROLLED, APIAdmission::STATUS_INCOMPLETE])) {
        // The ADMISSION exists and it is finished. Do nothing.
        $lc2Action = new LC2Action(LC2Action::ACTION_REDIRECT_TO_CASE);
        $lc2Action->setCaseId($foundAdmission->getCaseId());
        $lc2Action->setAdmissionId($foundAdmission->getId());
        $programId ? $lc2Action->setProgramId($programId) : null;
        $teamId ? $lc2Action->setTeamId($teamId) : null;
        return $lc2Action;
    }

    // Check point passed. initialize or update the ADMISSION
    if (!$alreadyInitialized) {
        /*
         * We need to initialize an ADMISSION. There are 2 situations:
         * - It is necessary to create a new ADMISSION. This situation happens when a PROFESSIONAL starts the process (and we know both the
         * information about the KIT and the PARTICIPANT
         * - There exists an ADMISSION for the prescption provided, but it has not been initialized yet with the KIT information. This situation
         * happens when the ADMISSION was created by the PARTICIPANT and now we are adding the KIT information
         */
        if (!$subscription) {
            throw new KitException(ErrorInfo::SUBSCRIPTION_NOT_FOUND);
        }
        $lc2Action = initializeAdmission($kitInfo, $prescription, $existingCaseId, $subscription, $foundAdmission);
    } else {
        // The ADMISSION for the KIT exists and it has already been initialized
        $lc2Action = updateAdmission($foundAdmission, $kitInfo);
        $lc2Action->setProgramId($foundAdmission->getSubscription()->getProgram()->getId());
        $lc2Action->setTeamId($foundAdmission->getSubscription()->getTeam()->getId());
    }

    // Complete the action with the PROGRAM and TEAM information
    $programId ? $lc2Action->setProgramId($programId) : null;
    $teamId ? $lc2Action->setTeamId($teamId) : null;

    return $lc2Action;
}

/**
 * Load an existing ADMISSION and check if it has already been initialized
 *
 * @param string $admissionId
 * @param KitInfo $kitInfo
 * @param APICase $caseByDevice
 * @throws APIException
 * @throws KitException
 * @return [APIAdmission, boolean, LC2Action]
 */
function loadExistingAdmission($admissionId, $kitInfo, $caseByDevice) {
    $api = LinkcareSoapAPI::getInstance();

    $admission = $api->admission_get($admissionId);
    if (!$admission) {
        throw new KitException(ErrorInfo::ADMISSION_NOT_FOUND);
    }

    if ($caseByDevice && $caseByDevice->getId() != $admission->getCaseId()) {
        // There exists an ADMISSION for the KIT that correspond to a CASE different that the ADMISSION provided
        return [$admission, true, invalidateKit($admission, ErrorInfo::KIT_ALREADY_USED)];
    }

    if ($caseByDevice) {
        $searchCondition = new StdClass();
        $searchCondition->data_code = new StdClass();
        $searchCondition->data_code->name = 'KIT_ID';
        $searchCondition->data_code->value = $kitInfo->getId();
        $kitAdmissions = $api->case_admission_list($caseByDevice->getId(), true, $admission->getSubscription()->getId(), json_encode($searchCondition));

        $admissionForKit = count($kitAdmissions) > 0 ? $kitAdmissions[0] : null; // There can only exist one Admission per device
        if ($admissionForKit && $admissionForKit->getId() != $admission->getId()) {
            return [$admission, true, invalidateKit($admission, ErrorInfo::KIT_ALREADY_USED)];
        }
    }

    if ($admission->getStatus() == 'INCOMPLETE') {
        // The patient information is not yet complete. Cannot assign KIT information to the admission
        $lc2Action = new LC2Action(LC2Action::ACTION_REDIRECT_TO_CASE);
        $lc2Action->setAdmissionId($admission->getId());
        $lc2Action->setCaseId($admission->getCaseId());
        $error = new ErrorInfo(ErrorInfo::ADMISSION_INCOMPLETE);
        $lc2Action->setErrorMessage($error->getErrorMessage());
        return $lc2Action;
    }

    $alreadyInitialized = $admission->getStatus() != 'ENROLLED';

    return [$admission, $alreadyInitialized];
}

/**
 * If the "HEALTH FORFAIT" is OPEN, remove the KIT ID value.
 * Teh function returns a LC2 Action to redirect to:
 * <ul>
 * <li>Display the "HEALTH FORFAIT" TASK if it is OPEN</li>
 * <li>Display the CASE TASKS otherwise</li>
 * </ul>
 *
 * @param APIAdmission $admission
 * @throws APIException
 * @return LC2Action
 */
function invalidateKit($admission, $errorCode = null) {
    $api = LinkcareSoapAPI::getInstance();
    $lc2Action = new LC2Action(LC2Action::ACTION_REDIRECT_TO_CASE);
    $lc2Action->setAdmissionId($admission->getId());
    $lc2Action->setCaseId($admission->getCaseId());
    if ($errorCode) {
        $error = new ErrorInfo($errorCode);
        $lc2Action->setErrorMessage($error->getErrorMessage());
    }

    // If the TASK "HEALTH_FORFAIT" exists and is open, then reset the value of the KIT ID and redirect to the TASK

    $filter = new TaskFilter();
    $filter->setObjectType('TASKS');
    $filter->setStatusIds('OPEN');
    $taskCodes = [$GLOBALS["TASK_CODES"]["HEALTH_FORFAIT"], $GLOBALS["TASK_CODES"]["SCAN_KIT_LINK"]];
    $filter->setTaskCodes(implode(',', $taskCodes));
    $tasks = $api->admission_get_task_list($admission->getId(), null, null, $filter);
    $hfTask = null;
    foreach ($tasks as $t) {
        // There exists an open REGISTER KIT TASK
        $hfTask = $t;
        break;
    }

    if ($hfTask) {
        $lc2Action->setActionType(LC2Action::ACTION_REDIRECT_TO_TASK);
        $lc2Action->setTaskId($hfTask->getId());
        $forms = $api->task_activity_list($hfTask->getId());

        $formCodes = [$GLOBALS["FORM_CODES"]["HEALTH_FORFAIT"], $GLOBALS["FORM_CODES"]["SCAN_KIT_LINK"]];
        foreach ($forms as $form) {
            if (in_array($form->getFormCode(), $formCodes) && $form->getStatus() == "OPEN") {
                // The HEALTH FORFAIT FORM is open: reset the "KIT ID" ITEM
                $qId = $form->getFormCode() == $GLOBALS["FORM_CODES"]["HEALTH_FORFAIT"] ? $GLOBALS["HEALTH_FORFAIT"]["KIT_ID"] : $GLOBALS["SCAN_KIT_LINK_Q_ID"]["KIT_ID"];
                try {
                    $api->form_set_answer($form->getId(), $qId, '');
                } catch (Exception $e) {
                    // Ignore errors
                }
                break;
            }
        }
    }

    return $lc2Action;
}

/**
 * Generates a LC2 Action to display the first open TASK, or the last TASK of the ADMISSION if all are closed
 *
 * @param APIAdmission $admission
 * @throws APIException
 * @return LC2Action
 */
function redirectToFirstOpenTask($admission) {
    $api = LinkcareSoapAPI::getInstance();
    $lc2Action = new LC2Action(LC2Action::ACTION_REDIRECT_TO_CASE);
    $lc2Action->setAdmissionId($admission->getId());
    $lc2Action->setCaseId($admission->getCaseId());

    // If the TASK "" exists and is open, then reset the value of the KIT ID and redirect to the TASK
    try {
        $filter = new TaskFilter();
        $filter->setObjectType('TASKS');
        $tasks = $api->admission_get_task_list($admission->getId(), null, null, $filter);
    } catch (Exception $e) {
        // Ignore error
        $tasks = [];
    }
    $firstOpenTask = null;
    $firstTask = null;
    foreach ($tasks as $t) {
        if (!$firstOpenTask && in_array($t->getStatus(), ['OPEN', 'ASSIGNED/NOT DONE'])) {
            // There exists an open TASK
            $firstOpenTask = $t;
        }
        $firstTask = $t;
    }

    if ($firstOpenTask) {
        $firstTask = $firstOpenTask;
    }

    if ($firstTask) {
        $lc2Action->setActionType(LC2Action::ACTION_REDIRECT_TO_TASK);
        $lc2Action->setTaskId($firstTask->getId());
    }

    return $lc2Action;
}

/**
 *
 * @param APISubscription $subscription
 * @param KitInfo $kitInfo
 * @param Prescription $prescription
 * @param APICase $caseByDevice An existing CASE already associated to the KIT ID (if any)
 * @throws KitException
 * @throws APIException
 * @return [APIAdmission, boolean, string]
 */
function loadAdmissionFromPrescription($subscription, $kitInfo, $prescription, $caseByDevice) {
    $api = LinkcareSoapAPI::getInstance();

    /*
     * We have a Prescription that allows us to:
     * - If a PRESCRIPTION ID and/or PARTICIPANT ID are provided, find existing ADMISSION associated to those values and check that there are no
     * incoherences
     */

    /*
     * Find if there exists a patient with the PARTICIPANT_ID
     */
    $casesByPrescription = [];
    $casesByParticipant = [];
    $finishedAdmissions = 0;
    $existingCaseId = null;

    if ($prescription->getParticipantId($subscription->getTeam()->getCode())) {
        $searchCondition = new StdClass();
        $searchCondition->identifier = new StdClass();
        $searchCondition->identifier->code = $GLOBALS['PARTICIPANT_IDENTIFIER'];
        $searchCondition->identifier->value = $prescription->getParticipantId($subscription->getTeam()->getCode());
        // $searchCondition->identifier->program = $subscription->getProgram()->getId();
        // $searchCondition->identifier->team = $subscription->getTeam()->getId();
        $casesByParticipant = $api->case_search(json_encode($searchCondition));
    }
    if (!empty($casesByParticipant)) {
        $existingCaseId = $casesByParticipant[0]->getId();
    }
    if ($prescription->getId()) {
        $searchCondition = new StdClass();
        $searchCondition->subscription = $subscription->getId();
        $searchCondition->data_code = new StdClass();
        $searchCondition->data_code->name = 'PRESCRIPTION_ID';
        $searchCondition->data_code->value = $prescription->getId();
        $casesByPrescription = $api->case_search(json_encode($searchCondition));
        if (!empty($casesByPrescription)) {
            // Ensure that the PRESCRIPTION ID does not correspond to another CASE
            $found = false;
            foreach ($casesByPrescription as $c) {
                if ($c->getId() == $existingCaseId) {
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
    if (empty($casesByParticipant) && $caseByDevice) {
        /*
         * ERROR: The information in the prescription does not correspond to an existing participant but we found a patient with the KIT ID
         * assigned, what means that the KIT ID has already been used for another participant
         */
        throw new KitException(ErrorInfo::KIT_ALREADY_USED);
    }
    if ($existingCaseId && $caseByDevice && $existingCaseId != $caseByDevice->getId()) {
        /*
         * ERROR: the case associated to the PARTICIPANT_REF provided is different than the one associated to the KIT_ID. This means that we are
         * scanning a KitID that was previously used for another CASE
         */
        throw new KitException(ErrorInfo::KIT_ALREADY_USED);
    } elseif ($caseByDevice) {
        $existingCaseId = $caseByDevice->getId();
    }

    if ($caseByDevice) {
        $searchCondition = new StdClass();
        $searchCondition->data_code = new StdClass();
        $searchCondition->data_code->name = 'KIT_ID';
        $searchCondition->data_code->value = $kitInfo->getId();
        $kitAdmissions = $api->case_admission_list($caseByDevice->getId(), true, $subscription->getId(), json_encode($searchCondition));

        $admissionFromKit = count($kitAdmissions) > 0 ? $kitAdmissions[0] : null; // There can only exist one Admission per device
        $foundAdmission = $admissionFromKit; // By now, this is the ADMISSION that we must use
    }

    if ($prescription->getId()) {
        /*
         * At this point we are sure that the KIT ID is not used, or it is used and corresponds to the participant, but it is also necessary to
         * check another thing:
         * There may exist more than one prescription for the same participant, so we must ensure that the KIT ID is not assigned to a different
         * prescription
         */
        $prescriptionAdmissions = null;
        if ($existingCaseId) {
            // Find the ADMISSIONs of the CASE (with the correct prescription ID)
            $searchCondition = new StdClass();
            $searchCondition->data_code = new StdClass();
            $searchCondition->data_code->name = 'PRESCRIPTION_ID';
            $searchCondition->data_code->value = $prescription->getId();
            $prescriptionAdmissions = $api->case_admission_list($existingCaseId, true, $subscription->getId(), json_encode($searchCondition));
            foreach ($prescriptionAdmissions as $a) {
                if (in_array($a->getStatus(), [APIAdmission::STATUS_ACTIVE, APIAdmission::STATUS_ENROLLED, APIAdmission::STATUS_INCOMPLETE])) {
                    $foundAdmission = $a;
                } else {
                    $finishedAdmissions++;
                }
            }

            if ($admissionFromKit) {
                /*
                 * One of the ADMISSIONs of the prescription must be the one found by the KIT ID. Otherwise it means that the KIT was used in
                 * another
                 * prescription of the same patient
                 */
                $found = false;
                foreach ($prescriptionAdmissions as $a) {
                    if ($a->getId() == $admissionFromKit->getId()) {
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

    $existsActiveAdmission = $foundAdmission &&
            in_array($foundAdmission->getStatus(), [APIAdmission::STATUS_ACTIVE, APIAdmission::STATUS_ENROLLED, APIAdmission::STATUS_INCOMPLETE]);

    if (!$existsActiveAdmission) {
        // No active ADMISSION exists for the PRESCRIPTION so we will try to create a new one, but only if the KIT is not expired.
        $tz_object = new DateTimeZone('UTC');
        $datetime = new DateTime();
        $datetime->setTimezone($tz_object);
        $today = $datetime->format('Y\-m\-d');

        if ($prescription && $prescription->getExpirationDate() && $prescription->getExpirationDate() < $today) {
            throw new KitException(ErrorInfo::PRESCRIPTION_EXPIRED);
        }
        /*
         * Everything looks right so far, but there is one last verification: There cannont be more ADMISSIONS for the prescription ID than
         * permitted
         * rounds
         */
        if ($prescription->getRounds() > 0 && $finishedAdmissions >= $prescription->getRounds()) {
            throw new KitException(ErrorInfo::MAX_ROUNDS_EXCEEDED);
        }
    }
    return [$foundAdmission, $existingCaseId];
}

/**
 * Searches the SUBSCRIPTIONS to a PROGRAM that will be used to generate an ADMISSION.
 * To search the subscription it is necessary to know the PROGRAM CODE and a TEAM_CODE.<br>
 * The PROGRAM_CODE is calculated as follows:
 * <ul>
 * <li>The Prescription contains a PROGRAM_CODE (if not NULL)</li>
 * <li>Otherwise the default PROGRAM CODE provided in $defaultProgramCode (if not null)</li>
 * <li>Otherwise the PROGRAM CODE defined in the global variable $GLOBALS["PROGRAM_CODE"]</li>
 * </ul>
 * The TEAM_CODE is calculated as follows:
 * <ul>
 * <li>The Prescription contains a TEAM_CODE (if not NULL)</li>
 * <li>Otherwise the default TEAM CODE provided in $defaultTeamCode (if not null)</li>
 * <li>Otherwise all the TEAMS that have a SUBSCRIPTION for the PROGRAM CODE will be considered</li>
 * </ul>
 *
 * @param Prescription $prescription
 * @param string $defaultProgramCode
 * @param string $defaultTeamCode
 * @throws APIException
 * @return APISubscription[]
 */
function findSubscription($prescription, $defaultProgramCode = null, $defaultTeamCode = null) {
    $api = LinkcareSoapAPI::getInstance();
    $found = [];
    $teamId = null;
    $programId = null;
    $programCode = null;

    if ($prescription && $prescription->getTeam()) {
        $team = $api->team_get($prescription->getTeam());
        $teamId = $team ? $team->getId() : null;
    } elseif ($api->getSession()->getRoleId() != 39) {
        $teamId = $api->getSession()->getTeamId();
    } elseif ($defaultTeamCode) {
        $teamCode = $defaultTeamCode;
    }

    if ($api->getSession()->getRoleId() != 24 && $api->getSession()->getRoleId() != 39) {
        // The active user is a professional. The active role to create admissions must be CASE MANAGER
        $api->session_role(24);
    }

    if ($prescription && $prescription->getProgram()) {
        $program = $api->program_get($prescription->getProgram());
        $programId = $program->getId();
        $programCode = $program->getCode();
    } elseif ($defaultProgramCode) {
        $programCode = $defaultProgramCode;
    } else {
        $programCode = $GLOBALS["PROGRAM_CODE"];
    }

    if ($programCode && $teamCode) {
        $subscription = $api->subscription_get($programCode, $teamCode);
        if ($subscription) {
            $found[] = $subscription;
        }
    } else {
        $filter = ["member_role" => 24, "member_team" => $teamId, "program" => $programId];
        $subscriptions = $api->subscription_list($filter);
        foreach ($subscriptions as $s) {
            $t = $s->getTeam();
            if ($t && $teamId && $t->getId() != $teamId) {
                // The owner of the SUBSCRIPTION is not the expected one
                continue;
            }
            $p = $s->getProgram();
            if ($p && $p->getCode() == $programCode) {
                $found[] = $s;
            }
        }
    }

    return $found;
}

/**
 *
 * @param KitInfo $kitInfo
 * @param Prescription $prescription
 * @param APISubscription $subscription
 * @param APIAdmission $admission (default = null) If not NULL, the function will initialize an existing ADMISSION
 * @return LC2Action
 */
function initializeAdmission($kitInfo, $prescription, $caseId, $subscription, $admission = null) {
    $lc2Action = new LC2Action();
    $api = LinkcareSoapAPI::getInstance();
    $isNewCase = false;

    $participantId = $prescription ? $prescription->getParticipantId($subscription->getTeam()->getCode()) : null;

    if (!$caseId) {
        $isNewCase = true;
        // Create the case
        $contactInfo = new APIContact();

        $device = new APIContactChannel();
        $device->setValue("SEROSCREENING:" . $kitInfo->getId());
        $contactInfo->addDevice($device);

        if ($prescription && $prescription->getName()) {
            $contactInfo->setName($prescription->getName());
        }

        if ($prescription && $prescription->getSurname()) {
            $contactInfo->setFamilyName($prescription->getSurname());
        }

        if ($prescription && $prescription->getEmail()) {
            $email = new APIContactChannel();
            $email->setValue($prescription->getEmail());
            $email->setCategory('home');
            $contactInfo->addEmail($email);
        }

        if ($prescription && $prescription->getPhone()) {
            $phone = new APIContactChannel();
            $phone->setValue($prescription->getPhone());
            $phone->setCategory('mobile');
            $contactInfo->addPhone($phone);
        }

        if ($participantId) {
            $studyRef = new APIIdentifier($GLOBALS['PARTICIPANT_IDENTIFIER'], $participantId);
            $contactInfo->addIdentifier($studyRef);
        }

        // Create a new CASE with incomplete data (only the KIT_ID)
        $caseId = $api->case_insert($contactInfo, $subscription->getId(), true);
    }

    $lc2Action->setCaseId($caseId);
    $failed = false;

    $admissionCreated = false;
    if (!$admission) {
        // Create an ADMISSION
        $invitationActive = false;
        try {
            $admission = $api->admission_create($caseId, $subscription->getId(), null, null, true,
                    $prescription ? $prescription->getPrescriptionData() : null);
        } catch (APIException $e) {
            if ($e->errorCode == 'SUBSCRIPTION.INVITATION_NOT_FINISHED') {
                /*
                 * There exists an ADMISSION with the same invitation reference and the invitation term has not passed. It is not possible to create a
                 * new ADMISSION until the invitation term has passed
                 */
                $admission = null;
                $invitationActive = true;
            } else {
                $admission = null;
            }
        } catch (Exception $e) {
            $admission = null;
        }

        if ($invitationActive) {
            $error = new ErrorInfo(ErrorInfo::INVITATION_ACTIVE);
            $lc2Action->setActionType(LC2Action::ACTION_REDIRECT_TO_CASE);
            $lc2Action->setErrorMessage($error->getErrorMessage());
            return $lc2Action;
        } elseif (!$admission) {
            // An unexpected error happened while creating the ADMISSION: Delete the CASE
            $failed = true;
            $lc2Action->setActionType(LC2Action::ACTION_ERROR_MSG);
            $lc2Action->setErrorMessage($api->errorMessage());
            return $lc2Action;
        } elseif (!$admission->isNew()) {
            // There already exists an active Admission for the patient. Cannot create a new Admission
            $error = new ErrorInfo(ErrorInfo::ADMISSION_ACTIVE);
            $lc2Action->setActionType(LC2Action::ACTION_REDIRECT_TO_CASE);
            $lc2Action->setErrorMessage($error->getErrorMessage());
            return $lc2Action;
        }
        $admissionCreated = true;
    } else {
        // Initialize an existing ADMISSION
    }

    $lc2Action->setAdmissionId($admission->getId());

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
        if ($admission->getStatus() != APIAdmission::STATUS_INCOMPLETE) {
            // Only insert the Register Kit TASK if the ADMISSION is not incomplete
            list($taskId, $formId) = createRegisterKitTask($admission->getId());
            $lc2Action->setTaskId($taskId);
            if ($formId) {
                $lc2Action->setActionType(LC2Action::ACTION_REDIRECT_TO_FORM);
                $lc2Action->setFormId($formId);
            } else {
                $lc2Action->setActionType(LC2Action::ACTION_REDIRECT_TO_TASK);
            }
        } else {
            $lc2Action = redirectToFirstOpenTask($admission);
        }
    } catch (APIException $e) {
        $failed = true;
        $lc2Action->setActionType(LC2Action::ACTION_ERROR_MSG);
        $lc2Action->setErrorMessage($api->errorMessage());
    }

    if ($failed) {
        if ($admission && $admissionCreated) {
            try {
                $api->admission_delete($admission->getId());
            } catch (Exception $e) {
                // Ignore errors
            }
        }
        if ($isNewCase) {
            try {
                $api->case_delete($caseId, "DELETE");
            } catch (Exception $e) {
                // Ignore errors
            }
        }
    } else {
        if (!$GLOBALS["KIT_INFO_MGR"]) {
            // If no url has been provided to access the service that manages del KIT INFO database, then we assume that we have local access to DB
            $kitInfo->changeStatus(KitInfo::STATUS_ASSIGNED);
        } else {
            updateKitStatusRemote($kitInfo->getId(), KitInfo::STATUS_ASSIGNED);
        }
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

    $filter = new TaskFilter();
    $filter->setObjectType('TASKS');
    $taskCodes = [$GLOBALS["TASK_CODES"]["KIT_RESULTS"], $GLOBALS["TASK_CODES"]["REGISTER_KIT"]];
    $filter->setTaskCodes(implode(',', $taskCodes));
    $tasks = $api->admission_get_task_list($admission->getId(), null, null, $filter);
    $kitResultsTask = null;
    $registerKitTask = null;
    foreach ($tasks as $t) {
        if ($t->getTaskCode() == $GLOBALS["TASK_CODES"]["KIT_RESULTS"]) {
            // There exists an open REGISTER KIT TASK
            $kitResultsTask = $t;
        }
        if ($t->getTaskCode() == $GLOBALS["TASK_CODES"]["REGISTER_KIT"]) {
            // There exists an open REGISTER KIT TASK
            $registerKitTask = $t;
        }
    }

    if (!$kitResultsTask && $registerKitTask) {
        // If KIT_RESULTS TASK does not exist, then redirect to the first open FORM of REGISTER_KIT
        $forms = $api->task_activity_list($registerKitTask->getId());
        $lastForm = null;
        foreach ($forms as $form) {
            $lastForm = $form;
            if ($form->getStatus() == "OPEN") {
                // An OPEN FORM was found
                break;
            }
        }
        $resultTaskIsMultiform = (count($forms) > 1);
    }

    if ($kitResultsTask && !$kitResultsTask->getLocked()) {
        // If the TASK is not locked, store the KIT ID in an ITEM of KIT_RESULTS TASK
        $forms = $api->task_activity_list($kitResultsTask->getId());
        $targetForm = null;
        foreach ($forms as $form) {
            if ($form->getFormCode() == $GLOBALS["FORM_CODES"]["KIT_RESULTS"]) {
                // The KIT_INFO FORM was found => update the questions with Kit Information
                $targetForm = $form;
                break;
            }
        }

        if ($targetForm) {
            $api->form_set_answer($targetForm->getId(), $GLOBALS["KIT_RESULTS_Q_ID"]["KIT_ID"], $kitInfo->getId());
        }
    }

    // Create a new "SCAN KIT" TASK
    createScanKitTask($admission->getId());
    if ($kitResultsTask) {
        $lc2Action->setActionType(LC2Action::ACTION_REDIRECT_TO_TASK);
        $lc2Action->setTaskId($kitResultsTask->getId());
    } elseif ($registerKitTask && $lastForm) {
        $lc2Action->setTaskId($registerKitTask->getId());
        if ($resultTaskIsMultiform) {
            $lc2Action->setActionType(LC2Action::ACTION_REDIRECT_TO_FORM);
            $lc2Action->setFormId($lastForm->getId());
        } else {
            $lc2Action->setActionType(LC2Action::ACTION_REDIRECT_TO_TASK);
        }
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
    $task = $api->task_get($taskId);

    // TASK inserted. Now update the questions with the Kit Information
    $forms = $api->task_activity_list($taskId);
    $targetForm = null;
    foreach ($forms as $form) {
        if ($form->getFormCode() == $GLOBALS["FORM_CODES"]["KIT_INFO"]) {
            // The KIT_INFO FORM was found => update the questions with Kit Information
            $targetForm = $api->form_get_summary($form->getId(), true, false);
            break;
        }
    }

    $task->clearAssignments();
    $a = new APITaskAssignment(APITaskAssignment::SERVICE, null, null);
    $task->addAssignments($a);
    try {
        $api->task_set($task);
    } catch (Exception $e) {
        // Ignore error
    }

    $arrQuestions = [];
    if ($targetForm) {
        if ($q = $targetForm->findQuestion($GLOBALS["KIT_INFO_Q_ID"]["KIT_ID"])) {
            $q->setValue($kitInfo->getId());
            $arrQuestions[] = $q;
        }
        if ($q = $targetForm->findQuestion($GLOBALS["KIT_INFO_Q_ID"]["MANUFACTURER_NAME"])) {
            $q->setValue($kitInfo->getManufacturerName());
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
    } else {
        throw new APIException("FORM NOT FOUND", "KIT INFO FORM NOT FOUND: (" . $GLOBALS["FORM_CODES"]["KIT_INFO"] . ")");
    }

    return $taskId;
}

/**
 * DEPRECATED!!! Now the prescription INFO is passed as 'setup_values' to admission_create()<br>
 *
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
    $task = $api->task_get($taskId);

    // TASK inserted. Now update the questions with the prescription Information
    $forms = $api->task_activity_list($taskId);
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
    } else {
        throw new APIException("FORM NOT FOUND", "PRESCRIPTION INFO FORM NOT FOUND: (" . $GLOBALS["FORM_CODES"]["PRESCRIPTION_INFO"] . ")");
    }

    $task->clearAssignments();
    $a = new APITaskAssignment(APITaskAssignment::SERVICE, null, null);
    $task->addAssignments($a);
    try {
        $api->task_set($task);
    } catch (Exception $e) {
        // Ignore error
    }

    return $taskId;
}

/**
 * Inserts a new "REGISTER_KIT" TASK in the ADMISSION
 * Returns an array with 2 elements:
 * 1- The ID of the inserted TASK
 * 2- The ID of the first open FORM (the TASK contains more FORMs, and we want to redirect to this specific FORM)
 *
 * @param string $admissionId
 * @throws APIException
 * @return string[]
 */
function createRegisterKitTask($admissionId) {
    $api = LinkcareSoapAPI::getInstance();
    $taskId = $api->task_insert_by_task_code($admissionId, $GLOBALS["TASK_CODES"]["REGISTER_KIT"]);

    if ($api->getSession()->getRoleId() != APITaskAssignment::PATIENT) {
        /*
         * The active user is a PROFESSIONAL ==> assign the inserted TASK to CASE.
         * We need to do this because the REGISTER_KIT task is assigned by default to PATIENT, because it is necessary to support auto-administered
         * ADMISSIONs (managed by the PATIENT)
         * If we had assigned the TASK by default to a CASE MANAGER, the patient would not be able to change the assignment to CASE because task_set()
         * needs professional privileges to execute
         *
         */
        $task = $api->task_get($taskId);
        $task->clearAssignments();
        $a = new APITaskAssignment(APITaskAssignment::CASE_MANAGER, null, null);
        $task->addAssignments($a);
        try {
            $api->task_set($task);
        } catch (Exception $e) {
            // Ignore error
        }
    }

    // TASK inserted. Now update the questions with the Kit Information
    $forms = $api->task_activity_list($taskId);

    if (count($forms) > 1) {
        /* @var APIForm $targetForm */
        $targetForm = null;
        foreach ($forms as $form) {
            // find the first open FORM of the TASK or the last FORM if all are closed
            $targetForm = $form;
            if ($form->getStatus() == "OPEN") {
                break;
            }
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

    return $taskId;
}

/**
 * Sends a SOAP request to the remote service that manages the Kits to change the status of a Kit
 *
 * @param string $kitId
 * @param string $status
 */
function updateKitStatusRemote($kitId, $status) {
    $endpoint = $GLOBALS["KIT_INFO_MGR"];
    $uri = parse_url($endpoint)['scheme'] . '://' . parse_url($endpoint)['host'] . ":" . parse_url($endpoint)['port'];

    $client = new SoapClient(null, ['location' => $endpoint, 'uri' => $uri, "connection_timeout" => 10]);
    try {
        $client->update_kit_status($kitId, $status);
    } catch (SoapFault $fault) {
        service_log("ERROR: SOAP Fault: (faultcode: {$fault->faultcode}, faultstring: {$fault->faultstring})");
    }
}
