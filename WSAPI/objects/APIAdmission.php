<?php

class APIAdmission {
    // Status constants
    const STATUS_INCOMPLETE = "INCOMPLETE";
    const STATUS_ACTIVE = "ACTIVE";
    const STATUS_REJECTED = "REJECTED";
    const STATUS_DISCHARGED = "DISCHARGED";
    const STATUS_PAUSED = "PAUSED";
    const STATUS_ENROLLED = "ENROLLED";

    // Private members
    private $id;
    private $caseId;
    private $enrolDate;
    private $admissionDate;
    private $dischargeRequestDate;
    private $dischargeDate;
    private $suspendedDate;
    private $rejectedDate;
    private $status;
    private $dateToDisplay;
    private $ageToDisplay;
    private $subscription;

    /**
     *
     * @param SimpleXMLElement $xmlNode
     * @return APIAdmission
     */
    static public function parseXML($xmlNode) {
        if (!$xmlNode) {
            return null;
        }
        $admission = new APIAdmission();
        $admission->id = (string) $xmlNode->ref;
        if ($xmlNode->data) {
            if ($xmlNode->data->case) {
                $admission->caseId = NullableString($xmlNode->data->case->ref);
            }
            $admission->enrolDate = NullableString($xmlNode->data->enrol_date);
            $admission->admissionDate = NullableString($xmlNode->data->admission_date);
            $admission->dischargeRequestDate = NullableString($xmlNode->data->discharge_request_date);
            $admission->dischargeDate = NullableString($xmlNode->data->discharge_date);
            $admission->suspendedDate = NullableString($xmlNode->data->suspended_date);
            $admission->rejectedDate = NullableString($xmlNode->data->rejected_date);
            $admission->status = NullableString($xmlNode->data->status);
            $admission->dateToDisplay = NullableString($xmlNode->data->date_to_display);
            $admission->ageToDisplay = NullableInt($xmlNode->data->age_to_display);
            if ($xmlNode->data->subscription) {
                $admission->subscription = APISubscription::parseXML($xmlNode->data->subscription);
            }
        }
        return $admission;
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
    public function getCaseId() {
        return $this->caseId;
    }

    /**
     *
     * @return string
     */
    public function getEnrolDate() {
        return $this->enrolDate;
    }

    /**
     *
     * @return string
     */
    public function getAdmissionDate() {
        return $this->admissionDate;
    }

    /**
     *
     * @return string
     */
    public function getDischargeRequestDate() {
        return $this->dischargeRequestDate;
    }

    /**
     *
     * @return string
     */
    public function getDischargeDate() {
        return $this->dischargeDate;
    }

    /**
     *
     * @return string
     */
    public function getSuspendedDate() {
        return $this->suspendedDate;
    }

    /**
     *
     * @return string
     */
    public function getRejectedDate() {
        return $this->rejectedDate;
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
    public function getDateToDisplay() {
        return $this->dateToDisplay;
    }

    /**
     *
     * @return int
     */
    public function getAgeToDisplay() {
        return $this->ageToDisplay;
    }

    /**
     *
     * @return APISubscription
     */
    public function getSubscription() {
        return $this->subscription;
    }
}