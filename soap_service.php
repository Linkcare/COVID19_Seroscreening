<?php
ini_set("soap.wsdl_cache_enabled", 0);

// Link the config params
require_once ("default_conf.php");
require_once ("utils.php");
require_once "classes/Database.Class.php";
require_once "classes/class.DbManagerOracle.php";
require_once "classes/class.DbManagerResultsOracle.php";
require_once "classes/Localization.php";
require_once 'view_models/ErrorInfo.php';
require_once 'view_models/KitInfo.php';

setSystemTimeZone();

/**
 *
 * @param int $kit_id
 * @param int $status
 * @return void|string[]|unknown[]|string[]
 */
function update_kit_status($kit_id, $status) {
    $err = new ErrorInfo();
    $dbConnResult = Database::init($GLOBALS["DBConnection_URI"]);
    if ($dbConnResult !== true) {
        $err = new ErrorInfo(ErrorInfo::DB_CONNECTION_ERROR);
        service_log($err->getErrorMessage());
    }
    if (!$err->getErrorCode()) {
        $kitInfo = KitInfo::getInstance($kit_id);
        if (!$kitInfo) {
            $err = new ErrorInfo(ErrorInfo::INVALID_KIT);
            service_log($err->getErrorMessage());
        }
    }

    if (!$err->getErrorCode()) {
        $kitInfo->setStatus($status);
    }

    return ["result" => $err->getErrorCode() ? "KO" : "OK", "ErrorMsg" => $err->getErrorMessage()];
}

error_reporting(0);
try {
    $server = new SoapServer("soap_service.wsdl");
    $server->addFunction("update_kit_status");
    $server->handle();
} catch (Exception $e) {
    service_log($e->getMessage());
}
