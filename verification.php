<?php
require_once 'lib/default_conf.php';

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
    include "views/Verification.html.php";
}
?>