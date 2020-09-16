<?php

class APITeam {
    private $id;
    private $code;
    private $name;
    private $unit;

    /**
     *
     * @param SimpleXMLElement $xmlNode
     * @return APITeam
     */
    static public function parseXML($xmlNode) {
        if (!$xmlNode) {
            return null;
        }
        $team = new APITeam();
        $team->id = (string) $xmlNode->ref;
        $team->code = (string) $xmlNode->code;
        $team->name = (string) $xmlNode->name;
        $team->unit = (string) $xmlNode->unit;
        return $team;
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
}