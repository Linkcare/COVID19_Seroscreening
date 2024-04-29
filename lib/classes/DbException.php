<?php

class DbException extends Exception {
    public $errorCode;

    /**
     *
     * @param string $errorCode
     * @param string $message
     * @param mixed $previous
     */
    public function __construct($errorCode, $message = null, $previous = null) {
        $this->errorCode = $errorCode;
        $this->message = $message;
        parent::__construct($this->message, null, $previous);
    }

    /**
     *
     * @return string
     */
    public function getErrorCode() {
        return $this->errorCode;
    }
}
