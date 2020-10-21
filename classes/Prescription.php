<?php

class Prescription {
    private $id;
    private $participantId;
    private $expirationDate;
    private $team;
    private $program;
    private $rounds;

    function __construct($str) {
        $parts = explode(';', $str);
        if (count($parts) == 1) {
            // Old format with only participantId
            $this->participantId = $str;
        } else {
            $this->id = count($parts) > 0 ? $parts[0] : nil;
            $this->team = count($parts) > 1 ? $parts[1] : nil;
            $this->program = count($parts) > 2 ? $parts[2] : nil;
            $this->participantId = count($parts) > 3 ? $parts[3] : nil;
            $this->expirationDate = count($parts) > 4 ? $this->formatDate($parts[4]) : nil;
            $this->rounds = count($parts) > 5 ? max(intval($parts[5]), 1) : 1;
        }
    }

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
        return $this->expirationDate;
    }

    function getParticipantId() {
        return $this->participantId;
    }

    function getRounds() {
        return $this->rounds;
    }

    private function formatDate($str) {
        $year = substr($str, 0, 4);
        $month = substr($str, 4, 2);
        $day = substr($str, 6, 2);
        return "$year-$month-$day";
    }

    public function toJSON() {
        $obj = new StdClass();
        $obj->success = 1;
        $obj->id = $this->id;
        $obj->program = $this->program;
        $obj->team = $this->team;
        $obj->expirationDate = $this->expirationDate;
        $obj->participantId = $this->participantId;
        $obj->rounds = $this->rounds;
        return json_encode($obj);
    }
}