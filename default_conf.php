<?php
session_start();

require_once ("utils.php");
require_once ("WSAPI/WSAPI.php");
require_once "classes/Database.Class.php";
require_once "classes/class.DbManagerOracle.php";
require_once "classes/class.DbManagerResultsOracle.php";
require_once "classes/KitException.php";
require_once 'view_models/KitInfo.php';
require_once 'view_models/ErrorInfo.php';
require_once "classes/Localization.php";
require_once "classes/LC2Action.php";
require_once "classes/Prescription.php";

$GLOBALS['COMMIT'] = true;
$GLOBALS["DBConnection_URI"] = "oci://covid_kits:xxxxxx@dbproduction.linkcareapp.com:1521/linkcare";
$GLOBALS["CLOSE_URL"] = "https://seroscreening.com";

$GLOBALS["LANG"] = "EN";
$GLOBALS["DEFAULT_TIMEZONE"] = "Europe/Madrid";

// Url of the WS-API where the ADMISSIONs will be created
$GLOBALS["WS_LINK"] = "https://test-api.linkcareapp.com/ServerWSDL.php";

/*
 * Url of the external service that manages the KIT Information. Used to update the Kit Status. If not provided then it is assumed that we have local
 * access to the DB and it is not necessary to invoke a remote service to change the status of a kit
 */
$GLOBALS["KIT_INFO_MGR"] = "https://test-api.linkcareapp.com/";

// The QR code of prescriptions has a check digit at the begining of the string?
$GLOBALS['QR_WITH_CHECK_DIGIT'] = true;

$GLOBALS["PROGRAM_CODE"] = "SEROSCREENING";
$GLOBALS["TASK_CODES"]["KIT_INFO"] = "KIT_INFO";
$GLOBALS["TASK_CODES"]["PRESCRIPTION_INFO"] = "PRESCRIPTION_INFO";
$GLOBALS["TASK_CODES"]["REGISTER_KIT"] = "REGISTER_KIT";
$GLOBALS["TASK_CODES"]["SCAN_KIT"] = "SCAN_KIT";
$GLOBALS["TASK_CODES"]["KIT_RESULTS"] = "KIT_RESULTS";

$GLOBALS["FORM_CODES"]["KIT_INFO"] = "KIT_INFO_FORM";
$GLOBALS["FORM_CODES"]["REGISTER_KIT"] = "KIT_CHECKLIST";
$GLOBALS["FORM_CODES"]["PRESCRIPTION_INFO"] = "PRESCRIPTION_INFO_FORM";
$GLOBALS["FORM_CODES"]["KIT_RESULTS"] = "KIT_INFO_DATA2";

// ID of the KIT_INFO FORM questions
$GLOBALS["KIT_INFO_Q_ID"]["KIT_ID"] = 1;
$GLOBALS["KIT_INFO_Q_ID"]["MANUFACTURE_PLACE"] = 2;
$GLOBALS["KIT_INFO_Q_ID"]["MANUFACTURE_DATE"] = 3;
$GLOBALS["KIT_INFO_Q_ID"]["MANUFACTURE_TIME"] = 4;
$GLOBALS["KIT_INFO_Q_ID"]["EXPIRATION_DATE"] = 5;
$GLOBALS["KIT_INFO_Q_ID"]["BATCH_NUMBER"] = 6;

$GLOBALS["PRESCRIPTION_INFO_Q_ID"]["PRESCRIPTION_ID"] = 1;
$GLOBALS["PRESCRIPTION_INFO_Q_ID"]["PRESCRIPION_EXPIRATION"] = 2;
$GLOBALS["PRESCRIPTION_INFO_Q_ID"]["ROUNDS"] = 3;

$GLOBALS["KIT_RESULTS_Q_ID"]["KIT_ID"] = 12;

// ID of the REGISTER_KIT FORM questions
$GLOBALS["REGISTER_KIT_Q_ID"]["REGISTER_STATUS"] = 1;

$GLOBALS["DEBUG_MODE"] = false;

// Load particular configuration
if (file_exists('conf/configuration.php')) {
    include_once 'conf/configuration.php';
}

date_default_timezone_set($GLOBALS["DEFAULT_TIMEZONE"]);

