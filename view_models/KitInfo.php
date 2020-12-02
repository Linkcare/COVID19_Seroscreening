<?php

class KitInfo {
    const STATUS_DISCARDED = "DISCARDED";
    const STATUS_NOT_USED = "READY";
    const STATUS_ASSIGNED = "ASSIGNED";
    const STATUS_USED = "USED";
    const STATUS_EXPIRED = "EXPIRED";
    const VALID_STATUS = [self::STATUS_DISCARDED, self::STATUS_NOT_USED, self::STATUS_ASSIGNED, self::STATUS_EXPIRED, self::STATUS_USED];

    /* Private members */
    private $id;
    private $manufacture_place;
    private $manufacture_date;
    private $batch_number;
    private $exp_date;
    private $status;
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
        return $this->status;
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
        if ($this->getStatus() === KitInfo::STATUS_NOT_USED) {}
        if (strpos($this->getInstance_url(), '?') === false) {
            $urlStart = $this->getInstance_url() . '?';
        } else {
            $urlStart = $this->getInstance_url() . '&';
        }

        switch ($this->getStatus()) {
            case KitInfo::STATUS_ASSIGNED :
                $this->setInstance_url($urlStart . 'kit_id=' . $this->getId());
                break;
            case KitInfo::STATUS_NOT_USED :
                $this->setInstance_url(
                        $urlStart . 'service_name=seroscreening' . '&kit_id=' . $this->getId() . '&manufacture_date=' . $this->getManufacture_date() .
                        '&manufacture_place=' . $this->getManufacture_place() . '&expiration_date=' . $this->getExp_date() . '&batch_number=' .
                        $this->getBatch_number());
                break;
        }
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
}
