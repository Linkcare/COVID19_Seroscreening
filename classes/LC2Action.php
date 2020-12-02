<?php

class LC2Action {
    const ACTION_REDIRECT_TO_TASK = "SHOW_TASK";
    const ACTION_REDIRECT_TO_CASE = "SHOW_CASE_TASK_LIST";
    const ACTION_REDIRECT_TO_FORM = "SHOW_FORM";
    const ACTION_ERROR_MSG = "ERROR_MSG";
    private $action;
    private $caseId;
    private $taskId;
    private $formId;
    private $admissionId;
    private $errorMessage;

    public function __construct($actionType = null) {
        $this->setActionType($actionType);
    }

    public function setActionType($actionType) {
        $this->action = $actionType;
    }

    /**
     *
     * @param int $taskId
     */
    public function setTaskId($taskId) {
        $this->taskId = $taskId;
    }

    /**
     *
     * @param int $formId
     */
    public function setFormId($formId) {
        $this->formId = $formId;
    }

    /**
     *
     * @param int $caseId
     */
    public function setCaseId($caseId) {
        $this->caseId = $caseId;
    }

    /**
     *
     * @param int $admissionId
     */
    public function setAdmissionId($admissionId) {
        $this->admissionId = $admissionId;
    }

    /**
     *
     * @param string $errorMsg
     */
    public function setErrorMessage($errorMsg) {
        $this->errorMessage = $errorMsg;
    }

    /*
     * **********************************
     * METHODS
     * **********************************
     */

    /**
     * Generates a JSON string that can be passed to LC2 with instructions about the action
     */
    public function toString() {
        $action = new stdClass();
        $action->action = $this->action;
        if ($this->taskId) {
            $action->task_id = $this->taskId;
        }
        if ($this->formId) {
            $action->form_id = $this->formId;
        }
        if ($this->caseId) {
            $action->case_id = $this->caseId;
        }
        if ($this->admissionId) {
            $action->admission_id = $this->admissionId;
        }

        if ($this->errorMessage) {
            $action->error_message = $this->errorMessage;
        }

        return json_encode($action);
    }
}