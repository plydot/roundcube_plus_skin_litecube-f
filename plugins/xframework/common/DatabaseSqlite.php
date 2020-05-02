<?php
namespace XFramework;

/**
 * Roundcube Plus Framework plugin.
 *
 * This class provides functions that contain SQLite-specific queries.
 *
 * Copyright 2016, Tecorama LLC.
 *
 * @license Commercial. See the LICENSE file for details.
 */

require_once(__DIR__ . "/Database.php");
require_once(__DIR__ . "/DatabaseInterface.php");

class DatabaseSqlite extends Database implements DatabaseInterface
{
    /**
     * Returns the columns in the database table.
     * We're not using $this->rcmail->db->list_cols($table) because in some cases it doesn't return reliable results.
     *
     * @param string $table
     * @param bool $prependPrefix
     * @return array
     */
    public function getColumns($table, $addPrefix = true)
    {
        if ($addPrefix) {
            $table = "{" . $table . "}";
        }

        $all = $this->all("pragma table_info($table)");

        if (!is_array($all)) {
            return array();
        }

        $columns = array();

        foreach ($all as $item) {
            $columns[] = $item["name"];
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
        $tables = $this->all("SELECT name FROM sqlite_master WHERE type='table'");

        if (is_array($tables)) {
            foreach ($tables as $table) {
                if (($values = array_values($table)) && $values[0] != "sqlite_sequence") {
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
        $table = $this->getTableName($table, false);
        return in_array($table, $this->getTables());
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

        if (!$this->query("DELETE FROM $table WHERE $dateField < date('now', '-$seconds second')")) {
            $this->logLastError();
            return false;
        }

        return true;
    }

}