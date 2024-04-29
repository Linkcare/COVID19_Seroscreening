<?php

class Database {

    /* @var DbManager $backend */
    private static $backend = null;

    /**
     * Function that initiates the DbMnager $backend variable
     *
     * @return boolean in order to check for the function's success
     */
    static public function init($connString = null) {
        $ret = null;
        try {
            $dbData = DbManager::init($connString);
            $dbData->ConnectServer();

            self::$backend = $dbData;
            $ret = true;
        } catch (Exception $e) {
            $ret = $e->getMessage();
        }

        return $ret;
    }

    /**
     * Returns the DbManager $backend instance in order to execute queries
     *
     * @return DbManager $backend instance
     */
    static public function getInstance() {
        return self::$backend;
    }
}
