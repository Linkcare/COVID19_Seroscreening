<?php

class APIQR {
    private $type;
    private $properties = [];

    /**
     *
     * @param SimpleXMLElement $xmlNode
     * @return APIQR
     */
    static public function parseXML($xmlNode) {
        if (!$xmlNode) {
            return null;
        }
        $qr = new APIQR();
        $qr->type = trim($xmlNode->type);
        foreach ($xmlNode->children() as $property) {
            $name = $property->getName();
            if ($name == 'type') {
                continue;
            }
            $qr->properties[$name] = trim($property);
        }
        return $qr;
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
    public function getType() {
        return $this->type;
    }

    /**
     *
     * @return string
     */
    public function getProperty($name) {
        return $this->properties[$name];
    }
}