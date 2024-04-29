<?php

/**
 * Definition of the Database data models used by the Linkcare platform
 */
class DataModels {

    /** @var string */
    /**
     * Generates the structure of the data schema
     *
     * @param string $name Name assigned to the new DB schema
     * @return DbSchemaDefinition
     */
    static public function dataModel($name) {
        $tables = [];
        $columns = [];
        $indexes = [];
        $fks = [];
        $seqs = [];

        $columns[] = new DbColumnDefinition('ID_DESCRIPTION', DbDataTypes::BIGINT);
        $columns[] = new DbColumnDefinition('DESCRIPTION_KEY', DbDataTypes::VARCHAR, 100, null, false);
        $columns[] = new DbColumnDefinition('DESCRIPTION_GROUP', DbDataTypes::VARCHAR, 100, null, false);
        $indexes[] = new DbIndexDefinition('DESCRIPTIONS_GROUP_KEY_IDX', ['DESCRIPTION_GROUP', 'DESCRIPTION_KEY'], true);
        $indexes[] = new DbIndexDefinition('DESCRIPTIONS_KEY_IDX', ['DESCRIPTION_KEY'], true);
        $tables[] = new DbTableDefinition('DESCRIPTIONS', $columns, 'ID_DESCRIPTION', $indexes, true);
        $seqs[] = new DbSequenceDefinition("SEQ_DESCRIPTIONS");

        $columns = [];
        $indexes = null;
        $columns[] = new DbColumnDefinition('ID_DESCRIPTION', DbDataTypes::BIGINT, null, null, false);
        $columns[] = new DbColumnDefinition('ISO2_LANGUAGE', DbDataTypes::VARCHAR, 2, null, false);
        $columns[] = new DbColumnDefinition('DESCRIPTION', DbDataTypes::TEXT);
        $tables[] = new DbTableDefinition('DESCRIPTION_TRANSLATIONS', $columns, null, $indexes);
        $fks[] = new DbFKDefinition('FK_DESCRIPTIONS', 'DESCRIPTION_TRANSLATIONS', ['ID_DESCRIPTION'], 'DESCRIPTIONS', ['ID_DESCRIPTION']);

        $columns = [];
        $indexes = null;
        $columns[] = new DbColumnDefinition('ID_TRACKING', DbDataTypes::BIGINT);
        $columns[] = new DbColumnDefinition('CREATED', DbDataTypes::DATETIME, null, null, false);
        $columns[] = new DbColumnDefinition('ID_CASE', DbDataTypes::BIGINT);
        $columns[] = new DbColumnDefinition('ID_ADMISSION', DbDataTypes::BIGINT);
        $columns[] = new DbColumnDefinition('OUTCOME', DbDataTypes::TINYINT);
        $columns[] = new DbColumnDefinition('TEST_RESULT', DbDataTypes::TINYINT);
        $columns[] = new DbColumnDefinition('IP', DbDataTypes::VARCHAR, 32);
        $columns[] = new DbColumnDefinition('QR', DbDataTypes::VARCHAR, 2000);
        $columns[] = new DbColumnDefinition('ID_INSTANCE', DbDataTypes::VARCHAR, 64);
        $tables[] = new DbTableDefinition('GATEKEEPER_TRACKING', $columns, 'ID_TRACKING', $indexes, true);
        $seqs[] = new DbSequenceDefinition("SEQ_GATEKEEPER");

        $columns = [];
        $indexes = null;
        $columns[] = new DbColumnDefinition('KIT_ID', DbDataTypes::VARCHAR, 16);
        $columns[] = new DbColumnDefinition('MANUFACTURE_PLACE', DbDataTypes::VARCHAR, 512);
        $columns[] = new DbColumnDefinition('MANUFACTURE_DATE', DbDataTypes::DATETIME);
        $columns[] = new DbColumnDefinition('EXPIRATION', DbDataTypes::DATETIME);
        $columns[] = new DbColumnDefinition('BATCH_NUMBER', DbDataTypes::VARCHAR, 100);
        $columns[] = new DbColumnDefinition('STATUS', DbDataTypes::VARCHAR, 32);
        $columns[] = new DbColumnDefinition('ID_INSTANCE', DbDataTypes::VARCHAR, 100);
        $columns[] = new DbColumnDefinition('PROGRAM_CODE', DbDataTypes::VARCHAR, 100);
        $columns[] = new DbColumnDefinition('MANUFACTURER_NAME', DbDataTypes::VARCHAR, 100);
        $columns[] = new DbColumnDefinition('TEAM_CODE', DbDataTypes::VARCHAR, 100);
        $tables[] = new DbTableDefinition('KIT_INFO', $columns, 'KIT_ID', $indexes);
        $fks[] = new DbFKDefinition('FK_LC_INSTANCES', 'KIT_INFO', ['ID_INSTANCE'], 'LC_INSTANCES', ['ID_INSTANCE']);
        $fks[] = new DbFKDefinition('FK_LC_PROGRAMS', 'KIT_INFO', ['PROGRAM_CODE'], 'LC_PROGRAMS', ['PROGRAM_CODE']);

        $columns = [];
        $indexes = null;
        $columns[] = new DbColumnDefinition('ID_TRACKING', DbDataTypes::BIGINT);
        $columns[] = new DbColumnDefinition('CREATED', DbDataTypes::DATETIME, null, null, false);
        $columns[] = new DbColumnDefinition('ID_KIT', DbDataTypes::VARCHAR, 100, null, true);
        $columns[] = new DbColumnDefinition('ID_PRESCRIPTION', DbDataTypes::TEXT);
        $columns[] = new DbColumnDefinition('IP', DbDataTypes::VARCHAR, 32);
        $columns[] = new DbColumnDefinition('LINKCARE_URL', DbDataTypes::VARCHAR, 512);
        $columns[] = new DbColumnDefinition('KIT_STATUS', DbDataTypes::VARCHAR, 32);
        $columns[] = new DbColumnDefinition('ACTION_TYPE', DbDataTypes::VARCHAR, 32);
        $columns[] = new DbColumnDefinition('COUNTRY', DbDataTypes::VARCHAR, 128);
        $columns[] = new DbColumnDefinition('CITY', DbDataTypes::VARCHAR, 128);
        $tables[] = new DbTableDefinition('KIT_TRACKING', $columns, 'ID_TRACKING', $indexes, true);
        $seqs[] = new DbSequenceDefinition("SEQ_TRACKING");

        $columns = [];
        $indexes = null;
        $columns[] = new DbColumnDefinition('ID_INSTANCE', DbDataTypes::VARCHAR, 100, null, false);
        $columns[] = new DbColumnDefinition('URL', DbDataTypes::VARCHAR, 512, null, false);
        $tables[] = new DbTableDefinition('LC_INSTANCES', $columns, 'ID_INSTANCE', $indexes);

        $columns = [];
        $indexes = null;
        $columns[] = new DbColumnDefinition('PROGRAM_CODE', DbDataTypes::VARCHAR, 100);
        $columns[] = new DbColumnDefinition('COMMENTS', DbDataTypes::TEXT);
        $tables[] = new DbTableDefinition('LC_PROGRAMS', $columns, 'PROGRAM_CODE', $indexes);

        $db = new DbSchemaDefinition($name, $tables, $fks, $seqs);
        return $db;
    }
}