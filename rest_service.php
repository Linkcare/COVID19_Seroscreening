<?php

// Link the config params
require_once ("lib/default_conf.php");

setSystemTimeZone();

$kitInfo = new KitInfo();

error_reporting(0);
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $kitInfo->setId(urldecode($_POST["kit_id"]));
        $kitInfo->setBatch_number(urldecode($_POST["batch_number"]));
        $kitInfo->setManufacturerName(urldecode($_POST["manufacturer"]));
        $kitInfo->setManufacture_place(urldecode($_POST["manufacture_place"]));
        $kitInfo->setManufacture_date(urldecode($_POST["manufacture_date"]));
        $kitInfo->setExp_date(urldecode($_POST["expiration_date"]));
        $kitInfo->setProgramCode(urldecode($_POST["program"]));
        $kitInfo->setPrescriptionString(urldecode($_POST["prescription"]));
        $kitInfo->setParticipantRef(urldecode($_POST["participant"]));
        $subscriptionId = urldecode($_POST["subscription"]);
        $lc2Action = service_dispatch_kit($_POST["token"], $kitInfo, $subscriptionId);
    } catch (APIException $e) {
        log_trace("ERROR: " . $e->getMessage());
        $lc2Action = new LC2Action(LC2Action::ACTION_ERROR_MSG);
        $lc2Action->setErrorMessage($e->getMessage());
    } catch (Exception $e) {
        log_trace("ERROR: " . $e->getMessage());
        $lc2Action = new LC2Action(LC2Action::ACTION_ERROR_MSG);
        $lc2Action->setErrorMessage($e->getMessage());
        service_log("ERROR: " . $e->getMessage());
    }

    header('Content-type: application/json');
    echo $lc2Action->toString();
}
