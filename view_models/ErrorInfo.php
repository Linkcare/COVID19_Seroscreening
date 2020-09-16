<?php

class ErrorInfo {
    /* Error codes */
    const DB_CONNECTION_ERROR = "DB_CONNECTION_ERROR";
    const INVALID_KIT = "INVALID_KIT";
    const INVALID_STATUS = "INVALID_STATUS";

    /* Private members */
    private $errorCode;
    private $errorMessage;

    /**
     *
     * @param string $errorCode
     * @param string $errorMessage
     */
    public function __construct($errorCode, $errorMessage = null) {
        $this->errorCode = $errorCode;
        $this->errorMessage = $errorMessage;
    }

    /**
     *
     * @return string
     */
    public function getErrorCode() {
        return $this->errorCode;
    }

    /**
     *
     * @return string
     */
    public function getErrorMessage() {
        return $this->errorMessage;
    }
}