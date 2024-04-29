<?php
/*
 * Script for transferring the whole contents of the database of this instace to another database
 */
$command = $argv[0];

if (count($argv) < 2) {
    echo "\nScript to transfer all the contents of the active DB to a different location\n";
    echo "The process stores progress information and can be stopped and restarted at any time.\n";
    echo "\nUsage:\n";
    $msg = "  $command <uri> [reset|continue]\n\n";
    $msg = sprintf("\033[33m%s\033[0m", $msg);
    echo $msg;
    echo "  uri: Database connection string to the destination database with format: schema://user:password@host:port/database\n";
    echo "  [reset|continue] permit to decide how to behave when the script detects that it was executed previously for the same destination host:\n";
    echo "    - reset: repeat again the whole data transfer process. The content of all tables in the destination server will be removed and populated with the data from the source server\n";
    echo "    - continue: continue the data transfer process from the point it stopped. The date of tables already transferred will be respected, and only the remaininin tables will be migrated\n";
    echo "\n";
    echo "Examples:\n";
    echo "  $command oci://datadb:password@db.linkcareapp.com:1521/linkcare reset\n";
    echo "  $command mysql://user:password@db.linkcareapp.com:3306/datadb reset\n";
    echo "\n";
    exit(0);
}

error_reporting(E_ALL);
ini_set("display_errors", "stderr");
ini_set('max_execution_time', 1000);
ini_set('max_input_time', 1000);
require_once 'lib/default_conf.php';

$logger = new LKLogger(LKLogger::LEVEL_INFO, LKLogger::OUTPUT_CONSOLE);

$dbConnResult = Database::init($GLOBALS["DBConnection_URI"]);
if ($dbConnResult !== true) {
    $logger->error("Failed connection to local DB");
}

$sourceDb = Database::getInstance();

$targetDbURI = $argv[1]; // 'mysql://platformuser:password@test.linkcareapp.com:/LK_EMPTY1';

try {
    $targetDb = DbManager::init($targetDbURI);
    $targetDb->ConnectServer();
} catch (Exception $e) {
    $errMsg = 'ERROR connecting to target database: ' . $e->getMessage();
    $logger->error($errMsg);
    exit(1);
}

/* Store the progress indicating which tables have already been migrated in a file */
$progressFile = 'DATA_' . $targetDb->GetHost() . "-" . $targetDb->GetDatabase() . '-' . $targetDb->GetUser() . '.transfer_progress';

if (file_exists($progressFile)) {
    $option = count($argv) > 2 ? $argv[2] : null;
    if ($option == 'reset') {
        unlink($progressFile);
        $option = null;
    } elseif ($option != 'continue') {
        /*
         * The process was executed before. Ask the user whether he wants to continue the transfer process from the point where it was interrupted or
         * reset it
         */
        $msg = "Apparently the transfer process has already been executed. Execute again the command indicating what you want to do. The possible options are:\n";
        $msg = sprintf("\033[31m%s\033[0m", $msg);

        echo $msg;
        echo "  - reset: repeat again the whole data transfer process. The content of all tables in the destination server will be removed and populated with the data from the source server\n";
        echo "  - continue: continue the data transfer process from the point it stopped. The date of tables already transferred will be respected, and only the remaininin tables will be migrated\n";
        $msg = "Apparently the transfer process has already been executed. Execute again the command indicating what you want to do. The possible options are:\n";
        echo "\n";
        $msg = "  Syntax: $command <uri> [reset|continue]\n\n";
        $msg = sprintf("\033[33m%s\033[0m", $msg);
        echo $msg;
        exit(0);
    }
}

// List of tables to migrate
// $migrateTables[] = 'LC_INSTANCES';
// $migrateTables[] = 'LC_PROGRAMS';
$migrateTables[] = 'KIT_INFO';
// $migrateTables[] = 'KIT_TRACKING';
// $migrateTables[] = 'GATEKEEPER_TRACKING';

$progressInfo = new stdClass();
if (file_exists($progressFile)) {
    $progressInfo = json_decode(file_get_contents($progressFile));
    if (!$progressInfo) {
        $progressInfo = new stdClass();
    }
}
if (!property_exists($progressInfo, 'alreadyTransferred') || !is_array($progressInfo->alreadyTransferred)) {
    $progressInfo->alreadyTransferred = [];
}
if (!property_exists($progressInfo, 'current') || !is_object($progressInfo->current)) {
    $progressInfo->current = new stdClass();
    $progressInfo->current->name = null;
    $progressInfo->current->offset = 1;
}

if ($targetDb->getType() == DbManager::ORACLE) {
    // In ORACLE, the name of the schema is identified by service/user
    $schemaName = $targetDb->GetDatabase() . "/" . $targetDb->GetUser();
} else {
    $schemaName = $targetDb->GetDatabase();
}
$logger->info('---------------------------------------------------------------------------------');
$logger->info("  TRANSFERRING DATA TO DATABASE $schemaName at host: " . $targetDb->GetHost());
$logger->info('---------------------------------------------------------------------------------');

$logger->info('STEP 1: REMOVE PREVIOUS CONTENT');
// Remove the contents of all tables
foreach (array_reverse($migrateTables) as $tableName) {
    if (in_array($tableName, $progressInfo->alreadyTransferred)) {
        $logger->info("Skip table $tableName (already transferred)", 1);
        continue;
    }
    if ($progressInfo->current->name == $tableName) {
        break;
    }
    $logger->info("Removing contents of table $tableName", 1);
    $sql = "DELETE FROM " . $targetDb->quoteIdentifier($tableName);
    $targetDb->ExecuteBindQuery($sql);
    $error = $targetDb->getError();
    if ($error->getErrorCode()) {
        $logger->error("Error deleting contents of table " . $tableName . '. ' . $error->getErrorMessage());
        exit(1);
    }
}

$error = new ErrorInfo();
$logger->info('STEP 2: TRANSFER TABLE CONTENTS');
$dbSchema = DataModels::dataModel('TRANSFER');
foreach ($migrateTables as $tableName) {
    if (in_array($tableName, $progressInfo->alreadyTransferred)) {
        $logger->info("Skip table $tableName (already transferred)", 1);
        continue;
    }

    $countSql = "SELECT COUNT(*) AS TOTAL FROM " . $sourceDb->quoteIdentifier($tableName);
    $rst = $sourceDb->ExecuteBindQuery($countSql);
    $error = $sourceDb->getError();
    if ($error->getErrorCode()) {
        $logger->error("Error calculating the total number of rows of table " . $tableName . '. ' . $error->getErrorMessage());
        exit(1);
    }
    $rst->Next();
    $total = $rst->GetField('TOTAL');

    $logger->info('Transferring table ' . $tableName . ". Rows: $total", 1);
    $tableDef = $dbSchema->getTable($tableName);
    if (!$tableDef) {
        $logger->error("The table $tableName does not exist in the current implementation of the platform");
        exit(1);
    }
    $ix = 1;
    $colNames = [];
    $arrBlobNames = [];
    $params = [];
    foreach ($tableDef->columns as $column) {
        $colNames[$ix] = $column->name;
        if ($column->dataType == DbDataTypes::BLOB) {
            $placeholder = ':blob_' . $ix;
            $params[$ix] = $placeholder;
            $arrBlobNames[$placeholder] = $column->name;
        } elseif ($column->dataType == DbDataTypes::LONGTEXT) {
            $placeholder = ':clob_' . $ix;
            $params[$ix] = $placeholder;
            $arrBlobNames[$placeholder] = $column->name;
        } else {
            $params[$ix] = ':col' . $ix;
        }
        $ix++;
    }

    // If the table has a primary key, use it to sort the queries
    $sortBy = '';
    if (!empty($tableDef->primaryKey)) {
        $sortCols = [];
        foreach ($tableDef->primaryKey as $pkColName) {
            $sortCols[] = $sourceDb->quoteIdentifier($pkColName);
        }
        $sortBy = 'ORDER BY ' . implode(',', $sortCols);
    }

    $sourceColStr = implode(',', array_map(function ($x) use ($sourceDb) {
        return $sourceDb->quoteIdentifier($x);
    }, $colNames));
    $targetColStr = implode(',', array_map(function ($x) use ($targetDb) {
        return $targetDb->quoteIdentifier($x);
    }, $colNames));

    $paramsStr = implode(',', $params);
    $sourceSql = "SELECT $sourceColStr FROM " . $sourceDb->quoteIdentifier($tableName) . " $sortBy";
    $targetSql = 'INSERT INTO ' . $targetDb->quoteIdentifier($tableName) . "($targetColStr) VALUES ($paramsStr)";

    if ($progressInfo->current->name != $tableName) {
        $progressInfo->current->offset = 1;
        $progressInfo->current->processed = 0;
    }
    $progressInfo->current->name = $tableName;
    $offset = $progressInfo->current->offset;
    $processed = $progressInfo->current->processed;

    $pageSize = 100;
    while ($offset <= $total && !$error->getErrorCode()) {
        $rst = $sourceDb->ExecuteBindQuery($sourceSql, null, $pageSize, $offset);
        $error = $sourceDb->getError();
        if ($error->getErrorCode()) {
            $logger->error("Error retrieving data from table " . $tableName . '. ' . $error->getErrorMessage());
            exit(1);
        }
        $targetDb->beginTransaction();
        while ($rst->Next() && !$error->getErrorCode()) {
            $arrVariables = [];
            foreach ($colNames as $ix => $colName) {
                $arrVariables[$params[$ix]] = $rst->GetField($colName);
            }
            if (empty($arrBlobNames)) {
                // Regular insert with no Large Object columns (LOBs)
                $targetDb->ExecuteBindQuery($targetSql, $arrVariables);
            } else {
                // Insert Large Object columns (LOBs)
                $targetDb->ExecuteLOBQuery($targetSql, $arrVariables, $arrBlobNames);
            }
            $error = $targetDb->getError();
            if ($error->getErrorCode()) {
                echo ("\n");
                $logger->error("Error inserting contents in table " . $tableName . '. ' . $error->getErrorMessage());
                break;
            }
            $processed++;
        }
        if (!$error->getErrorCode()) {
            echo ("\r  Progress: $processed / $total (" . ($total ? round(100 * $processed / $total, 1) : 100) . "%)");
            $targetDb->commit();
            $offset += $pageSize;
        } else {
            $targetDb->rollback();
            break;
        }
        $progressInfo->current->offset = $offset;
        $progressInfo->current->processed = $processed;
        file_put_contents($progressFile, json_encode($progressInfo));
    }
    echo ("\n");

    if ($error->getErrorCode()) {
        break;
    }
    $progressInfo->alreadyTransferred[] = $tableName;
    file_put_contents($progressFile, json_encode($progressInfo));
}

if (!$error->getErrorCode()) {
    $logger->info('---------------------------------------------------------------------------------');
    $logger->info('  TRANSFERENCE COMPLETED SUCCESSFULLY');
    $logger->info('---------------------------------------------------------------------------------');
}