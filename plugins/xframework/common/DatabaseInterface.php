<?php
namespace XFramework;

/**
 * Roundcube Plus Framework plugin.
 *
 * Copyright 2016, Tecorama LLC.
 *
 * @license Commercial. See the LICENSE file for details.
 */

interface DatabaseInterface
{
    public function getColumns($table, $addPrefix = true);
    public function getTables();
    public function hasTable($table);
    public function removeOld($table, $dateField = "created_at", $seconds = 3600, $addPrefix = true);
}