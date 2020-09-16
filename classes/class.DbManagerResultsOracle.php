<?php
include_once("class.DbManagerResults.php");

class DbManagerResultsOracle extends DbManagerResults {
    var $rst;
    var $rs;
    var $pdo;

    function setResultSet($pRst, $pdo = true) {
        $this->pdo = $pdo;
        $this->rst = $pRst;
    }

    function NRows() {

    }

    function Next() {
        if ($this->rst) {
            if ($this->pdo) {
                $this->rst->fetch(PDO::FETCH_ASSOC);
            } else {
                $this->rs = oci_fetch_array($this->rst);
            }
        } else {
            return false;
        }
        return ($this->rs);
    }

    function GetField($fieldName) {
        if (!isset($this->rs[$fieldName])) {
            return null;
        } else {
            if (is_object($this->rs[$fieldName])) {
                return $this->rs[$fieldName]->load();
            } //clob oci
            else {
                return ($this->rs[$fieldName]);
            }
        }
    }
    function GetLOBChunk($fieldName, $startPos, $length) {
        if ($startPos < 0 || !isset($this->rs[$fieldName]) || !is_object($this->rs[$fieldName]) ) {
            return null;
        } 
        
        $size =  $this->rs[$fieldName]->size();
        if($startPos >= $size) {
            return null;
        }
        if($startPos + $length >= $size) {
            $length = $size - $startPos;
        }

        $this->rs[$fieldName]->seek($startPos);
        return $this->rs[$fieldName]->read($length);
    }

    function GetAllFields(){
        return $this->rs;
    }

    function GetFieldNULL($fieldName, $scaped = true) {
        if (trim($this->rs[$fieldName]) == "") {
            return "NULL";
        }
        if ($this->rs[$fieldName] == null) {
            return "NULL";
        }
        if ($scaped) /* 25/02/2005: Escapamos los caracteres conflictivos con HTML */ {
            return ($this->scapeXMLChars($this->rs[$fieldName]));
        } else {
            return ($this->rs[$fieldName]);
        }
    }

    function GetXML($fieldRoot) {

    }

    function GetFieldAsString($fieldName) {

    }

    function NumFields() {

    }

    function FieldName($fieldNumber) {

    }

    function scapeXMLChars($str) {

    }

    function GetPlainField($fieldName) {

    }

    function GoToRow($NumRow = 0) {

    }

    function FreeRst() {


    }

    function FilasAfectadas() {

    }

}
