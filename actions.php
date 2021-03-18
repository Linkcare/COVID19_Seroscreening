<?php
// POST functions
require_once 'default_conf.php';

if ($_POST['action'] == 'check_test_results') {
    // Gatekeeper action
    if (isset($_POST['id'])) {
        $res = checkTestResults($_POST['id']);
        header('Content-Type: application/json');
        echo json_encode($res);
    }
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
        switch ($_POST['action']) {
            case 'process_kit' :
                if ($kit->getStatus() == KitInfo::STATUS_NOT_USED) {
                    /* The Kit status is: not used */
                    header('Content-Type: application/text');
                    if (isset($_POST['lang'])) {
                        $language = $_POST['lang'];
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
                if (isset($_POST['prescription'])) {
                    $prescriptionStr = $_POST['prescription'];
                    $kit->storeTracking(KitInfo::ACTION_PROCESSED, $prescriptionStr);
                    $targetUrl = $kit->generateURLtoLC2() . "&prescription=" . urlencode($prescriptionStr);
                    header('Content-Type: application/text');
                    echo $targetUrl;
                } elseif (isset($_POST['participant'])) {
                    $participantRef = $_POST['participant'];
                    $kit->storeTracking(KitInfo::ACTION_PROCESSED, $participantRef);
                    $targetUrl = $kit->generateURLtoLC2();
                    header('Content-Type: application/text');
                    echo $targetUrl;
                }
                break;
            case 'set_prescription' :
                // PEDRO REMOVE
                $prescription = new Prescription($_POST["prescription"], $_POST["participant"]);
                header('Content-Type: application/json');
                echo $prescription->toJSON();
                break;
        }
    }
}
?>
