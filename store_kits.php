<?php
error_reporting(1);
ini_set("display_errors", 1);

require_once 'lib/default_conf.php';
include "views/Header.html.php";

if ($_POST['action'] == 'load') {
    $dbConnResult = Database::init($GLOBALS["DBConnection_URI"]);
    if (!$dbConnResult) {
        return;
    }

    $arrVariables[':manufacturePlace'] = $_POST['manufacture_place'];
    $arrVariables[':manufactureDate'] = $_POST['manufacture_date'];
    $arrVariables[':expirationDate'] = $_POST['expiration_date'];
    $arrVariables[':batchNumber'] = $_POST['batch_number'];
    $arrVariables[':instanceId'] = $_POST['instance_id'];
    $arrVariables[':programCode'] = $_POST['program_code'];
    $sql = 'INSERT INTO KIT_INFO (KIT_ID, MANUFACTURE_PLACE, MANUFACTURE_DATE, EXPIRATION, BATCH_NUMBER, STATUS, ID_INSTANCE, PROGRAM_CODE) VALUES(:kitId, :manufacturePlace, :manufactureDate, :expirationDate, :batchNumber, NULL, :instanceId, :programCode)';

    $errorList = null;
    $ok = 0;

    if (!$arrVariables[':programCode']) {
        $errorList[] = ['subject' => 'Program Code', 'error' => 'It is mandatory to provide a Program Code'];
    }
    if (!$arrVariables[':instanceId']) {
        $errorList[] = ['subject' => 'Instance ID', 'error' => 'It is mandatory to provide an Instance ID'];
    }

    if (empty($errorList)) {
        if ($_FILES["kits_file"] && $_FILES["kits_file"]["tmp_name"]) {
            $srcFileName = $_FILES["kits_file"]["tmp_name"];
            $db = Database::getInstance();
            $f = fopen($srcFileName, 'r');
            if (!$f) {
                $errorList[] = ['subject' => $_FILES["kits_file"]["name"], 'error' => 'ERROR OPENING FILE'];
            }
        } else {
            $errorList[] = ['subject' => 'Source file', 'error' => 'NO FILE PROVIDED'];
        }
    }

    if (empty($errorList)) {
        $ix = 1;
        while ($line = fgets($f)) {
            if (!trim($line)) {
                continue;
            }
            $parts = explode('/', $line);
            $kitId = trim(end($parts), "\n");
            if (strlen($kitId) == 5) {
                $arrVariables[':kitId'] = $kitId;
                $db->ExecuteBindQuery($sql, $arrVariables);
                $error = $db->getErrorMsg();
                if ($error && $error != 'null') {
                    $errorList[] = ['subject' => $kitId, 'error' => $error];
                } else {
                    $ok++;
                }
            } else {
                $errorList[] = ['subject' => $kitId, 'error' => 'Incorrect Kit ID format'];
            }
            $ix++;
        }
        fclose($f);
    }

    // header('Content-Type: application/json');
    $resp = new StdClass();
    $message = 'Kits correctly stored: ' . $ok . '<br>';
    if (empty($errorList)) {} else {
        $message .= 'Errors: ' . count($errorList) . '<br><br>';
        $message .= '<table class="table table-striped">';
        $message .= '<thead class="thead-dark"><tr><th style="text-align: left;">Subject</th><th style="text-align: left;">Error</th></tr></thead>';
        $message .= '<tbody>';
        foreach ($errorList as $e) {
            $message .= '<tr>';
            $message .= '<td>' . $e['subject'] . '</td><td>' . $e['error'] . '</td>';
            $message .= '</tr>';
        }
        $message .= '</tbody>';
        $message .= '</table>';
    }

    echo $message;
    return;
}

?>
<div class="container col-lg-12 col-md-12">
    <form id='uploadForm' method="post" enctype="multipart/form-data">
        <input type='hidden' name='action' value="load">
        <label for="manufacture_place">Manufacture place:</label>
        <input type='text' id='manufacture_place' name='manufacture_place' style="background-color: lightyellow;" value="Nantong - RP China"><br>
        <label for="manufacture_date">Manufacture date (yyyy-mm-dd):</label>
        <input type='text' id='manufacture_date' name='manufacture_date' style="background-color: lightyellow;"value=""><br>
        <label for="expiration_date">Expiration date (yyyy-mm-dd):</label>
        <input type='text' id='expiration_date'  name='expiration_date' style="background-color: lightyellow;"value = ""><br>
        <label for="batch_number">Batch number (e.g. 20210806):</label>
        <input type='text' id='batch_number' name='batch_number' style="background-color: lightyellow;"value=""><br>
        <label for="program_code">Program Code:</label>
        <input type='text' id='program_code' name='program_code' style="background-color: lightyellow;"value="COVID19_AG"><br>
        <label for="instance_id">Linkcare Instance Id (e.g. TEST, DEMO, COVID19):</label>
        <input type='text' id='instance_id' name='instance_id' style="background-color: lightyellow;"><br>
        <label for="kits_file">Select file to upload</label>
        <input type='file' id='kits_file' name='kits_file' style="background-color: lightyellow;"><br>
        <br>
        <input type='submit' value="Submit">
    </form>
</div>

