<?php

class KitInfo {
    const STATUS_DISCARDED = "DISCARDED";
    const STATUS_NOT_USED = "READY";
    const STATUS_ASSIGNED = "ASSIGNED";
    const STATUS_USED = "USED";
    const STATUS_EXPIRED = "EXPIRED";

    /* Private members */
    private $id;
    private $manufacture_place;
    private $manufacture_date;
    private $batch_number;
    private $exp_date;
    private $status;
    private $instance_url;

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

    /* Other methods */

    /**
     * Function to compose the URL that will be the instance URL of the kit used at the PROCEED button
     */
    public function generateURLtoLC2() {
        if ($this->getStatus() === KitInfo::STATUS_NOT_USED) {}
        if (strpos($this->getInstance_url(), '/?') === false) {
            $urlStart = $this->getInstance_url() . '/?';
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
}
