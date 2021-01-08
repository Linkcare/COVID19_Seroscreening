<?php

class KitInfo {
    const STATUS_DISCARDED = "DISCARDED";
    const STATUS_NOT_USED = "NOT_USED";
    const STATUS_ASSIGNED = "ASSIGNED";
    const STATUS_USED = "USED";
    const STATUS_EXPIRED = "EXPIRED";
    const STATUS_PROCESSING = "PROCESSING";
    const STATUS_PROCESSING_5MIN = "PROCESSING_5MIN";
    const STATUS_INSERT_RESULTS = "INSERT_RESULTS";
    const VALID_STATUS = [self::STATUS_DISCARDED, self::STATUS_NOT_USED, self::STATUS_ASSIGNED, self::STATUS_EXPIRED, self::STATUS_USED,
            self::STATUS_PROCESSING, self::STATUS_PROCESSING_5MIN, self::STATUS_INSERT_RESULTS];

    // Actions on a Kit
    const ACTION_SCANNED = "SCANNED";
    const ACTION_PROCESSED = "PROCESSED";

    /* Private members */
    private $id;
    private $manufacture_place;
    private $manufacture_date;
    private $batch_number;
    private $exp_date;
    private $status;
    private $programCode;
    private $instance_url;
    private $prescriptionId;

    /**
     * Returns the corresponding kit based in the id that is obtained from the url
     *
     * @return KitInfo object with the data from the database of said kit, or null if the kit doesn't exist or there was no id specified at the URL
     */
    static function getInstance($kitId) {
        $kit = null;

        if (!empty($kitId)) {
            /* Obtain the id as a parameter and its corresponding info from the DB */
            $id = [':id' => $kitId];

            $sql = "SELECT
                    ki.KIT_ID,
                    ki.MANUFACTURE_PLACE,
                    ki.MANUFACTURE_DATE,
                    ki.EXPIRATION,
                    ki.BATCH_NUMBER,
                    ki.STATUS,
                    ki.PROGRAM_CODE,
                    li.URL
                FROM
                    KIT_INFO ki
                LEFT JOIN LC_INSTANCES li ON ki.ID_INSTANCE = li.ID_INSTANCE
                WHERE ki.KIT_ID = :id";

            $result = Database::getInstance()->ExecuteBindQuery($sql, $id);

            if ($result->Next()) {
                $kit = new KitInfo();
                $kit->setId($result->GetField('KIT_ID'));
                $kit->setManufacture_place($result->GetField('MANUFACTURE_PLACE'));
                $kit->setManufacture_date(substr($result->GetField('MANUFACTURE_DATE'), 0, 16));
                $kit->setBatch_number($result->GetField('BATCH_NUMBER'));
                $kit->setExp_date(substr($result->GetField('EXPIRATION'), 0, 10));
                $kit->setStatus($result->GetField("STATUS"));
                $kit->setProgramCode($result->GetField("PROGRAM_CODE"));
                $kit->setInstance_url($result->GetField('URL'));
            }
        }
        return $kit;
    }

    /* Get methods */

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
    public function getManufacture_place() {
        return $this->manufacture_place;
    }

    /**
     *
     * @return string
     */
    public function getManufacture_date() {
        return $this->manufacture_date;
    }

    /**
     *
     * @return string
     */
    public function getBatch_number() {
        return $this->batch_number;
    }

    /**
     *
     * @return string
     */
    public function getExp_date() {
        return $this->exp_date;
    }

    /**
     *
     * @return string
     */
    public function getStatus() {
        if ($this->status === null) {
            return self::STATUS_NOT_USED;
        }
        return $this->status;
    }

    /**
     *
     * @return string
     */
    public function getProgramCode() {
        return $this->programCode;
    }

    /**
     *
     * @return string
     */
    public function getInstance_url() {
        return $this->instance_url;
    }

    /**
     *
     * @return string
     */
    public function getPrescriptionId() {
        return $this->prescriptionId;
    }

    /* Set methods */

    /**
     *
     * @param string $id
     */
    public function setId($id) {
        $this->id = $id;
    }

    /**
     *
     * @param string $manufacture_place
     */
    public function setManufacture_place($manufacture_place) {
        $this->manufacture_place = $manufacture_place;
    }

    /**
     *
     * @param string $manufacture_date
     */
    public function setManufacture_date($manufacture_date) {
        $this->manufacture_date = $manufacture_date;
    }

    /**
     *
     * @param string $batch_number
     */
    public function setBatch_number($batch_number) {
        $this->batch_number = $batch_number;
    }

    /**
     *
     * @param string $exp_date
     */
    public function setExp_date($exp_date) {
        $this->exp_date = $exp_date;
    }

    /**
     *
     * @param string $status
     */
    public function setStatus($status) {
        $this->status = $status;
    }

    /**
     *
     * @param string $programCode
     */
    public function setProgramCode($programCode) {
        $this->programCode = $programCode;
    }

    /**
     *
     * @param string $instance_url
     */
    public function setInstance_url($instance_url) {
        $this->instance_url = $instance_url;
    }

    /**
     *
     * @param string $instance_url
     */
    public function setPrescriptionId($prescriptionId) {
        $this->prescriptionId = $prescriptionId;
    }

    /* Other methods */

    /**
     * Function to compose the URL that will be the instance URL of the kit used at the PROCEED button
     */
    public function generateURLtoLC2() {
        $urlStart = '';
        if (strpos($this->instance_url, '?') === false) {
            $urlStart = $this->instance_url . '?';
        } else {
            $urlStart = $this->instance_url . '&';
        }

        switch ($this->getStatus()) {
            case KitInfo::STATUS_NOT_USED :
                $urlStart .= 'service_name=seroscreening';
                $urlStart .= '&kit_id=' . $this->getId();
                $urlStart .= '&manufacture_date=' . $this->getManufacture_date();
                $urlStart .= '&manufacture_place=' . $this->getManufacture_place();
                $urlStart .= '&expiration_date=' . $this->getExp_date();
                $urlStart .= '&batch_number=' . $this->getBatch_number();
                $urlStart .= '&program=' . $this->getProgramCode();
                break;
            default :
                $urlStart .= 'kit_id=' . $this->getId();
                break;
        }

        return $urlStart;
    }

    /**
     * Function to change the status of a kit and update it at the DB
     *
     * @param string $status new status
     */
    public function changeStatus($status) {
        // If the new status is the same as the old one, exit the function
        if ($this->status == $status) {
            return;
        }
        // If the aim is to set a new status that is not declared, exit the function as well
        if (!in_array($status, self::VALID_STATUS)) {
            return;
        }

        if (!Database::getInstance()) {
            return;
        }

        if ($this->status !== null || $status != self::STATUS_NOT_USED) {
            // Do not update DATABASE if the current status is NULL and we are setting the status=STATUS_NOT_USED,
            // because they are considered to be the same
            $arrVariables[":status"] = $status;
            $arrVariables[":id"] = $this->getId();
            $sql = "UPDATE KIT_INFO SET STATUS = :status WHERE KIT_ID = :id";
            Database::getInstance()->ExecuteBindQuery($sql, $arrVariables);
        }

        // Finally modify the status of the object itself
        $this->setStatus($status);
    }

    /**
     * Generates an entry in the table KIT_TRACKING to know who is creating Admissions in Linkcare with a KIT_ID
     *
     * @param string $prescriptionId
     */
    public function storeTracking($action, $prescriptionId) {
        if (!$GLOBALS["KIT_TRACKING"]) {
            return;
        }
        $tz_object = new DateTimeZone('UTC');
        $datetime = new DateTime();
        $datetime->setTimezone($tz_object);
        $today = $datetime->format('Y\-m\-d\ H:i:s');

        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ipAddress = $_SERVER['REMOTE_ADDR'];
        }

        try {
            $ipdat = @json_decode(file_get_contents("http://www.geoplugin.net/json.gp?ip=" . $ipAddress));
        } catch (Exception $e) {
            $ipdat = null;
        }

        $arrVariables[':id'] = self::getNextTrackingId();
        $arrVariables[':created'] = $today;
        $arrVariables[':kitId'] = $this->getId();
        $arrVariables[':kitStatus'] = $this->getStatus();
        $arrVariables[':actionType'] = $action;
        $arrVariables[':prescriptionId'] = $prescriptionId;
        $arrVariables[':ipAddress'] = $ipAddress;
        $arrVariables[':targetUrl'] = $this->getInstance_url();
        $arrVariables[':countryName'] = $ipdat ? $ipdat->geoplugin_countryName : null;
        $arrVariables[':cityName'] = $ipdat ? $ipdat->geoplugin_city : null;
        $sql = "INSERT INTO KIT_TRACKING (ID_TRACKING, CREATED, ID_KIT, KIT_STATUS, ACTION_TYPE, ID_PRESCRIPTION, IP, LINKCARE_URL, COUNTRY, CITY) VALUES (:id, :created, :kitId, :kitStatus, :actionType, :prescriptionId, :ipAddress, :targetUrl, :countryName, :cityName)";
        Database::getInstance()->ExecuteBindQuery($sql, $arrVariables);
    }

    static private function getNextTrackingId() {
        // if ($GLOBALS["BBDD"] == "ORACLE") {
        $sql = "SELECT SEQ_TRACKING.NEXTVAL AS NEXTV FROM DUAL";
        $rst = Database::getInstance()->ExecuteQuery($sql);
        $rst->Next();
        return $rst->GetField("NEXTV");
    }
}
