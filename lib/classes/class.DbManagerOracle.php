<?php
include_once ("class.DbManager.php");

/**
 * Returns true if the string $needle is found exactly at the begining of $haystack
 *
 * @param string $needle
 * @param string $haystack
 * @return boolean
 */
function startsWith($needle, $haystack) {
    return (strpos($haystack, $needle) === 0);
}

class DbManagerOracle extends DbManager {
    private $conn;
    private $nrows;
    private $pdo;
    private $res;
    private $error;
    private $errorDetails;
    public $should_commit;
    private $Port = '1521';

    function __construct() {
        $this->should_commit = $GLOBALS['COMMIT'];
    }

    function setURI($uri) {
        $dict = parse_url($uri);
        $this->Host = isset($dict['host']) ? $dict['host'] : 'localhost';
        $this->User = $dict['user'];
        $this->Passwd = $dict['pass'];
        $this->Port = isset($dict['port']) ? $dict['port'] : $this->Port;
        $this->Database = trim($dict['path'], "/");
    }

    function ConnectServer($pdo = true, $persistant = true) {
        $this->pdo = $pdo;
        $MAX_intentos = 5;
        $intentos = 0;
        while ($this->conn == null && $intentos < $MAX_intentos) {
            try {
                // by default use OCI8 connection
                if ($this->pdo) {
                    $this->conn = new PDO("oci:dbname=//" . $this->Host . "/" . $this->Database . ";charset=AL32UTF8", $this->User, $this->Passwd);
                } else {
                    if ($persistant) {
                        $this->conn = oci_pconnect($this->User, $this->Passwd, $this->Host . ':' . $this->Port . '/' . $this->Database, 'AL32UTF8');
                    } else {
                        $this->conn = oci_connect($this->User, $this->Passwd, $this->Host . ':' . $this->Port . '/' . $this->Database, 'AL32UTF8');
                    }
                }
                $intentos++;
            } catch (PDOException $e) {
                sleep(0.01);
                $intentos++;
            }
        }
        if ($intentos == $MAX_intentos) {
            // file_put_contents("conn_error.log", "Error al conectar a Oracle: " . $e->getMessage(), FILE_APPEND);
            throw new Exception("Error al conectar a Oracle: {user: $this->User DB: $this->Database Host: $this->Host }");
        } else {
            return ($this->conn);
        }
    }

    function Conn($connection) {}

    function DisconnectServer() {
        oci_close($this->conn);
    }

    function SelectDataBase($dbName) {}

    /* to write sql call log just set second parameter as true */
    function ExecuteQueries($queries, $log = false) {
        $this->clearError();
        foreach ($queries as $query) {
            $this->ExecuteQuery($query, $log);
        }
    }

    /* to write sql call log just set second parameter as true */
    function ExecuteBindQueries($queries, $arrVariables, $log = false) {
        $this->clearError();
        foreach ($queries as $query) {
            $this->ExecutebindQuery($query, $arrVariables, $log);
        }
    }

    function ExecuteQuery($query, $log = false) {
        $this->clearError();
        $this->nrows = 0;

        $commit = ($this->should_commit && $GLOBALS['COMMIT']);

        $isQuery = true;
        if (strtoupper(substr(trim($query), 0, 6)) == 'DELETE' || strtoupper(substr(trim($query), 0, 6)) == 'UPDATE' ||
                strtoupper(substr(trim($query), 0, 6)) == 'INSERT') {
            $isQuery = false;
        }

        // if database is locked don't permit any update or deletion
        if ($GLOBALS["READ_ONLY"]) {
            if (!$isQuery) {
                return;
            }
        }

        $this->res = oci_parse($this->conn, $query);
        $error = oci_error($this->conn);
        if (!$error) {
            oci_execute($this->res, ($commit ? OCI_COMMIT_ON_SUCCESS : OCI_NO_AUTO_COMMIT));
            $error = oci_error($this->res);
            if (!$error) {
                $error = oci_error($this->conn);
            }
            $this->nrows = oci_num_rows($this->res);
        }

        $this->SetError($error);
        if ($this->error) {
            // logw(json_encode(end(array_filter($this->error))) . $query . PHP_EOL . json_encode(debug_backtrace()), 'error');
        }

        if ($isQuery) {
            $this->results = new DbManagerResultsOracle();
            $this->results->setResultSet($this->res, $this->pdo);
        }

        if ($log) {
            if ($GLOBALS["SQL_LOGS"]) {
                $callers = debug_backtrace();
                if (!empty($callers[1])) {
                    $function = array_key_exists('class', $callers[1]) ? $callers[1]['class'] . ':' . $callers[1]['function'] : $callers[1]['function'];
                    $file = $callers[0]['file'];
                    $line = $callers[0]['line'];
                    $file = end(explode('/', $file)) . PHP_EOL . $line;
                    // log_start_write("", "sql", todayUTC(), $function . PHP_EOL . $file, '', $brCounter->elapsed(), $query);
                }
            }
        }

        return ($this->results);
    }

    function ExecuteBindQuery($query, $arrVariables, $log = false) {
        $this->clearError();
        $this->nrows = 0;

        $autoCommit = ($this->should_commit && $GLOBALS['COMMIT']);

        $isQuery = true;
        if (strtoupper(substr(trim($query), 0, 6)) == 'DELETE' || strtoupper(substr(trim($query), 0, 6)) == 'UPDATE' ||
                strtoupper(substr(trim($query), 0, 6)) == 'INSERT') {
            $isQuery = false;
        }

        // if database is locked don't permit any update or deletion
        if ($GLOBALS["READ_ONLY"]) {
            if (!$isQuery) {
                return;
            }
        }
        $lobs = null;
        $this->res = oci_parse($this->conn, $query);
        $error = oci_error($this->conn);
        if (!$error) {
            // if this is not an array with variables than this is only unique :id variable in query
            if (!is_array($arrVariables)) {
                $arrVariables = [':id' => $arrVariables];
            }
            foreach ($arrVariables as $key => $val) {
                if (startsWith(':clob_', $key) || startsWith(':blob_', $key)) {
                    $bindType = (startsWith(':clob_', $key) ? OCI_B_CLOB : OCI_B_BLOB);
                    $lobs[$key] = oci_new_descriptor($this->conn, OCI_D_LOB);
                    oci_bind_by_name($this->res, $key, $lobs[$key], -1, $bindType);
                } else {
                    oci_bind_by_name($this->res, $key, $arrVariables[$key], -1);
                }
            }
            if (!$lobs) {
                // no clobs in query:
                oci_execute($this->res, ($autoCommit ? OCI_COMMIT_ON_SUCCESS : OCI_NO_AUTO_COMMIT));
                $error = oci_error($this->res);
            } else {
                // clobs in query: first execute it
                // file_put_contents("/var/www/dev4.linkcare.es/ws/tmp/log.log", $query.PHP_EOL.json_encode($arrVariables).PHP_EOL, FILE_APPEND);
                oci_execute($this->res, OCI_DEFAULT);
                $error = oci_error($this->res);

                // file_put_contents("/var/www/dev4.linkcare.es/ws/tmp/log.log", " >>>>> EXECUTED".PHP_EOL, FILE_APPEND);
                $ok = true;
                if (!$error) {
                    foreach ($lobs as $key => $lob) {
                        // then save clobs
                        if (!$lob->save($arrVariables[$key])) {
                            $ok = false;
                        }
                    }
                }
                if ($autoCommit) {
                    if ($ok && !$error) {
                        oci_commit($this->conn);
                    } else {
                        oci_rollback($this->conn);
                    }
                }
            }
            if (!$error) {
                $this->nrows = oci_num_rows($this->res);
                $error = oci_error($this->conn);
            }
        }

        $this->SetError($error);

        if ($this->error) {
            $minVariables = [];
            foreach ($arrVariables as $ix => $v) {
                if (strlen($v) > 1000) {
                    $minVariables[$ix] = substr($v, 0, 1000);
                } else {
                    $minVariables[$ix] = $v;
                }
            }
            // logw(json_encode(end($this->error)) . PHP_EOL . json_encode($minVariables) . PHP_EOL . $query . PHP_EOL .
            // json_encode(debug_backtrace()), 'error');
        }

        if ($isQuery) {
            $this->results = new DbManagerResultsOracle();
            $this->results->setResultSet($this->res, $this->pdo);
        }

        if ($log) {
            if ($GLOBALS["SQL_LOGS"]) {
                $callers = debug_backtrace();
                if (!empty($callers[1])) {
                    $function = array_key_exists('class', $callers[1]) ? $callers[1]['class'] . ':' . $callers[1]['function'] : $callers[1]['function'];
                    $file = $callers[0]['file'];
                    $line = $callers[0]['line'];
                    $file = end(explode('/', $file)) . PHP_EOL . $line;
                    // log only "valid" query without variables
                    // log_start_write("", "sql", todayUTC(), $function . PHP_EOL . $file, '', $brCounter->elapsed(), json_encode($arrVariables) .
                    // "\n$query");
                }
            }
        }

        return ($this->results);
    }

    function ExecuteLOBQuery($query, $arrVariables, $arrBlobNames, $log = false) {
        $this->clearError();
        $this->nrows = 0;

        if (!is_array($arrVariables)) {
            $arrVariables = [':id' => $arrVariables];
        }
        if (empty($arrBlobNames)) {
            $arrBlobNames = [];
        }
        $autoCommit = ($this->should_commit && $GLOBALS['COMMIT']);

        // if database is locked don't permit any update or deletion
        if ($GLOBALS["READ_ONLY"]) {
            return;
        }
        $lobs = null;

        if (!empty($arrBlobNames)) {
            $query = self::buildLobInsert($query, $arrBlobNames);
        }

        $this->res = oci_parse($this->conn, $query);
        $error = oci_error($this->conn);
        if (!$error) {
            foreach ($arrVariables as $key => $val) {
                if (!in_array($key, $arrBlobNames)) {
                    oci_bind_by_name($this->res, $key, $arrVariables[$key], -1);
                }
            }
            foreach ($arrBlobNames as $key => $fieldName) {
                $bindType = (startsWith(':clob_', $key) ? OCI_B_CLOB : OCI_B_BLOB);
                $lobs[$key] = oci_new_descriptor($this->conn, OCI_D_LOB);
                oci_bind_by_name($this->res, $key, $lobs[$key], -1, $bindType);
            }

            if (!$lobs) {
                // no clobs in query:
                oci_execute($this->res, ($autoCommit ? OCI_COMMIT_ON_SUCCESS : OCI_NO_AUTO_COMMIT));
                $error = oci_error($this->res);
            } else {
                oci_execute($this->res, OCI_DEFAULT);
                $error = oci_error($this->res);

                $ok = true;
                if (!$error) {
                    foreach ($lobs as $key => $lob) {
                        // then save clobs
                        if (!$lob->save($arrVariables[$key])) {
                            $ok = false;
                        }
                    }
                }
                if ($autoCommit) {
                    if ($ok && !$error) {
                        oci_commit($this->conn);
                    } else {
                        oci_rollback($this->conn);
                    }
                }
                foreach ($lobs as $key => $lob) {
                    $lob->free();
                }
            }
            if (!$error) {
                $this->nrows = oci_num_rows($this->res);
                $error = oci_error($this->conn);
            }
        }

        $this->SetError($error);
        if ($this->error) {
            // logw(json_encode(end($this->error)) . PHP_EOL . $query . json_encode(debug_backtrace()), 'error');
        }

        if ($log) {
            if ($GLOBALS["SQL_LOGS"]) {
                $callers = debug_backtrace();
                if (!empty($callers[1])) {
                    $function = array_key_exists('class', $callers[1]) ? $callers[1]['class'] . ':' . $callers[1]['function'] : $callers[1]['function'];
                    $file = $callers[0]['file'];
                    $line = $callers[0]['line'];
                    $file = end(explode('/', $file)) . PHP_EOL . $line;
                    // log only "valid" query without variables
                    // log_start_write("", "sql", todayUTC(), $function . PHP_EOL . $file, '', $brCounter->elapsed(), json_encode($arrVariables) .
                    // "\n$query");
                }
            }
        }

        return ($this->results);
    }

    /**
     * This function expects a SQL query like "SELECT myLOBField FROM myTable WHERE id=1 FOR UPDATE" and appends contents to the LOB fields indicated
     * in $arrBlobNames
     * $arrVariables is an associative array where the key is the name of the parameter in the SQL query and the contents is the value that will be
     * appended to the blob fields
     *
     * The function returns the final length of the LOB
     *
     * @param string $query
     * @param string[] $arrVariables
     * @param string $lobName
     * @param string $lobValue
     * @param boolean $log
     * @return int
     */
    function LOBAppend($query, $arrVariables, $lobName, $lobValue, $log = false) {
        $this->clearError();
        $this->nrows = 0;
        $finalLength = null;

        if (!is_array($arrVariables)) {
            $arrVariables = [':id' => $arrVariables];
        }
        $autoCommit = ($this->should_commit && $GLOBALS['COMMIT']);

        // if database is locked don't permit any update or deletion
        if ($GLOBALS["READ_ONLY"]) {
            return $finalLength;
        }

        $this->res = oci_parse($this->conn, $query);
        $error = oci_error($this->conn);
        if (!$error) {
            foreach ($arrVariables as $key => $val) {
                oci_bind_by_name($this->res, $key, $arrVariables[$key], -1);
            }

            oci_execute($this->res, OCI_DEFAULT);
            $error = oci_error($this->res);

            if (!$error) {
                // Fetch the SELECTed row
                $row = oci_fetch_assoc($this->res);
                if (FALSE === $row) {
                    $error = oci_error($this->conn);
                }
            }

            if (!$error && $row && $row[$lobName]) {
                $row[$lobName]->seek(0, OCI_SEEK_END);
                $row[$lobName]->write($lobValue);
                $finalLength = $row[$lobName]->size();
            }

            if ($autoCommit) {
                if (!$error) {
                    oci_commit($this->conn);
                } else {
                    oci_rollback($this->conn);
                }
            }

            if ($row && $row[$lobName]) {
                $row[$lobName]->free();
            }
        }

        $this->SetError($error);
        if ($this->error) {
            // logw(json_encode(end($this->error)) . PHP_EOL . $query . json_encode(debug_backtrace()), 'error');
        }

        if ($log) {
            if ($GLOBALS["SQL_LOGS"]) {
                $callers = debug_backtrace();
                if (!empty($callers[1])) {
                    $function = array_key_exists('class', $callers[1]) ? $callers[1]['class'] . ':' . $callers[1]['function'] : $callers[1]['function'];
                    $file = $callers[0]['file'];
                    $line = $callers[0]['line'];
                    $file = end(explode('/', $file)) . PHP_EOL . $line;
                    // log only "valid" query without variables
                    // log_start_write("", "sql", todayUTC(), $function . PHP_EOL . $file, '', $brCounter->elapsed(), json_encode($arrVariables) .
                    // "\n$query");
                }
            }
        }

        return $finalLength;
    }

    function ExecuteQueryClob($query) {
        $commit = ($this->should_commit && $GLOBALS['COMMIT']);
        $this->clearError();
        $this->nrows = 0;
        if ($this->pdo) {
            $this->res = $this->conn->prepare($query);
            $this->res->execute();
            $this->res->bindColumn(1, $lob, PDO::PARAM_LOB);
            $this->res->fetch(PDO::FETCH_BOUND);
            $clob = stream_get_contents($lob);
            $lob = null;
            return ($clob);
        } else {
            $s = oci_parse($this->conn, $query);
            $error = oci_error($this->conn);
            if (!$error) {
                oci_execute($s, ($commit ? OCI_COMMIT_ON_SUCCESS : OCI_NO_AUTO_COMMIT));
                $error = oci_error($s);
                if (!$error) {
                    $a = oci_fetch_array($s, OCI_RETURN_LOBS);
                    return $a[0];
                }
            }
        }
        return null;
    }

    function getColumnNames() {
        if ($this->pdo) {
            $total_column = $this->conn->fetchColumn();
            for ($counter = 0; $counter <= $total_column; $counter++) {
                $meta = $this->conn->getColumnMeta($counter);
                $column[] = $meta['name'];
            }
            return $column;
        } else {
            $ncols = oci_num_fields($this->res);
            for ($i = 1; $i <= $ncols; ++$i) {
                $colnames[] = oci_field_name($this->res, $i);
            }
            return $colnames;
        }
    }

    function getColumnTypes() {
        $ncols = oci_num_fields($this->res);
        for ($i = 1; $i <= $ncols; ++$i) {
            $coltypes[] = oci_field_type($this->res, $i);
        }
        return $coltypes;
    }

    function LastInsertedId() {}

    function setAutocommit($autocommit) {}

    function begin_transaction() {
        $this->should_commit = false;
    }

    function commit() {
        oci_commit($this->conn);
    }

    function rollback() {
        oci_rollback($this->conn);
    }

    function getErrorNumber() {
        if (!empty($this->errorDetails)) {
            foreach ($this->errorDetails as $errorDetail) {
                $txt[] = $errorDetail['code'];
            }
            return implode('/', $txt);
        }
        return null;
    }

    function getErrorDescription() {
        if (!empty($this->errorDetails)) {
            foreach ($this->errorDetails as $errorDetail) {
                $txt[] = $errorDetail['code'] . " : " . $errorDetail['message'];
            }
            return implode('\n', $txt);
        }
        return null;
    }

    function getErrorDescriptionDebug() {
        if ($this->pdo) {
            $var = $this->conn->errorInfo();
            if (is_array($var)) {
                $this->error = $var[2];
            }
        } else {
            $e = oci_error($this->res);
            if ($e) {
                return htmlentities($e['message'] . " in " . $e['sqltext']);
            }
        }
    }

    function getErrorMsg() {
        if ($this->pdo) {
            $var = $this->conn->errorInfo();
            if (is_array($var)) {
                $this->error = $var[2];
            }
        }
        return json_encode($this->error);
    }

    function SetError($e, $function = null) {
        if (is_object($e)) {
            $error = htmlentities($e['message'] . " in " . $e['sqltext']);
            $this->error[] = $error;
            $this->errorDetails = ['code' => $e['code'], 'message' => $e['message']];
        } else {
            if ($e != '') {
                $this->error[] = $e;
            }
        }
    }

    function getRowsAffected() {
        return $this->nrows;
    }

    function str_to_date($strDate) {}

    function date_to_str($field) {}

    function time_to_date($strTime) {}

    function time_to_str($field) {}

    function string_to_int($field) {}

    function int_to_string($field) {}

    function concat($field1, $field2) {}

    private function clearError() {
        $this->error = null;
        $this->errorDetails = null;
    }

    /**
     * Modifies the INSERT SQL $insertQuery adding a 'RETURNING' clause for the bind variables defined in $arrBoundVariables
     * This is necessary for inserting LOB values in a query with bound variables
     *
     * @param string $query
     * @param string[] $arrBoundVariables
     * @return string
     */
    private function buildLobInsert($insertQuery, $arrBoundVariables) {
        foreach ($arrBoundVariables as $varName => $fieldName) {
            if (startsWith(":blob_", $varName)) {
                $insertQuery = str_replace($varName, "EMPTY_BLOB()", $insertQuery);
            } else {
                $insertQuery = str_replace($varName, "EMPTY_CLOB()", $insertQuery);
            }
        }

        $fieldNames = implode(",", array_values($arrBoundVariables));
        $varNames = implode(",", array_keys($arrBoundVariables));

        // The RETURNING clause should look like: RETURNING field_lob_a,field_lob_b INTO :LOB_A,:LOB_B"
        $insertQuery = $insertQuery . " RETURNING $fieldNames INTO $varNames";
        return $insertQuery;
    }
}
