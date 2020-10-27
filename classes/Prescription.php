<?php

class Prescription {
    private $valid = false;
    private $id;
    private $participantId;
    private $expirationDate;
    private $team;
    private $program;
    private $rounds = 1;
    private $checkDigit = "";
    private $withCheckDigit;

    /**
     *
     * @param string $str Semicolon separated string with the information aobut the prescription
     * @param boolean $allowParticipantId (default = false) If true, the value in $str can be a single value that will be interpreted as the
     *        ParticipantId
     */
    function __construct($str = null, $allowParticipantId = false) {
        $this->withCheckDigit = $GLOBALS['QR_WITH_CHECK_DIGIT'];
        if (!$str) {
            return;
        }
        $parts = explode(';', $str);
        if (count($parts) == 1) {
            // Old format with only participantId
            $this->participantId = $str;
            $this->valid = $allowParticipantId;
        } else {
            $ix = 0;
            if ($this->withCheckDigit) {
                // The Prescription has a check digit at the begining of the string
                $this->checkDigit = count($parts) > $ix ? $parts[$ix] : nil;
                $ix++;
            }
            $this->id = count($parts) > $ix ? $parts[$ix] : nil;
            $ix++;
            $this->team = count($parts) > $ix ? $parts[$ix] : nil;
            $ix++;
            $this->program = count($parts) > $ix ? $parts[$ix] : nil;
            $ix++;
            $this->participantId = count($parts) > $ix ? $parts[$ix] : nil;
            $ix++;
            $this->expirationDate = count($parts) > $ix ? $parts[$ix] : nil;
            $ix++;
            $this->rounds = count($parts) > $ix ? max(intval($parts[$ix]), 1) : 1;
            $this->valid = $this->id && $this->team && $this->program && $this->participantId && $this->expirationDate;
            if ($this->withCheckDigit) {
                // Verify the check digit
                $this->valid = $this->valid && $this->validateCheckDigit($str);
            }
        }
    }

    // **************************************************************
    // GETTERS
    // **************************************************************
    function getId() {
        return $this->id;
    }

    function getProgram() {
        return $this->program;
    }

    function getTeam() {
        return $this->team;
    }

    function getExpirationDate() {
        if ($this->expirationDate) {
            return $this->formatDate($this->expirationDate);
        }
        return null;
    }

    function getParticipantId() {
        return $this->participantId;
    }

    function getRounds() {
        return $this->rounds;
    }

    // **************************************************************
    // SETTERS
    // **************************************************************
    function setId($value) {
        $this->id = $value;
    }

    function setProgram($value) {
        $this->program = $value;
    }

    function setTeam($value) {
        $this->team = $value;
    }

    function setExpirationDate($value) {
        $this->expirationDate = $value;
    }

    function setParticipantId($value) {
        $this->participantId = $value;
    }

    function setRounds($value) {
        $this->rounds = max(intval($value), 1);
    }

    // **************************************************************
    // PUBLIC FUNCTIONS
    // **************************************************************
    /**
     * Returns true if the string used to create this Prescription is valid because the check digit provided is correct
     */
    public function isValid() {
        return $this->valid;
    }

    /**
     * Generates a JSON string with the information of the Prescription
     *
     * @return string
     */
    public function toJSON() {
        $obj = new StdClass();
        $obj->success = $this->valid ? 1 : 0;
        $obj->id = $this->id;
        $obj->program = $this->program;
        $obj->team = $this->team;
        $obj->expirationDate = $this->expirationDate;
        $obj->participantId = $this->participantId;
        $obj->rounds = $this->rounds;
        return json_encode($obj);
    }

    /**
     * Generates a string that can be used to generate a QR code
     * The string generated includes a check digit
     *
     * @return string
     */
    public function generateQR() {
        $parts = [];
        $parts[] = $this->id;
        $parts[] = $this->team;
        $parts[] = $this->program;
        $parts[] = $this->participantId;
        $parts[] = $this->expirationDate;
        $parts[] = $this->rounds;

        $str = implode(";", $parts);
        return $this->calculateCheckDigit($str) . ";" . $str;
    }

    // **************************************************************
    // PRIVATE FUNCTIONS
    // **************************************************************
    private function formatDate($str) {
        $year = substr($str, 0, 4);
        $month = substr($str, 4, 2);
        $day = substr($str, 6, 2);
        return "$year-$month-$day";
    }

    /**
     * Returns the check digit for a string
     * The calculation is done using a Luhn Mod N algorithm, using as base N=36 (numbers and A-Z uppercase letters)
     *
     * @param $str String for which the check digit will be calculated
     * @return string
     */
    private function calculateCheckDigit($str) {
        $LUHN_CHARACTERS = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $str = strtoupper($str);
        $cleanStr = "";
        for ($i = 0; $i < strlen($str); $i++) {
            if (strpos($LUHN_CHARACTERS, $str[$i]) !== false) {
                $cleanStr .= $str[$i];
            }
        }

        $LUHN_CHARACTERS = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $factor = 2;
        $sum = 0;
        $n = strlen($LUHN_CHARACTERS);

        // Starting from the right and working leftwards is easier since the initial "factor" will always be "2".
        for ($i = strlen($cleanStr) - 1; $i >= 0; $i--) {
            $codePoint = strpos($LUHN_CHARACTERS, $cleanStr[$i]);
            $addend = $factor * $codePoint;

            // Alternate the "factor" that each "codePoint" is multiplied by
            $factor = ($factor == 2) ? 1 : 2;

            // Sum the digits of the "addend" as expressed in base "n"
            $addend = intval($addend / $n) + ($addend % $n);
            $sum += $addend;
        }

        // Calculate the number that must be added to the "sum" to make it divisible by "n".
        $remainder = $sum % $n;
        $checkCodePoint = ($n - $remainder) % $n;

        return $LUHN_CHARACTERS[$checkCodePoint];
    }

    /**
     * Returns true if the string provided has a valid check digit
     * The string should have the following format: "dc;str", where:
     * - dc = is the check digit calculated for str
     * - str = the string for which the check digit is calculated
     *
     * @param string $str
     * @return boolean
     */
    private function validateCheckDigit($str) {
        $parts = explode(";", $str, 2);
        $cd = $parts[0];
        return $cd == $this->calculateCheckDigit($parts[1]);
    }
}
