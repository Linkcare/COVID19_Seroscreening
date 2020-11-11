<?php
require_once 'default_conf.php';

/* Initialize the connection to the DB */
$dbConnResult = Database::init($GLOBALS["DBConnection_URI"]);

if ($dbConnResult !== true) {
    $err = new ErrorInfo(ErrorInfo::DB_CONNECTION_ERROR, $dbConnResult);
    $GLOBALS["VIEW_MODEL"] = $err;
    include "views/Header.html.php";
    include "views/ErrorInfo.html.php";
} else {

    // Obtain the return page to go back where we were previously to the error
    if (isset($_GET['return'])) {
        $return = $_GET['return'];
    }

    // In case the error was PRESCRIPTION_WRONG_FORMAT
    if (isset($_GET['error']) && $_GET['error'] == "PRESCRIPTION_WRONG_FORMAT") {
        $GLOBALS["VIEW_MODEL"] = new ErrorInfo(ErrorInfo::PRESCRIPTION_WRONG_FORMAT);
    } else {
        // Else go back to the index since this page cannot be accessed from scratch (it will show an invalid kit error
        header("Location: /index.php");
        die();
    }

    /* Initialize user language */
    setLanguage();

    include "views/Header.html.php";
    include "views/ErrorInfo.html.php";
}
?>
