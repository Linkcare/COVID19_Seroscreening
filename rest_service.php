<?php

// Link the config params
require_once ("default_conf.php");

setSystemTimeZone();

$kitInfo = new KitInfo();

error_reporting(0);
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $kitInfo->setId($_POST["kit_id"]);
    $kitInfo->setBatch_number($_POST["batch_number"]);
    $kitInfo->setManufacture_place($_POST["manufacture_place"]);
    $kitInfo->setManufacture_date($_POST["manufacture_date"]);
    $kitInfo->setExp_date($_POST["expiration_date"]);
    $kitInfo->setProgramCode($_POST["program"]);
    $kitInfo->setPrescriptionString(urldecode($_POST["prescription"]));
    $kitInfo->setParticipantRef(urldecode($_POST["participant"]));
    $subscriptionId = urldecode($_POST["subscription"]);
    header('Content-type: application/json');
    echo service_dispatch_kit($_POST["token"], $kitInfo, $subscriptionId);
}
