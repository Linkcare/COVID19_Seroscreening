<?php
require_once "lib/classes/Database.Class.php";
require_once "lib/classes/class.DbManagerOracle.php";
require_once "lib/classes/class.DbManagerResultsOracle.php";

$GLOBALS['COMMIT'] = true;

Database::init("oci://covid_kits:C0v1dK1ts@dbproduction.linkcareapp.com:1521/linkcare");

$arrVariables[':manufacturePlace'] = 'Nantong - RP China';
$arrVariables[':manufactureDate'] = '2021-04-22';
$arrVariables[':expirationDate'] = '2023-04-21';
$arrVariables[':batchNumber'] = '20210422';
$arrVariables[':instanceId'] = 'COVID19';
$arrVariables[':programCode'] = 'COVID19_AG';
$sql = 'INSERT INTO KIT_INFO (KIT_ID, MANUFACTURE_PLACE, MANUFACTURE_DATE, EXPIRATION, BATCH_NUMBER, STATUS, ID_INSTANCE, PROGRAM_CODE) VALUES(:kitId, :manufacturePlace, :manufactureDate, :expirationDate, :batchNumber, NULL, :instanceId, :programCode)';

$db = Database::getInstance();
$srcFileName = 'Total.txt';
$f = fopen($srcFileName, 'r');
if (!$f) {
    echo "ERROR OPENING FILE $srcFileName<br>";
    exit(1);
}

$ix = 1;
while ($line = fgets($f)) {
    $parts = explode('/', $line);

    $arrVariables[':kitId'] = trim(end($parts), "\n");
    // $db->ExecuteBindQuery($sql, $arrVariables);
    echo sprintf('%05d', $ix) . " => " . $arrVariables[':kitId'] . '<br>';
    $ix++;
}
