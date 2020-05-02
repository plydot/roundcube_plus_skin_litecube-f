<?php
namespace XFramework;

/**
 * Roundcube Plus Framework plugin.
 *
 * This file provides ip to country functions.
 *
 * Copyright 2016, Tecorama LLC.
 *
 * @license Commercial. See the LICENSE file for details.
 */

class Geo
{
    /**
     * Gets the geolocation data for the currently logged in user based on the user ip.
     *
     * @param string|bool $maxMindDatabase
     * @param bool $maxMindCity
     * @return array
     */
    public static function getUserData($maxMindDatabase = false, $maxMindCity = false)
    {
        $rcmail = \rcmail::get_instance();

        $rcmail->user->geoData = self::getDataFromIp(
            Utils::getRemoteAddr(),
            $maxMindDatabase,
            $maxMindCity
        );

        return $rcmail->user->geoData;
    }

    /**
     * Gets the geolocation data for the specified ip.
     *
     * @param string $ip
     * @param string|bool $maxMindDatabase
     * @param bool $maxMindCity
     * @return array
     */
    public static function getDataFromIp($ip, $maxMindDatabase = false, $maxMindCity = false)
    {
        $data = array(
            "ip" => $ip,
            "country_code" => false,
            "country_name" => false,
            "city" => false,
            "latitude" => false,
            "longitude" => false,
        );

        // check if the data for this configuration has been already retrieved
        $hash = md5($ip . $maxMindDatabase . $maxMindCity);

        if (isset($_SESSION["xframework_geo_$hash"])) {
            return $_SESSION["xframework_geo_$hash"];
        }

        // get the geo data from the database
        self::getMaxMindData($ip, $data, $maxMindDatabase, $maxMindCity);
        $data["country_name"] = self::getCountryName($data["country_code"]);

        $_SESSION["xframework_geo_$hash"] = $data;

        return $data;
    }

    /**
     * Returns the array of country names for the specified language taken from the countries directory.
     *
     * @param bool $includeUnknown
     * @param bool $language
     * @return array|mixed
     */
    public static function getCountryArray($includeUnknown = true, $language = false)
    {
        // get the user's language code
        if (!$language) {
            $rcmail = \rcmail::get_instance();
            $array = explode("_", $rcmail->user->language);
            $language = $array[0];
        }

        $countries = @include(__DIR__ . "/countries/$language.php");

        if (empty($countries) && $language != "en") {
            $countries = include(__DIR__ . "/countries/en.php");
        }

        if (empty($countries)) {
            return array();
        }

        if (!$includeUnknown) {
            unset($countries['ZZ']);
        }

        return $countries;
    }

    /**
     * Returns the country name given the country code.
     *
     * @param string $code
     * @return mixed|string
     */
    public static function getCountryName($code)
    {
        $code = (string)$code;
        $countries = self::getCountryArray(true);

        if (!is_array($countries) || empty($code) || !array_key_exists($code, $countries)) {
            return "-";
        }

        return array_key_exists($code, $countries) ? $countries[$code] : "-";
    }

    /**
     * Uses the MaxMind database to get the geo data.
     * http://dev.maxmind.com/geoip/
     *
     * @param string $ip
     * @param array $data
     * @param string $provider
     * @param string|bool $maxMindDatabase
     * @param bool $maxMindCity
     */
    protected static function getMaxMindData($ip, &$data, $maxMindDatabase = false, $maxMindCity = false)
    {
        try {
            require_once(__DIR__ . "/../vendor/autoload.php");

            // use the local country database unless a different database is specified
            if (!$maxMindDatabase) {
                $maxMindDatabase = __DIR__ . "/geo/GeoLite2-Country.mmdb";
                $maxMindCity = false;
            }

            $reader = new \GeoIp2\Database\Reader($maxMindDatabase);

            if ($maxMindCity) {
                $record = $reader->city($ip);
                $data['city'] = $record->city->name;
                $data['latitude'] = $record->location->latitude;
                $data['longitude'] = $record->location->longitude;
            } else {
                $record = $reader->country($ip);
            }

            if (!empty($record->country->isoCode)) {
                $data["country_code"] = $record->country->isoCode;
            }
        } catch (\Exception $e) {
        }
    }
}