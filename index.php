<?php
require_once 'default_conf.php';
require_once "classes/Database.Class.php";
require_once "classes/class.DbManagerOracle.php";
require_once "classes/class.DbManagerResultsOracle.php";
require_once "classes/Localization.php";
require_once 'view_models/ErrorInfo.php';
require_once 'view_models/KitInfo.php';

$_SESSION["KIT"] = NULL;

/* Initialize the connection to the DB */
$dbConnResult = Database::init($GLOBALS["DBConnection_URI"]);

if ($dbConnResult !== true) {
    $err = new ErrorInfo(ErrorInfo::DB_CONNECTION_ERROR, $dbConnResult);
    openErrorInfoView($err);
} else {

    /* Initialize user language */
    setLanguage();

    if ($kit = getKitData()) {
        /* If the kit has been called to be discarded, we set and update its values before showing, if not, the kit will remain the same */
        discardKit($kit);

        switch ($kit->getStatus()) {
            case '' :
                $kit->setStatus(KitInfo::STATUS_NOT_USED);
            /* If the status is empty, the kit is valid as if its status is equal to NOT_USED */
            case KitInfo::STATUS_NOT_USED :
                if (strtotime($kit->getExp_date()) - strtotime($kit->getManufacture_date()) < 0) {
                    /* The kit is not valid, athough it's not used, it has expired */
                    kitExpired($kit);
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
 * Set the language of the website
 */
function setLanguage() {
    /* Initialize user language */
    if (!($lang = $_GET['culture'])) {
        $lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
    }
    Localization::init($lang);
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

/**
 * Returns the corresponding kit based in the id that is obtained from the url
 *
 * @return KitInfo object with the data from the database of said kit, or null if the kit doesn't exist or there was no id specified at the URL
 */
function getKitData() {
    $kit = null;

    if (!empty($_GET['id'])) {
        /* Obtain the id as a parameter and its corresponding info from the DB */
        $id = [':id' => $_GET['id']];

        $sql = "SELECT
                    ki.KIT_ID,
                    ki.MANUFACTURE_PLACE,
                    ki.MANUFACTURE_DATE,
                    ki.EXPIRATION,
                    ki.BATCH_NUMBER,
                    ki.STATUS,
                    li.URL
                FROM
                    KIT_INFO ki
                LEFT JOIN LC_INSTANCES li ON ki.ID_INSTANCE = li.ID_INSTANCE
                WHERE ki.KIT_ID = :id";

        $result = Database::getInstance()->ExecuteBindQuery($sql, $id);

        if ($result->Next()) {
            $kit = new KitInfo();
            $kit->setId($result->GetField('KIT_ID'));
            $kit->setManufacture_place($result->GetField('MANUFACTURE_PLACE'));
            $kit->setManufacture_date(substr($result->GetField('MANUFACTURE_DATE'), 0, 16));
            $kit->setBatch_number($result->GetField('BATCH_NUMBER'));
            $kit->setExp_date(substr($result->GetField('EXPIRATION'), 0, 10));
            $kit->setStatus($result->GetField("STATUS"));
            $kit->setInstance_url($result->GetField('URL'));
        }
    }
    return $kit;
}

/**
 * The function will change the kit status and update its DB registry to DISCARDED if it has been called to be discarded.
 * If not, it will keep the Kit object as it was.
 *
 * @param KitInfo $kit Kit to update
 */
function discardKit(&$kit) {
    if (isset($_GET['discard'])) {
        $kit->setStatus(KitInfo::STATUS_DISCARDED);
        updateStatus($kit);
    }
}

/**
 * Function to update locally and at the DB a kit's status to EXPIRED
 *
 * @param KitInfo $kit Kit to update
 */
function kitExpired(&$kit) {
    $kit->setStatus(KitInfo::STATUS_EXPIRED);
    updateStatus($kit);
}

/**
 * Updates the status of a kit at the database
 *
 * @param KitInfo $kit Kit to be updated
 */
function updateStatus($kit) {
    $arrVariables[":status"] = $kit->getStatus();
    $arrVariables[":id"] = $kit->getId();
    $sql = "UPDATE KIT_INFO SET STATUS = :status WHERE KIT_ID = :id";
    Database::getInstance()->ExecuteBindQuery($sql, $arrVariables);
}

?>
