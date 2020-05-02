<?php
/**
 * Roundcube Plus xframework plugin
 *
 * Copyright 2016, Tecorama LLC.
 *
 * @license Commercial. See the file LICENSE for details.
 */

require_once(__DIR__ . "/../../xframework/common/Test.php");
require_once(__DIR__ . "/../common/Format.php");

class FormatTest extends XFramework\Test
{
    public function testConstruct()
    {
        $this->format = new XFramework\Format();

        $this->assertEquals($this->format->rcmail->dateFormats['php'], "Y-m-d");
        $this->assertEquals($this->format->rcmail->timeFormats['php'], "H:i");
        $this->assertEquals($this->format->rcmail->dmFormats['php'], "m-d");
    }

    public function testGetDateFormat()
    {
        $this->assertEquals($this->format->getDateFormat("php"), "Y-m-d");
        $this->assertEquals($this->format->getDateFormat("moment"),  "YYYY-MM-DD");
        $this->assertEquals($this->format->getDateFormat("datepicker"), "yy-mm-dd");
    }

    public function testGetTimeFormat()
    {
        $this->assertEquals($this->format->getTimeFormat("php"), "H:i");
        $this->assertEquals($this->format->getTimeFormat("moment"),  "HH:mm");
    }

    public function testFormatCurrency()
    {
        $this->assertEquals($this->format->formatCurrency("9.99"), "9.99");
        $this->assertEquals($this->format->formatCurrency("9.99", false, "sq_AL"), "9,99");
    }

    public function testFormatNumber()
    {
        $this->assertEquals($this->format->formatNumber("9.99", false, "sq_AL"), "9,99");
    }

    public function testGetSeparators()
    {
        $this->assertEquals($this->format->getSeparators(), [0 => '.', 1 => ',', 2 => '.', 3 => ',']);
        $this->assertEquals($this->format->getSeparators("sq_AL"), [0 => ',', 1 => ' ', 2 => ',', 3 => ' ']);
    }

    public function testFloatToString()
    {
        $this->assertEquals($this->format->floatToString(9.9), "9.9");
        $this->assertEquals($this->format->floatToString("string"), "string");
    }

    public function testFormatDate()
    {
        $this->assertEquals($this->format->formatDate(false, "hello"), "hello");
        $this->assertEquals($this->format->formatDate("1 Jan 2020"), "2020-01-01");
        $this->assertEquals($this->format->formatDate(1577893500), "2020-01-01");
    }

    public function testFormatTime()
    {
        $this->assertEquals($this->format->formatTime(false, "hello"), "hello");
        $this->assertEquals($this->format->formatTime("3:45 PM"), "15:45");
        $this->assertEquals($this->format->formatTime(1577893500), "15:45");
    }

    public function testFormatDateTime()
    {
        $this->assertEquals($this->format->formatDateTime(false, "hello"), "hello");
        $this->assertEquals($this->format->formatDateTime("1 Jan 2020 3:45 PM"), "2020-01-01 15:45");
        $this->assertEquals($this->format->formatDateTime(1577893500), "2020-01-01 15:45");
    }

}