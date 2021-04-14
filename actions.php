<?php
require_once 'lib/default_conf.php';
require_once 'lib/gatekeeper_functions.php';

if ($_GET['action'] == 'check_test_results') {
    // Gatekeeper action
    if (isset($_GET['qr'])) {
        $prescription = new Prescription($_GET['qr']);
    } else {
        $prescription = new Prescription(null, $_GET['participant_id']);
        $prescription->setId($_GET['prescription_id']);
        $prescription->setProgram($_GET['program']);
        $prescription->setTeam($_GET['team']);
    }
    $testInfo = checkTestResults($prescription);

    header('Content-Type: application/json');
    // Pass only the necessary information as response to the request
    $res = new stdClass();
    $res->result = $testInfo->result;
    $res->date = $testInfo->date;
    $res->expiration = $testInfo->expiration;
    $res->error = $testInfo->error;
    echo json_encode($res);

    try {
        $dbConnResult = Database::init($GLOBALS["DBConnection_URI"]);

        if ($dbConnResult === true) {
            storeGatekeeperTracking($res, json_encode($_GET));
        }
    } catch (Exception $e) {}
} else {
    // LC2 actions
    if (isset($_SESSION["KIT"])) {
        /* @var KitInfo $kit */
        $kit = unserialize($_SESSION["KIT"]);
    } else {
        die();
    }

    /* Initialize the connection to the DB */
    $dbConnResult = Database::init($GLOBALS["DBConnection_URI"]);

    if ($dbConnResult === true) {
        // A POST request has been received informing about a scanned Prescription
        switch ($_GET['action']) {
            case 'process_kit' :
                if ($kit->getStatus() == KitInfo::STATUS_NOT_USED) {
                    /* The Kit status is: not used */
                    header('Content-Type: application/text');
                    if (isset($_GET['lang'])) {
                        $language = $_GET['lang'];
                    } else {
                        $language = Localization::getLang();
                    }
                    if ($kit->getPrescriptionString() == '') {
                        // Show the next view asking for the prescription information
                        echo 'prescription.php?culture=' . $language;
                    } else {
                        // We already know the PRESCRIPTION information. There is no need to ask for it.
                        echo $kit->generateURLtoLC2() . "&prescription=" . urlencode($kit->getPrescriptionString());
                    }
                } else if (in_array($kit->getStatus(),
                        [KitInfo::STATUS_ASSIGNED, KitInfo::STATUS_PROCESSING, KitInfo::STATUS_PROCESSING_5MIN, KitInfo::STATUS_INSERT_RESULTS])) {
                    $kit->storeTracking(KitInfo::ACTION_PROCESSED, '');
                    header('Content-Type: application/text');
                    $url = $kit->generateURLtoLC2();
                    if ($kit->getPrescriptionString()) {
                        $url .= "&prescription=" . urlencode($kit->getPrescriptionString());
                    }
                    echo $url;
                }
                break;
            case 'create_admission' :
                if (isset($_GET['prescription'])) {
                    $prescriptionStr = $_GET['prescription'];
                    $kit->storeTracking(KitInfo::ACTION_PROCESSED, $prescriptionStr);
                    $targetUrl = $kit->generateURLtoLC2() . "&prescription=" . urlencode($prescriptionStr);
                    header('Content-Type: application/text');
                    echo $targetUrl;
                } elseif (isset($_GET['participant'])) {
                    $participantRef = $_GET['participant'];
                    $kit->storeTracking(KitInfo::ACTION_PROCESSED, $participantRef);
                    $targetUrl = $kit->generateURLtoLC2();
                    header('Content-Type: application/text');
                    echo $targetUrl;
                }
                break;
            case 'set_prescription' :
                // PEDRO REMOVE
                $prescription = new Prescription($_GET["prescription"], $_GET["participant"]);
                header('Content-Type: application/json');
                echo $prescription->toJSON();
                break;
        }
    }
}

