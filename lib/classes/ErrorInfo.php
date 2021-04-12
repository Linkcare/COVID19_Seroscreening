<?php

class ErrorInfo {
    /* Error codes */
    const DB_CONNECTION_ERROR = "DB_CONNECTION_ERROR";
    const INVALID_KIT = "INVALID_KIT";
    const KIT_EXPIRED = "KIT_EXPIRED";
    const INVALID_STATUS = "INVALID_STATUS";
    const KIT_ALREADY_USED = "KIT_ALREADY_USED";
    const MAX_ROUNDS_EXCEEDED = "MAX_ROUNDS_EXCEEDED";
    const PRESCRIPTION_EXPIRED = "PRESCRIPTION_EXPIRED";
    const PRESCRIPTION_MISSING = "PRESCRIPTION_MISSING";
    const PRESCRIPTION_WRONG_FORMAT = "PRESCRIPTION_WRONG_FORMAT";
    const SUBSCRIPTION_NOT_FOUND = "SUBSCRIPTION_NOT_FOUND";
    const PRESCRIPTION_ALREADY_USED = "PRESCRIPTION_ALREADY_USED";
    const ADMISSION_ACTIVE = "ADMISSION_ACTIVE";
    const ADMISSION_NOT_FOUND = "ADMISSION_NOT_FOUND";
    const ADMISSION_INCOMPLETE = "ADMISSION_INCOMPLETE";

    /* Private members */
    private $errorCode;
    private $errorMessage;

    /**
     *
     * @param string $errorCode
     * @param string $errorMessage
     */
    public function __construct($errorCode = null, $errorMessage = null) {
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
        if (!$this->errorMessage && $this->getErrorCode()) {
            $this->errorMessage = Localization::translateError($this->getErrorCode());
        }
        return $this->errorMessage;
    }
}
