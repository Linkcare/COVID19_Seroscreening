<?php
require_once 'default_conf.php';

$_SESSION["KIT"] = NULL;

/* Initialize the connection to the DB */
$dbConnResult = Database::init($GLOBALS["DBConnection_URI"]);

if ($dbConnResult !== true) {
    $err = new ErrorInfo(ErrorInfo::DB_CONNECTION_ERROR, $dbConnResult);
    openErrorInfoView($err);
} else {

    /* Initialize user language */
    setLanguage();

    if ($kit = KitInfo::getInstance($_GET['id'])) {
        /* If the kit has been called to be discarded, we set and update its values before showing, if not, the kit will remain the same */
        if (isset($_GET['discard'])) {
            // Disable the functionality at the moment
            // $kit->changeStatus(KitInfo::STATUS_DISCARDED);
        }

        switch ($kit->getStatus()) {
            case '' :
                $kit->setStatus(KitInfo::STATUS_NOT_USED);
            /* If the status is empty, the kit is valid as if its status is equal to NOT_USED */
            case KitInfo::STATUS_NOT_USED :
                if (strtotime($kit->getExp_date()) - strtotime($kit->getManufacture_date()) < 0) {
                    /* The kit is not valid, athough it's not used, it has expired */
                    $kit->changeStatus(KitInfo::STATUS_EXPIRED);
                } else {
                    $kit->generateURLtoLC2();
                }
                openKitInfoView($kit);
                break;
            case KitInfo::STATUS_EXPIRED :
            /* The EXPIRED and DISCARDED status are equal */
            case KitInfo::STATUS_DISCARDED :
                /* The kit has been discarded, we'll only see its data */
                openKitInfoView($kit);
                break;
            case KitInfo::STATUS_ASSIGNED :
            /* The ASSIGNED and USED status are equal */
            case KitInfo::STATUS_USED :
                $kit->generateURLtoLC2();
                openKitInfoView($kit);
                break;
            default:
                /* The kit contains an invalid status value */
                $err = new ErrorInfo(ErrorInfo::INVALID_STATUS);
                openErrorInfoView($err);
                break;
        }
    } else {
        /* The kit doesn't exist or there was no id specified at the URL */
        $err = new ErrorInfo(ErrorInfo::INVALID_KIT);
        openErrorInfoView($err);
    }
}

/**
 * Opens the KitInfo view page
 *
 * @param KitInfo $kit
 */
function openKitInfoView($kit) {
    $GLOBALS["VIEW_MODEL"] = $kit;
    include "views/Header.html.php";
    include "views/KitInfo.html.php";
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
