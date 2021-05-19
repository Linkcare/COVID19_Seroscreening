<?php
ini_set("soap.wsdl_cache_enabled", 0);

// Link the config params
require_once ("lib/default_conf.php");

setSystemTimeZone();

error_reporting(0);
try {
    $server = new SoapServer("soap_service.wsdl");
    $server->addFunction("update_kit_status");
    $server->handle();
} catch (Exception $e) {
    service_log($e->getMessage());
}

/**
 * ******************************** SOAP FUNCTIONS *********************************
 */
/**
 *
 * @param int $kit_id
 * @param int $status
 * @return void|string[]|unknown[]|string[]
 */
function update_kit_status($kit_id, $status) {
    $errorMsg = null;
    try {
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
            $kitInfo->changeStatus($status);
        }
        $errorMsg = $err->getErrorMessage();
    } catch (APIException $e) {
        $errorMsg = $e->getMessage();
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
    }

    $result = $errorMsg ? 0 : 1;
    return ['result' => $result, 'ErrorMsg' => $errorMsg];
}
