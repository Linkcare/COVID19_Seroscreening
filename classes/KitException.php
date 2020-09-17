<?php

class KitException extends Exception {
    /* @var ErrorInfo $error */
    public $error;

    public function __construct($errorCode, $errorMessage = null, $previous = null) {
        $this->error = new ErrorInfo($errorCode, $errorMessage);
        parent::__construct($this->error->getErrorMessage(), null, null);
    }
}
