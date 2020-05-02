<?php
/**
 * Roundcube Plus xframework plugin
 *
 * Copyright 2016, Tecorama LLC.
 *
 * @license Commercial. See the file LICENSE for details.
 */

require_once(__DIR__ . "/../../xframework/common/Test.php");
require_once(__DIR__ . "/../common/DatabaseMysql.php");
require_once(__DIR__ . "/../common/DatabaseSqlite.php");
require_once(__DIR__ . "/../common/DatabasePostgres.php");

class DatabaseTest extends XFramework\Test
{
    private $testRecord = array(
        "name" => "maya",
        "char_value" => "hello",
        "int_value" => 44,
        "bool_value" => true,
        "null" => 55,
    );

    public function __construct() {
        parent::__construct();

        $this->dbProvider = $_SERVER['ROUNDCUBE_TEST_DATABASE'];

        switch ($this->dbProvider) {
            case "mysql":
                $this->db = new \XFramework\DatabaseMysql($this->rcmail);
                break;

            case "sqlite":
                $this->db = new \XFramework\DatabaseSqlite($this->rcmail);
                break;

            case "postgres":
                $this->db = new \XFramework\DatabasePostgres($this->rcmail);
                break;
        }
    }

    /**
     * Creates the database table for testing.
     */
    public function testQuery()
    {
        $this->db->query("DROP TABLE IF EXISTS {xunit_tests}");

        switch ($this->dbProvider) {
            case "mysql":
                $this->db->query("CREATE TABLE IF NOT EXISTS {xunit_tests} (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    name VARCHAR(255) NOT NULL DEFAULT '',
                    char_value VARCHAR(255) NOT NULL DEFAULT '',
                    int_value INT NOT NULL DEFAULT 0,
                    bool_value TINYINT(1) NOT NULL DEFAULT 0,            
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    modified_at TIMESTAMP NULL DEFAULT NULL,
                    removed_at TIMESTAMP NULL DEFAULT NULL," .
                    $this->db->col("null") . " INT UNSIGNED NOT NULL DEFAULT 0,
                    PRIMARY KEY (id)
                    ) ENGINE = InnoDB DEFAULT CHARSET utf8 COLLATE utf8_unicode_ci;"
                );
                break;

            case "sqlite":
                $this->db->query("CREATE TABLE IF NOT EXISTS {xunit_tests} (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name VARCHAR(255) NOT NULL DEFAULT '',
                    char_value VARCHAR(255) NOT NULL DEFAULT '',
                    int_value INT NOT NULL DEFAULT 0,
                    bool_value TINYINT(1) NOT NULL DEFAULT 0,            
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    modified_at TIMESTAMP NULL DEFAULT NULL,
                    removed_at TIMESTAMP NULL DEFAULT NULL," .
                    $this->db->col("null") . " INT UNSIGNED NOT NULL DEFAULT 0
                    );"
                );
                break;

            case "postgres":
                $this->db->query("DROP SEQUENCE IF EXISTS {xunit_tests_seq}");
                $this->db->query("CREATE SEQUENCE {xunit_tests_seq} START WITH 1 INCREMENT BY 1 NO MAXVALUE NO MINVALUE CACHE 1");
                $this->db->query("CREATE TABLE IF NOT EXISTS {xunit_tests} (
                    id INTEGER NOT NULL DEFAULT nextval('{xunit_tests_seq}'::text),
                    name VARCHAR(255) NOT NULL DEFAULT '',
                    char_value VARCHAR(255) NOT NULL DEFAULT '',
                    int_value INTEGER NOT NULL DEFAULT 0,
                    bool_value SMALLINT NOT NULL DEFAULT 0,            
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    modified_at TIMESTAMP NULL DEFAULT NULL,
                    removed_at TIMESTAMP NULL DEFAULT NULL," .
                    $this->db->col("null") . " INTEGER NOT NULL DEFAULT 0,
                    PRIMARY KEY (id)
                    );"
                );
                break;
        }

        $this->assertTrue($this->db->hasTable("xunit_tests"));
    }

    public function testGetProvider()
    {
        $this->assertEquals($this->db->getProvider(), $this->dbProvider);
    }

    public function testCol()
    {
        switch ($this->dbProvider) {
            case "mysql":
                $result = "`hello`";
                break;

            default: // sqlite and postgres
                $result = '"hello"';
        }

        $this->assertEquals($this->db->col("hello"), $result);
    }

    public function testFix()
    {
        $data = array("key1" => "1", "key2" => "0");

        $this->db->fix($data, BOOL, array("key1", "key2"));
        $this->assertTrue($data['key1'] === true);
        $this->assertTrue($data['key2'] === false);

        $this->db->fix($data, INT, array("key1", "key2"));
        $this->assertTrue($data['key1'] === 1);
        $this->assertTrue($data['key2'] === 0);
    }

    public function testGetColumns()
    {
        $this->assertTrue(is_array($this->db->getColumns("xunit_tests")));

        $this->rcmail->db->set_option("ignore_errors", true);
        $this->assertTrue($this->db->getColumns("invalid table name") == false);
        $this->rcmail->db->set_option("ignore_errors", true);
    }

    public function testInsert()
    {
        $this->assertTrue($this->db->insert("xunit_tests", $this->testRecord));
        $this->assertEquals($this->db->count("xunit_tests", ["int_value" => 44]), 1);
        $this->assertTrue($this->db->insert("xunit_tests", array_merge($this->testRecord, array("int_value" => 66))));
        $this->assertTrue($this->db->insert("xunit_tests", array_merge($this->testRecord, array("int_value" => 77))));

        $this->rcmail->db->set_option("ignore_errors", true);
        $this->assertTrue($this->db->insert("xunit_tests", ["invalid_column", 33]) === false);
        $this->rcmail->db->set_option("ignore_errors", false);
    }

    public function testLastInsertId()
    {
        $this->assertEquals($this->db->lastInsertId("xunit_tests"), 3);
    }

    public function testLastError()
    {
        $this->rcmail->db->set_option("ignore_errors", true);
        $this->db->query("hello");
        $this->rcmail->db->set_option("ignore_errors", false);

        $this->assertTrue($this->db->lastError() !== NULL);
    }

    public function testFetch()
    {
        $this->rcmail->db->set_option("ignore_errors", true);
        $this->assertTrue($this->db->fetch("THIS IS AN INVALID QUERY") == false);
        $this->rcmail->db->set_option("ignore_errors", true);
    }

    public function testRow()
    {
        // also checking if the column 'null' is properly quoted
        $data = $this->db->row("xunit_tests", array("name" => "maya", "null" => "55"));
        $this->assertIncludesArray($data, $this->testRecord);

        $this->assertTrue($this->db->row("xunit_tests", ["removed_at" => null]) !== false);
    }

    public function testValue()
    {
        $value = $this->db->value("char_value", "xunit_tests", array("id" => 1, "name" => "maya"));
        $this->assertEquals($value, "hello");
    }

    public function testAll()
    {
        $data = $this->db->all("SELECT * FROM {xunit_tests} WHERE name = ?", "maya");
        $this->assertTrue(is_array($data));
        $this->assertTrue(count($data) > 1);
        $this->assertTrue(!empty($data[0]['name']));
        $this->assertEquals($data[0]['name'], "maya");

        $data = $this->db->all("SELECT * FROM {xunit_tests} WHERE name = ?", "maya", "name");
        $this->assertTrue(is_array($data));
        $this->assertTrue(count($data) == 1);
        $this->assertTrue(!empty($data['maya']['name']));
        $this->assertEquals($data['maya']['name'], "maya");

        $this->rcmail->db->set_option("ignore_errors", true);
        $this->assertTrue($this->db->all("this is an invalid query") === false);
        $this->rcmail->db->set_option("ignore_errors", false);
    }

    public function testUpdate()
    {
        $this->assertTrue(
            $this->db->update(
                "xunit_tests",
                array("char_value" => "updated", "int_value" => 55),
                array("id" => 1, "name" => "maya")
            )
        );

        $this->assertEquals($this->db->value("char_value", "xunit_tests", array("id" => "1")), "updated");

        $this->rcmail->db->set_option("ignore_errors", true);
        $this->assertTrue($this->db->update("xunit_tests", ["char_value", "hello"], ["invalid_column", 1]) === false);
        $this->rcmail->db->set_option("ignore_errors", false);
    }

    public function testRemove()
    {
        $this->assertTrue($this->db->remove("xunit_tests", array("id" => 1, "name" => "maya")));
        $this->assertEquals($this->db->value("char_value", "xunit_tests", array("id" => 1)), null);

        // on sqlite this always returns true
        if ($this->dbProvider != "sqlite") {
            $this->rcmail->db->set_option("ignore_errors", true);
            $this->assertTrue($this->db->remove("xunit_tests", ["invalid_column" => 1]) === false);
            $this->rcmail->db->set_option("ignore_errors", false);
        }
    }

    public function testRemoveOld()
    {
        $this->assertTrue(
            $this->db->insert(
                "xunit_tests",
                array_merge($this->testRecord, array("created_at" => date("Y-m-d H:i:s", strtotime("-1 day"))))
            )
        );

        $this->assertEquals($this->db->value("int_value", "xunit_tests", array("id" => 4)), 44);
        $this->assertTrue($this->db->removeOld("xunit_tests", "created_at", 3600));
        $this->assertEquals($this->db->value("int_value", "xunit_tests", array("id" => 4)), null);

        $this->rcmail->db->set_option("ignore_errors", true);
        $this->assertTrue($this->db->removeOld("xunit_tests", "invalid field") === false);
        $this->rcmail->db->set_option("ignore_errors", false);
    }

    public function testTransaction()
    {
        $this->db->beginTransaction();
        $this->assertTrue($this->db->insert("xunit_tests", array_merge($this->testRecord, array("char_value" => "trans"))));
        $this->db->commit();
        $this->assertEquals($this->db->value("char_value", "xunit_tests", array("char_value" => "trans")), "trans");

        $this->db->beginTransaction();
        $this->assertTrue($this->db->insert("xunit_tests", array_merge($this->testRecord, array("char_value" => "roll"))));
        $this->db->rollBack();
        $this->assertEquals($this->db->value("char_value", "xunit_tests", array("char_value" => "roll")), null);
    }

    public function testGetTables()
    {
        $tables = $this->db->getTables();
        $this->assertTrue(is_array($tables));
        $this->assertTrue(count($tables) > 0);
    }

    public function testHasColumn()
    {
        $this->assertTrue($this->db->hasColumn("removed_at", "xunit_tests", true));
    }


    // INSERT NEW TESTS HERE



    /**
     * This test should be last: it truncates the table
     */
    public function testTruncate()
    {
        $this->rcmail->db->set_option("ignore_errors", true);
        $this->assertTrue($this->db->truncate("invalid table name") === false);
        $this->rcmail->db->set_option("ignore_errors", false);

        $this->assertNotEquals($this->db->truncate("xunit_tests"), false);
        $this->assertEquals($this->db->value("char_value", "xunit_tests", array("name" => "maya")), null);
    }
}