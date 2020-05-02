<?php
/**
 * Roundcube Plus xframework plugin
 *
 * Copyright 2016, Tecorama LLC.
 *
 * @license Commercial. See the file LICENSE for details.
 */

require_once(__DIR__ . "/../../xframework/common/Test.php");
require_once(__DIR__ . "/../common/Geo.php");

class GeoTest extends XFramework\Test
{
    public function testGetUserData()
    {
        $_SERVER['REMOTE_ADDR'] = "5.157.7.42";
        $data = \XFramework\Geo::getUserData();
        $this->assertEquals($data['country_code'], "SE");
        $this->assertEquals($data['country_name'], "Sweden");
    }

    public function testGetDataFromIp()
    {
        $data = \XFramework\Geo::getDataFromIp("5.157.7.42");
        $this->assertTrue(!empty($data));
        $this->assertEquals($data['country_code'], "SE");
        $this->assertEquals($data['country_name'], "Sweden");
    }

    public function testGetCountryArray()
    {
        $array = \XFramework\Geo::getCountryArray(true, "es");
        $this->assertEquals($array['IT'], "Italia");
        $array = \XFramework\Geo::getCountryArray(true, "aa");
        $this->assertEquals($array['IT'], "Italy");
        $array = \XFramework\Geo::getCountryArray(false);
        $this->assertTrue(!in_array("ZZ", $array));

    }

    public function testGetCountryName()
    {
        $this->assertEquals(\XFramework\Geo::getCountryName("IT"), "Italy");
        $this->assertEquals(\XFramework\Geo::getCountryName("AA"), "-");
    }
}