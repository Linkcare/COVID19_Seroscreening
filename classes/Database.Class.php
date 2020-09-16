<?php
include_once ("class.DbManagerOracle.php");

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
            self::$backend = new DbManagerOracle();
            self::$backend->setURI($connString);
            self::$backend->ConnectServer(false);

            /* Fix the format that the obtained DATE fields from the DB will have */
            $sql = "ALTER SESSION SET NLS_DATE_FORMAT = 'yyyy-mm-dd hh24:mi:ss'";
            self::getInstance()->ExecuteQuery($sql);

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
