<?php
require_once ("APIException.php");
require_once ("LinkcareSoapAPI.php");
require_once ("APIResponse.php");
// require_once ("objects/APICase.php");

$requires = glob(__DIR__ . '/objects/*.php');
foreach ($requires as $filename) {
    require_once ($filename);
}
