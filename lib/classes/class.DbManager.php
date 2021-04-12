<?php

class DbManager {
    var $Host;
    var $User;
    var $Passwd;
    var $Database;
    var $Persistent;

    function SetHost($inputHost) {
        $this->Host = $inputHost;
    }

    function SetUser($inputUser) {
        $this->User = $inputUser;
    }

    function SetPasswd($inputPasswd) {
        $this->Passwd = $inputPasswd;
    }

    function SetDatabase($inputDatabase) {
        $this->Database = $inputDatabase;
    }

    function SetPersistent($persistent = true) {
        $this->Persistent = $persistent;
    }

    function GetHost() {
        return $this->Host;
    }

    function GetUser() {
        return $this->User;
    }

    function GetPasswd() {
        return $this->Passwd;
    }

    function GetDatabase() {
        return $this->Database;
    }

    function GetPersistent() {
        return $this->Persistent;
    }

    function StrToBD($value) {
        if ($value == "") {
            return ("null");
        }
        return ("'" . $value . "'");
    }

    function ConnectServer() {}

    function DisconnectServer() {}

    function SelectDataBase($dbName) {}

    /**
     *
     * @return DbManagerResults
     */
    function ExecuteQuery($query) {}

    /**
     *
     * @return DbManagerResults
     */
    function ExecuteBindQuery($query, $arrVariables, $log = false) {}

    /**
     *
     * @return DbManagerResults
     */
    function ExecuteLOBQuery($query, $arrVariables, $arrBlobNames, $log = false) {}

    /**
     *
     * @return bool
     */
    function LOBAppend($query, $arrVariables, $lobName, $lobValue, $log = false) {}

    /**
     *
     * @return DbManagerResults
     */
    function ExecuteQueryClob($query) {}

    function ExecuteQueries($queries, $log = false) {}

    function ExecuteBindQueries($queries, $arrVariables, $log = false) {}

    function LastInsertedId() {}

    function setAutocommit($autocommit) {}

    function begin_transaction() {}

    function commit() {}

    function rollback() {}

    function getErrorNumber() {}

    function getErrorDescription() {}

    function getErrorDescriptionDebug() {}

    function getErrorMsg() {}

    function str_to_date($field) {}

    function date_to_str($field) {}

    function str_to_time() {}

    function time_to_str($field) {}

    function int_to_string($field) {}

    function string_to_int($field) {}

    function getRowsAffected() {}

    function concat($field1, $field2) {}
}

