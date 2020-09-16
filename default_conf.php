<?php
$GLOBALS['COMMIT'] = true;
$GLOBALS["DBConnection_URI"] = "oci://covid_kits:xxxxxx@dbproduction.linkcareapp.com:1521/linkcare";

$GLOBALS["LANG"] = "EN";
$GLOBALS["DEFAULT_TIMEZONE"] = "Europe/Madrid";
$GLOBALS["WS_LINK"] = "https://test-api.linkcareapp.com";
// $GLOBALS["WS_LINK"] = "https://dev-api.linkcareapp.com";
// $GLOBALS["WS_LINK"] = "http://localhost:8888";

$GLOBALS["PROGRAM_CODE"] = "SEROSCREENING";
$GLOBALS["TASK_CODES"]["KIT_INFO"] = "KIT_INFO";
$GLOBALS["TASK_CODES"]["REGISTER_KIT"] = "REGISTER_KIT";
$GLOBALS["FORM_CODES"]["KIT_INFO"] = "KIT_INFO_FORM";
$GLOBALS["FORM_CODES"]["REGISTER_KIT"] = "KIT_CHECKLIST";

// ID of the KIT_INFO FORM questions
$GLOBALS["KIT_INFO_Q_ID"]["KIT_ID"] = 1;
$GLOBALS["KIT_INFO_Q_ID"]["MANUFACTURE_PLACE"] = 2;
$GLOBALS["KIT_INFO_Q_ID"]["MANUFACTURE_DATE"] = 3;
$GLOBALS["KIT_INFO_Q_ID"]["MANUFACTURE_TIME"] = 4;
$GLOBALS["KIT_INFO_Q_ID"]["EXPIRATION_DATE"] = 5;
$GLOBALS["KIT_INFO_Q_ID"]["BATCH_NUMBER"] = 6;

// ID of the REGISTER_KIT FORM questions
$GLOBALS["REGISTER_KIT_Q_ID"]["REGISTER_STATUS"] = 1;

$GLOBALS["DEBUG_MODE"] = false;

// Load particular configuration
if (file_exists('conf/configuration.php')) {
    include_once 'conf/configuration.php';
}

date_default_timezone_set($GLOBALS["DEFAULT_TIMEZONE"]);

