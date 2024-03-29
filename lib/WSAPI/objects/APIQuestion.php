<?php

class APIQuestion {
    const TYPE_NUMERICAL = 'NUMERICAL';
    const TYPE_BOOLEAN = 'BOOLEAN';
    const TYPE_TEXT = 'TEXT';
    const TYPE_TEXT_AREA = 'TEXT_AREA';
    const TYPE_STATIC_TEXT = 'STATIC_TEXT';
    const TYPE_SELECT = 'SELECT';
    const TYPE_DATE = 'DATE';
    const TYPE_TIME = 'TIME';
    const TYPE_HORIZONTAL_CHECK = 'HORIZONTAL_CHECK';
    const TYPE_VERTICAL_CHECK = 'VERTICAL_CHECK';
    const TYPE_VERTICAL_RADIO = 'VERTICAL_RADIO';
    const TYPE_HORIZONTAL_RADIO = 'HORIZONTAL_RADIO';
    const TYPE_FORM = 'FORM';
    const TYPE_CODE = 'CODE';
    const TYPE_GRAPH = 'GRAPH';
    const TYPE_FILE = 'FILE';
    const TYPE_ACTION = 'ACTION';
    const TYPE_LINK = 'LINK';
    const TYPE_EDITABLE_STATIC_TEXT = 'TEXT_AREA';
    const TYPE_HTML = 'HTML';
    const TYPE_JSON = 'JSON';
    const TYPE_DEVICE = 'DEVICE';
    const TYPE_AGE = 'AGE';
    const TYPE_SLIDER = 'VAS';
    const TYPE_MULTIMEDIA = 'MULTIMEDIA';
    const TYPE_GEOLOCATION = 'GEOLOCATION';
    const TYPE_CASE_DATA = 'CASE_DATA';
    const OPTIONS_TYPES = [self::TYPE_BOOLEAN, self::TYPE_SELECT, self::TYPE_HORIZONTAL_CHECK, self::TYPE_VERTICAL_CHECK, self::TYPE_VERTICAL_RADIO,
            self::TYPE_HORIZONTAL_RADIO];

    // Private members
    private $id;
    private $itemCode;
    private $questionTemplateId;
    private $code;
    private $name;
    private $unit;
    private $order;
    private $row;
    private $column;
    private $decimals;
    private $mandatory;
    private $description;
    private $descriptionOnEdit;
    private $constraint;
    private $dataCode;
    private $type;
    private $value;
    private $valueDescription;

    /**
     *
     * @param SimpleXMLElement $xmlNode
     * @return APIQuestion
     */
    static public function parseXML($xmlNode) {
        if (!$xmlNode) {
            return null;
        }
        $question = new APIQuestion();
        $question->id = NullableString($xmlNode->question_id);
        $question->itemCode = NullableString($xmlNode->item_code);
        $question->questionTemplateId = NullableString($xmlNode->question_template_id);

        $question->order = intval((string) $xmlNode->order);
        $question->row = NullableInt((string) $xmlNode->row);
        $question->column = NullableInt((string) $xmlNode->column);
        $question->decimals = NullableInt((string) $xmlNode->num_dec);
        $question->mandatory = textToBool((string) $xmlNode->mandatory);
        $question->description = NullableString($xmlNode->description);
        $question->descriptionOnEdit = NullableString($xmlNode->description_onedit);
        $question->constraint = NullableString($xmlNode->constraint);
        $question->dataCode = NullableString($xmlNode->data_code);
        $question->type = NullableString($xmlNode->type);
        $question->value = NullableString($xmlNode->value);
        $question->valueDescription = NullableString($xmlNode->value_description);
        return $question;
    }

    /*
     * **********************************
     * GETTERS
     * **********************************
     */

    /**
     *
     * @return string
     */
    public function getId() {
        return $this->id;
    }

    /**
     *
     * @return string
     */
    public function getItemCode() {
        return $this->itemCode;
    }

    /**
     *
     * @return string
     */
    public function getQuestionTemplateId() {
        return $this->questionTemplateId;
    }

    /**
     *
     * @return string
     */
    public function getCode() {
        return $this->code;
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
    public function getUnit() {
        return $this->unit;
    }

    /**
     *
     * @return int
     */
    public function getOrder() {
        return $this->order;
    }

    /**
     *
     * @return int
     */
    public function getRow() {
        return $this->row;
    }

    /**
     *
     * @return int
     */
    public function getColumn() {
        return $this->column;
    }

    /**
     *
     * @return int
     */
    public function getDecimals() {
        return $this->decimals;
    }

    /**
     *
     * @return boolean
     */
    public function getMandatory() {
        return $this->mandatory;
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
     * @return string
     */
    public function getDescriptionOnEdit() {
        return $this->descriptionOnEdit;
    }

    /**
     *
     * @return string
     */
    public function getConstraint() {
        return $this->constraint;
    }

    /**
     *
     * @return string
     */
    public function getDataCode() {
        return $this->dataCode;
    }

    /**
     *
     * @return string
     */
    public function getType() {
        return $this->type;
    }

    /**
     *
     * @return string
     */
    public function getValue() {
        return $this->value;
    }

    /**
     *
     * @return string
     */
    public function getValueDescription() {
        return $this->valueDescription;
    }

    /*
     * **********************************
     * SETTERS
     * **********************************
     */

    /**
     * Sets the value of the question
     *
     * @param string $value
     */
    public function setValue($value) {
        $this->value = $value;
    }

    /*
     * **********************************
     * METHODS
     * **********************************
     */
    /**
     *
     * @param XMLHelper $xml
     * @param SimpleXMLElement $parentNode
     * @return SimpleXMLElement
     */
    public function toXML($xml, $parentNode) {
        if ($parentNode === null) {
            $parentNode = $xml->rootNode;
        }

        $xml->createChildNode($parentNode, "question_id", $this->getId());
        if (in_array($this->getType(), self::OPTIONS_TYPES)) {
            $xml->createChildNode($parentNode, "value", '');
            $xml->createChildNode($parentNode, "option_id", $this->getValue());
        } else {
            $xml->createChildNode($parentNode, "value", $this->getValue());
            $xml->createChildNode($parentNode, "option_id", '');
        }

        return $parentNode;
    }
}