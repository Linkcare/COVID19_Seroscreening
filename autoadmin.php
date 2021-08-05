<?php
require_once 'lib/default_conf.php';

/*
 * Web page for autoadministered kits. The URL must include the following parameters:
 * - id: Kit ID of the COVID test device
 * - One of the following:
 * -- app: application name (e.g. "KX" for CaixaBank. This value is used to retrieve the shared key of the SUBSCRITION for creating new ADMISSIONS
 * -- sk: if the parameter "app" is not provided, then the shared key must be explicitly defined
 *
 * Examples:
 * https://domainname?id=-1A01&app=KX
 * https://domainname?id=-1A01&sk=1CA9ma3YiiKYdy5NOwAlsA6cET8L0rSF1uIoRv9CA0Hq3leN2PoRpA,,
 */

$_SESSION["KIT"] = NULL;

/* Initialize the connection to the DB */
$dbConnResult = Database::init($GLOBALS["DBConnection_URI"]);

if ($dbConnResult !== true) {
    $err = new ErrorInfo(ErrorInfo::DB_CONNECTION_ERROR, $dbConnResult);
    openErrorInfoView($err);
} else {

    /* Initialize user language */
    setLanguage();
    $kitId = $_GET['id'];
    $sharedKey = null;
    $kit = KitInfo::getInstance($kitId);
    if ($app = $_GET['app']) {
        // An specific application is defined in the url parameters. Try to obtain the SharedKey of the subscripcion from the configuration
        $sharedKey = $GLOBALS['APP_SUBSCRIPTION_SHARED_KEYS'][$app];
    }
    if (!$sharedKey) {
        $sharedKey = $_GET['sk'];
    }
    $signUpUrl = null;

    if (!$sharedKey) {
        // ERROR: We need a shared key to start a new ADMISSION for an autoadministered kit
        $err = new ErrorInfo(ErrorInfo::SUBSCRIPTION_NOT_FOUND);
        openErrorInfoView($err);
        return;
    }

    if ($kit) {
        /* If the kit has been called to be discarded, we set and update its values before showing, if not, the kit will remain the same */
        if (isset($_GET['discard'])) {
            // Disable the functionality at the moment
            // $kit->changeStatus(KitInfo::STATUS_DISCARDED);
        }

        /* Store a tracking of all kits scanned */
        $kit->storeTracking(KitInfo::ACTION_SCANNED, '');
        switch ($kit->getStatus()) {
            case '' :
                $kit->setStatus(KitInfo::STATUS_NOT_USED);
            /* If the status is empty, the kit is valid as if its status is equal to NOT_USED */
            case KitInfo::STATUS_NOT_USED :
                if (strtotime($kit->getExp_date()) - strtotime($kit->getManufacture_date()) < 0) {
                    /* The kit is not valid, athough it's not used, it has expired */
                    $kit->changeStatus(KitInfo::STATUS_EXPIRED);
                    $err = new ErrorInfo(ErrorInfo::KIT_EXPIRED);
                    openErrorInfoView($err);
                    return;
                } else {
                    $resp = generateSignUpUrl($sharedKey, $kit);
                    if (!$resp['error']) {
                        $signUpUrl = $resp['url'];
                    }
                }
                break;
            case KitInfo::STATUS_EXPIRED :
            case KitInfo::STATUS_DISCARDED :
            case KitInfo::STATUS_ASSIGNED :
            case KitInfo::STATUS_PROCESSING :
            case KitInfo::STATUS_PROCESSING_5MIN :
            case KitInfo::STATUS_INSERT_RESULTS :
            case KitInfo::STATUS_USED :
                break;
            default:
                /* The kit contains an invalid status value */
                $err = new ErrorInfo(ErrorInfo::INVALID_STATUS);
                openErrorInfoView($err);
                return;
        }
    } else {
        /* The kit doesn't exist or there was no id specified at the URL */
        if (trim($kitId)) {
            $kit = new KitInfo();
            $kit->setId($kitId);
            $kit->setStatus(KitInfo::STATUS_INVALID);
            $kit->storeTracking(KitInfo::ACTION_SCANNED, '');
        }
        $err = new ErrorInfo(ErrorInfo::INVALID_KIT);
        openErrorInfoView($err);
        return;
    }
    if ($signUpUrl) {
        include "views/Header.html.php";
        $GLOBALS['URL_START_AUTOADMINISTERED'] = $signUpUrl;
        include "views/caixabank_autoadmin.html.php";
    }
}

/**
 * Opens the ErrorInfo view page
 *
 * @param ErrorInfo $err
 */
function openErrorInfoView($err) {
    $GLOBALS["VIEW_MODEL"] = $err;
    include "views/Header.html.php";
    include "views/ErrorInfo.html.php";
}

?>
