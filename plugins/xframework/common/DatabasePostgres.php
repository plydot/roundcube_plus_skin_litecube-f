<?php
namespace XFramework;

/**
 * Roundcube Plus Framework plugin.
 *
 * This class provides functions that contain Postgres-specific queries.
 *
 * Copyright 2018, Tecorama LLC.
 *
 * @license Commercial. See the LICENSE file for details.
 */

require_once(__DIR__ . "/Database.php");
require_once(__DIR__ . "/DatabaseInterface.php");

class DatabasePostgres extends Database implements DatabaseInterface
{
    /**
     * Returns the columns in the database table.
     * We're not using $this->rcmail->db->list_cols($table) because in some cases it doesn't return reliable results.
     *
     * @param string $table
     * @param bool $addPrefix
     * @return array
     */
    public function getColumns($table, $addPrefix = true)
    {
        $all = $this->all(
            "SELECT column_name FROM information_schema.columns WHERE table_name = ?",
            [$addPrefix ? $this->rcmail->config->get("db_prefix") . $table : $table]
        );

        if (!is_array($all)) {
            return array();
        }

        $columns = array();

        foreach ($all as $item) {
            $columns[] = $item["column_name"];
        }

        return $columns;

    }

    /**
     * Returns the names of all the tables in the database.
     *
     * @return array
     */
    public function getTables()
    {
        $result = array();

        $tables = $this->all(
            "SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname != 'pg_catalog' AND schemaname != 'information_schema'"
        );

        if (is_array($tables)) {
            foreach ($tables as $table) {
                if ($values = array_values($table)) {
                    $result[] = $values[0];
                }
            }
        }

        return $result;
    }

    /**
     * Checks if a table exists in the database.
     *
     * @param string $table
     * @return bool
     */
    public function hasTable($table)
    {
        $result = $this->all(
            "SELECT tablename FROM pg_catalog.pg_tables ".
            "WHERE schemaname != 'pg_catalog' AND schemaname != 'information_schema' ".
            "AND tablename = '" . $this->getTableName($table, false) . "'");
        return !empty($result);
    }

    /**
     * Removes records that are older than the specified amount of seconds.
     *
     * @param string $table
     * @param string $dateField
     * @param int $seconds
     * @param bool $addPrefix
     * @return bool
     */
    public function removeOld($table, $dateField = "created_at", $seconds = 3600, $addPrefix = true)
    {
        if ($addPrefix) {
            $table = "{" . $table . "}";
        }

        if (!$this->query("DELETE FROM $table WHERE $dateField < NOW() - INTERVAL '$seconds seconds'")) {
            $this->logLastError();
            return false;
        }

        return true;
    }

}