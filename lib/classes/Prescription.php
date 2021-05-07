<?php

class Prescription {
    // Class members
    private $valid = false;
    private $type;
    /**
     * prescriptionData is a JSON object that can contain the following properties (all properties are optional):
     * <ul>
     * <li>id</li>
     * <li>participant</li>
     * <li>expiration</li>
     * <li>team</li>
     * <li>program</li>
     * <li>rounds (default = 0)</li>
     * <li>admission</li>
     * <li>name</li>
     * <li>surname</li>
     * <li>email</li>
     * <li>phone</li>
     * </ul>
     *
     * @var StdClass
     */
    private $prescriptionData;
    private $checkDigit = "";
    private $withCheckDigit;

    /**
     *
     * @param string $prescriptionStr Semicolon separated string with the information aobut the prescription
     * @param boolean $allowParticipantId (default = false) If true, the value in $str can be a single value that will be interpreted as the
     *        ParticipantId
     */
    function __construct($prescriptionStr = null, $participant = null) {
        $this->withCheckDigit = $GLOBALS['QR_WITH_CHECK_DIGIT'];
        $this->prescriptionData = new StdClass();
        $this->prescriptionData->rounds = 0;

        if (!$prescriptionStr && !$participant) {
            return;
        }
        if (startsWith('adm=', $prescriptionStr)) {
            $parts = explode(';', $prescriptionStr);
            // ePrescription with the ADMISSION information
            $vars = explode('=', $parts[0]);
            $this->prescriptionData->admission = $vars[1];
            foreach ($parts as $v) {
                $param = explode('=', $v);
                switch ($param[0]) {
                    case 'tc' :
                        $this->prescriptionData->team = $param[1];
                        break;
                    case 'pc' :
                        $this->prescriptionData->program = $param[1];
                        break;
                    case 'n' :
                        $this->prescriptionData->name = $param[1];
                        break;
                }
            }
            $this->valid = $this->prescriptionData->admission != '';
        } else {
            if ($prescriptionStr) {
                $pd = json_decode(base64_decode($prescriptionStr));
                if ($pd) {
                    $this->prescriptionData = $pd;
                    $this->valid = (trim($pd->id) && trim($pd->program)) || trim($pd->admission);
                }
            } elseif ($participant) {
                // Old format with only participantId
                $this->prescriptionData->participant = $participant;
                $this->valid = true;
            }
        }
    }

    // **************************************************************
    // GETTERS
    // **************************************************************
    function getType() {
        return $this->type;
    }

    function getId() {
        return trim($this->prescriptionData->id);
    }

    function getProgram() {
        return trim($this->prescriptionData->program);
    }

    function getTeam() {
        return trim($this->prescriptionData->team);
    }

    function getExpirationDate() {
        return trim($this->prescriptionData->expiration);
    }

    // The PARTICIPANT_REF is an IDENTIFIER with format xxxxx@team_code
    function getParticipantId($teamCode) {
        $participantId = trim($this->prescriptionData->participant);
        if ($participantId && $teamCode && strpos($participantId, '@') === false) {
            // If the TEAM CODE is not present in the participant ID, then add the $teamCode provided
            $participantId = $participantId . '@' . $teamCode;
        }
        return $participantId;
    }

    function getRounds() {
        return $this->prescriptionData->rounds;
    }

    function getAdmissionId() {
        return $this->prescriptionData->admission;
    }

    /**
     * Returns the full name of the patient (name + surname)
     *
     * @return string
     */
    function getFullName() {
        return trim($this->prescriptionData->name . ' ' . $this->prescriptionData->surname);
    }

    function getName() {
        return trim($this->prescriptionData->name);
    }

    function getSurname() {
        return trim($this->prescriptionData->surname);
    }

    function getEmail() {
        return trim($this->prescriptionData->email);
    }

    function getPhone() {
        return trim($this->prescriptionData->phone);
    }

    /**
     *
     * @return StdClass
     */
    function getPrescriptionData() {
        return $this->prescriptionData;
    }

    // **************************************************************
    // SETTERS
    // **************************************************************
    function setId($value) {
        $this->prescriptionData->id = trim($value);
    }

    function setProgram($value) {
        $this->prescriptionData->program = trim($value);
    }

    function setTeam($value) {
        $this->prescriptionData->team = trim($value);
    }

    function setExpirationDate($value) {
        $this->prescriptionData->expiration = trim($value);
    }

    function setParticipantId($value) {
        $this->prescriptionData->participant = trim($value);
    }

    function setRounds($value) {
        $this->prescriptionData->rounds = max(intval($value), 1);
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
        $obj->id = $this->prescriptionData->id;
        $obj->program = $this->prescriptionData->program;
        $obj->team = $this->prescriptionData->team;
        $obj->expirationDate = $this->prescriptionData->expiration;
        $obj->participantId = $this->prescriptionData->participant;
        $obj->rounds = $this->prescriptionData->rounds;
        $obj->name = $this->getFullName();
        $obj->email = $this->prescriptionData->email;
        $obj->phone = $this->prescriptionData->phone;
        $obj->admissionId = $this->prescriptionData->admission;

        $obj->success = $this->valid ? 1 : 0;
        $obj->type = $this->type;
        return json_encode($obj);
    }

    /**
     * Generates a string that can be used to generate a QR code
     * The string generated includes a check digit
     *
     * @return string
     */
    public function generateQR() {
        return base64_encode(json_encode($this->prescriptionData));
    }

    // **************************************************************
    // PRIVATE FUNCTIONS
    // **************************************************************
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
}
