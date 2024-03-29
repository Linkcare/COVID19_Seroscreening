<?php

class APIForm {
    private $id;
    private $formCode;
    private $name;
    private $description;
    private $parentId;
    private $date;
    private $status;
    /** @var APIQuestion[] $questions */
    private $questions = null;
    /** @var LinkcareSoapAPI $api */
    private $api;

    public function __construct() {
        $this->api = LinkcareSoapAPI::getInstance();
    }

    /**
     *
     * @param SimpleXMLElement $xmlNode
     * @return APIForm
     */
    static public function parseXML($xmlNode) {
        if (!$xmlNode) {
            return null;
        }
        $form = new APIForm();
        $form->id = (string) $xmlNode->ref;
        if ($xmlNode->code) {
            $form->formCode = NullableString((string) $xmlNode->code);
        } else {
            $form->formCode = NullableString((string) $xmlNode->form_code);
        }

        // form_get_summary retuns the information of the FORM into th subnode "data"
        $formInfoNode = $xmlNode->data ? $xmlNode->data : $xmlNode;
        if ($formInfoNode->short_name) {
            // form_get() returns the name in node "short_name")
            $form->name = NullableString((string) $formInfoNode->short_name);
        } else {
            $form->name = NullableString((string) $formInfoNode->name);
        }
        $form->description = NullableString((string) $formInfoNode->description);
        $form->parentId = NullableInt((string) $formInfoNode->parent_id);
        $form->date = NullableString((string) $formInfoNode->date);
        $form->status = NullableString((string) $formInfoNode->status);

        $questions = [];
        if ($formInfoNode->questions) {
            foreach ($formInfoNode->questions->question as $qNode) {
                $questions[] = APIQuestion::parseXML($qNode);
            }
            $form->questions = array_filter($questions);
        }
        return $form;
    }

    /*
     * **********************************
     * GETTERS
     * **********************************
     */
    /**
     *
     * @return int
     */
    public function getId() {
        return $this->id;
    }

    /**
     *
     * @return string
     */
    public function getFormCode() {
        return $this->formCode;
    }

    /**
     *
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     *
     * @return string
     */
    public function getDescription() {
        return $this->description;
    }

    /**
     *
     * @return int
     */
    public function getParentId() {
        return $this->parentId;
    }

    /**
     *
     * @return string
     */
    public function getDate() {
        return $this->date;
    }

    /**
     *
     * @return string
     */
    public function getStatus() {
        return $this->status;
    }

    /**
     *
     * @return APIQuestion[]
     */
    public function getQuestions() {
        return $this->questions ?? [];
    }

    /*
     * **********************************
     * METHODS
     * **********************************
     */
    /**
     *
     * @param APIQuestion $q
     */
    public function addQuestion($q) {
        $this->questions[] = $q;
    }

    /**
     * Searches the QUESTION with the $questionId indicated
     *
     * @param int $questionId
     * @return APIQuestion
     */
    public function findQuestion($questionId) {
        if ($this->questions === null) {
            $f = $this->api->form_get_summary($this->id);
            $this->questions = $f->getQuestions();
        }
        foreach ($this->questions as $q) {
            if ($q->getQuestionTemplateId() == $questionId || $q->getItemCode() == $questionId) {
                return $q;
            }
        }

        return null;
    }
}