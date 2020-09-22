<?php
require_once 'default_conf.php';

if (isset($_SESSION["KIT"])) {
    /* @var KitInfo $kit */
    $kit = unserialize($_SESSION["KIT"]);
} else {
    header("Location: /index.php");
    die();
}

/* Initialize the connection to the DB */
$dbConnResult = Database::init($GLOBALS["DBConnection_URI"]);

if ($dbConnResult !== true) {
    $err = new ErrorInfo(ErrorInfo::DB_CONNECTION_ERROR, $dbConnResult);
    $GLOBALS["VIEW_MODEL"] = $err;
    include "views/Header.html.php";
    include "views/ErrorInfo.html.php";
} else {
    /* Initialize user language */
    setLanguage();

    include "views/Header.html.php";
    include "views/Prescription.html.php";
}
?>