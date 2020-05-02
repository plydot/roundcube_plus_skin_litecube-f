<?php
/**
 * Roundcube Plus xframework plugin
 *
 * Copyright 2016, Tecorama LLC.
 *
 * @license Commercial. See the file LICENSE for details.
 */

require_once(__DIR__ . "/../../xframework/common/Test.php");
require_once(__DIR__ . "/../common/Utils.php");

use XFramework\Utils as Utils;

class UtilsTest extends XFramework\Test
{
    public function __construct() {
        parent::__construct();

        // get temp dir
        $this->tempDir = $this->rcmail->config->get("temp_dir");
        $this->assertTrue($this->tempDir != false);
        $this->tempDir = Utils::addSlash($this->tempDir);
    }

    public function testGetRemoteAddr()
    {
        $ip = "5.157.7.42";
        $this->assertEquals(Utils::getRemoteAddr(), $ip);

        unset($_SERVER['REMOTE_ADDR']);
        $this->assertEquals(Utils::getRemoteAddr(), "");
        $_SERVER['REMOTE_ADDR'] = $ip;

        $this->rcmail->config->set("remote_addr_key", "ALTERNATE_REMOTE_ADDR");
        $_SERVER['ALTERNATE_REMOTE_ADDR'] = "82.102.20.178";
        $this->assertEquals(Utils::getRemoteAddr(), "82.102.20.178");
    }

    public function testSizeToString()
    {
        $this->assertEquals(Utils::sizeToString("hello"), "-");
        $this->assertEquals(Utils::sizeToString(12345678), "12 MB");
    }

    public function testShortenString()
    {
        $this->assertEquals(Utils::shortenString("this is a long string", 10), "this is a...");
        $this->assertEquals(Utils::shortenString("hello", 10), "hello");
    }

    public function testStructuredDirectory()
    {
        $this->assertEquals(Utils::structuredDirectory(1234, -5, 10), "000/012/");
    }

    public function testEnsureFileName()
    {
        $this->assertEquals(Utils::ensureFileName("hello/\:?*+%|\"<>there"), "hello_there");
    }

    public function testUniqueFileName()
    {
        $this->assertRegExp('/prefix_.*\.png/', Utils::uniqueFileName("/home/maya", "png", "prefix_"));
    }

    public function testExt()
    {
        $this->assertEquals(Utils::ext("hello.png"), "png");
    }

    public function testMakeDir()
    {
        $dir = $this->tempDir . "test_dir";
        Utils::removeDir($dir);

        // test creation
        $this->assertTrue(Utils::makeDir($dir));
        // test if true if already exists
        $this->assertTrue(Utils::makeDir($dir));
    }

    public function testRemoveDir()
    {
        $dir = $this->tempDir . "test_dir";
        $file = "$dir/directory/file";
        $symTarget = $this->tempDir . "test_file";
        $symLink = "$dir/directory/sym";


        $this->assertTrue(Utils::removeDir("$dir/some-non-existent-directory"));

        $this->createTestFiles($dir, $file, $symTarget, $symLink);
        $this->assertTrue(Utils::removeDir($dir, false, true));
        $this->assertFalse(file_exists($file));
        $this->assertFalse(file_exists($symLink));
        $this->assertTrue(file_exists($symTarget));

        $this->createTestFiles($dir, $file, $symTarget, $symLink);
        $this->assertTrue(Utils::removeDir($dir, true, true));
        $this->assertFalse(file_exists($file));
        $this->assertFalse(file_exists($symLink));
        $this->assertFalse(file_exists($symTarget));

        $this->createTestFiles($dir, $file, $symTarget, $symLink);
        $this->assertTrue(Utils::removeDir($dir, true, false));
        $this->assertFalse(file_exists($dir));
    }

    private function createTestFiles($dir, $file, $symTarget, $symLink)
    {
        Utils::makeDir("$dir/directory");
        file_put_contents($file, "hello");
        file_put_contents($symTarget, "hello");
        symlink($symTarget, $symLink);
        $this->assertTrue(file_exists($file));
        $this->assertTrue(file_exists($symTarget));
        $this->assertTrue(file_exists($symLink));
    }
}