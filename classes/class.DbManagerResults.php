<?php

class DbManagerResults {
    function NRows() {
    }

    function Next() {
        return (false);
    }

    /**
     * @return mixed
     */
    function GetField($fieldName) {
        return ("");
    }

    function GetLOBChunk($fieldName, $startPos, $lenght) {
        return null;
    }

    function GetAllFields() {
        return ("");
    }

    function GetFieldNULL($fieldName, $scaped) {
        return ("");
    }

    function GetFieldAsString($fieldName) {
        return ("");
    } //

    function GetXML($fieldRoot) {
        return ("");
    }

    function NumFields() {
        return (0);
    }

    function FieldName($fieldNumber) {
        return ("");
    }

    function GoToRow($NumRow) {
    }

    function FreeRst() {
    }

    function FilasAfectadas() {
        return (0);
    }
}
