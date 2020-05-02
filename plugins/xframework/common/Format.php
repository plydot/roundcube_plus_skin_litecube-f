<?php
namespace XFramework;

/**
 * Roundcube Plus Framework plugin.
 *
 * This class handles the date/time formats and their conversions to the formats used by different systems/components.
 *
 * Copyright 2016, Tecorama LLC.
 *
 * @license Commercial. See the LICENSE file for details.
 */

class Format
{
    const DECIMAL_SEPARATOR_SYMBOL = 0;
    const GROUPING_SEPARATOR_SYMBOL = 1;
    const MONETARY_SEPARATOR_SYMBOL = 2;
    const MONETARY_GROUPING_SEPARATOR_SYMBOL = 3;

    private $separators = array(
        'sq_AL' => array(0 => ',', 1 => ' ', 2 => ',', 3 => ' '),
        'ar' => array(0 => '٫', 1 => '٬', 2 => '٫', 3 => '٬'),
        'ar_SA' => array(0 => '٫', 1 => '٬', 2 => '٫', 3 => '٬'),
        'hy_AM' => array(0 => ',', 1 => '.', 2 => ',', 3 => '.'),
        'ast' => array(0 => '.', 1 => ',', 2 => '.', 3 => ','),
        'az_AZ' => array(0 => ',', 1 => '.', 2 => ',', 3 => '.'),
        'eu_ES' => array(0 => ',', 1 => '.', 2 => ',', 3 => '.'),
        'be_BE' => array(0 => ',', 1 => ' ', 2 => ',', 3 => ' '),
        'bn_BD' => array(0 => '.', 1 => ',', 2 => '.', 3 => ','),
        'bs_BA' => array(0 => ',', 1 => '.', 2 => ',', 3 => '.'),
        'br' => array(0 => ',', 1 => ' ', 2 => ',', 3 => ' '),
        'bg_BG' => array(0 => ',', 1 => ' ', 2 => ',', 3 => ' '),
        'ca_ES' => array(0 => ',', 1 => '.', 2 => ',', 3 => '.'),
        'zh_CN' => array(0 => '.', 1 => ',', 2 => '.', 3 => ','),
        'zh_TW' => array(0 => '.', 1 => ',', 2 => '.', 3 => ','),
        'hr_HR' => array(0 => ',', 1 => '.', 2 => ',', 3 => '.'),
        'cs_CZ' => array(0 => ',', 1 => ' ', 2 => ',', 3 => ' '),
        'da_DK' => array(0 => ',', 1 => '.', 2 => ',', 3 => '.'),
        'fa_AF' => array(0 => '٫', 1 => '٬', 2 => '٫', 3 => '٬'),
        'de_DE' => array(0 => ',', 1 => '.', 2 => ',', 3 => '.'),
        'de_CH' => array(0 => '.', 1 => "'", 2 => '.', 3 => "'"),
        'nl_NL' => array(0 => ',', 1 => '.', 2 => ',', 3 => '.'),
        'en_CA' => array(0 => '.', 1 => ',', 2 => '.', 3 => ','),
        'en_GB' => array(0 => '.', 1 => ',', 2 => '.', 3 => ','),
        'en_US' => array(0 => '.', 1 => ',', 2 => '.', 3 => ','),
        'eo' => array(0 => ',', 1 => ' ', 2 => ',', 3 => ' '),
        'et_EE' => array(0 => ',', 1 => ' ', 2 => ',', 3 => ' '),
        'fo_FO' => array(0 => ',', 1 => '.', 2 => ',', 3 => '.'),
        'fi_FI' => array(0 => ',', 1 => ' ', 2 => ',', 3 => ' '),
        'nl_BE' => array(0 => ',', 1 => '.', 2 => ',', 3 => '.'),
        'fr_FR' => array(0 => ',', 1 => ' ', 2 => ',', 3 => ' '),
        'gl_ES' => array(0 => ',', 1 => '.', 2 => ',', 3 => '.'),
        'ka_GE' => array(0 => ',', 1 => ' ', 2 => ',', 3 => ' '),
        'el_GR' => array(0 => ',', 1 => '.', 2 => ',', 3 => '.'),
        'he_IL' => array(0 => '.', 1 => ',', 2 => '.', 3 => ','),
        'hi_IN' => array(0 => '.', 1 => ',', 2 => '.', 3 => ','),
        'hu_HU' => array(0 => ',', 1 => ' ', 2 => ',', 3 => ' '),
        'is_IS' => array(0 => ',', 1 => '.', 2 => ',', 3 => '.'),
        'id_ID' => array(0 => ',', 1 => '.', 2 => ',', 3 => '.'),
        'ia' => array(0 => '.', 1 => ',', 2 => '.', 3 => ','),
        'ga_IE' => array(0 => '.', 1 => ',', 2 => '.', 3 => ','),
        'it_IT' => array(0 => ',', 1 => '.', 2 => ',', 3 => '.'),
        'ja_JP' => array(0 => '.', 1 => ',', 2 => '.', 3 => ','),
        'km_KH' => array(0 => ',', 1 => '.', 2 => ',', 3 => '.'),
        'kn_IN' => array(0 => '.', 1 => ',', 2 => '.', 3 => ','),
        'ko_KR' => array(0 => '.', 1 => ',', 2 => '.', 3 => ','),
        'ku' => array(0 => '.', 1 => ',', 2 => '.', 3 => ','),
        'lv_LV' => array(0 => ',', 1 => ' ', 2 => ',', 3 => ' '),
        'lt_LT' => array(0 => ',', 1 => ' ', 2 => ',', 3 => ' '),
        'lb_LU' => array(0 => '.', 1 => ',', 2 => '.', 3 => ','),
        'mk_MK' => array(0 => ',', 1 => '.', 2 => ',', 3 => '.'),
        'mn_MN' => array(0 => '.', 1 => ',', 2 => '.', 3 => ','),
        'ms_MY' => array(0 => '.', 1 => ',', 2 => '.', 3 => ','),
        'ml_IN' => array(0 => '.', 1 => ',', 2 => '.', 3 => ','),
        'mr_IN' => array(0 => '.', 1 => ',', 2 => '.', 3 => ','),
        'ne_NP' => array(0 => '.', 1 => ',', 2 => '.', 3 => ','),
        'nb_NO' => array(0 => ',', 1 => ' ', 2 => ',', 3 => ' '),
        'nn_NO' => array(0 => ',', 1 => ' ', 2 => ',', 3 => ' '),
        'ps' => array(0 => '٫', 1 => '٬', 2 => '٫', 3 => '٬'),
        'fa_IR' => array(0 => '٫', 1 => '٬', 2 => '٫', 3 => '٬'),
        'pl_PL' => array(0 => ',', 1 => ' ', 2 => ',', 3 => ' '),
        'pt_BR' => array(0 => ',', 1 => '.', 2 => ',', 3 => '.'),
        'pt_PT' => array(0 => ',', 1 => ' ', 2 => ',', 3 => ' '),
        'ro_RO' => array(0 => ',', 1 => '.', 2 => ',', 3 => '.'),
        'ru_RU' => array(0 => ',', 1 => ' ', 2 => ',', 3 => ' '),
        'sr_CS' => array(0 => ',', 1 => '.', 2 => ',', 3 => '.'),
        'si_LK' => array(0 => '.', 1 => ',', 2 => '.', 3 => ','),
        'sk_SK' => array(0 => ',', 1 => ' ', 2 => ',', 3 => ' '),
        'sl_SI' => array(0 => ',', 1 => '.', 2 => ',', 3 => '.'),
        'es_AR' => array(0 => ',', 1 => '.', 2 => ',', 3 => '.'),
        'es_ES' => array(0 => ',', 1 => ' ', 2 => ',', 3 => ' '),
        'es_419' => array(0 => '.', 1 => ',', 2 => '.', 3 => ','),
        'sv_SE' => array(0 => ',', 1 => ' ', 2 => ':', 3 => ' '),
        'ta_IN' => array(0 => '.', 1 => ',', 2 => '.', 3 => ','),
        'ti' => array(0 => '.', 1 => ',', 2 => '.', 3 => ','),
        'th_TH' => array(0 => '.', 1 => ',', 2 => '.', 3 => ','),
        'tr_TR' => array(0 => ',', 1 => '.', 2 => ',', 3 => '.'),
        'uk_UA' => array(0 => ',', 1 => ' ', 2 => ',', 3 => ' '),
        'ur_PK' => array(0 => '.', 1 => ',', 2 => '.', 3 => ','),
        'vi_VN' => array(0 => ',', 1 => '.', 2 => ',', 3 => '.'),
        'cy_GB' => array(0 => '.', 1 => ',', 2 => '.', 3 => ','),
        'fy_NL' => array(0 => '.', 1 => ',', 2 => '.', 3 => ','),
    );

    /**
     * Format constructor
     *
     * @param \rcmail $rcmail
     * @codeCoverageIgnore
     */
    public function __construct($rcmail = null) {
        $this->rcmail = $rcmail ? $rcmail : \rcmail::get_instance();

        // $this->rcmail might be null when running some /bin scripts
        if ($this->rcmail) {
            $this->loadFormats();
        }
    }

    /**
     * Returns the user date format.
     *
     * @param string $type
     * @return string
     */
    public function getDateFormat($type = "php")
    {
        $valid = in_array($type, array("php", "moment", "datepicker"));
        return $valid ? $this->rcmail->dateFormats[$type] : false;
    }

    /**
     * Returns the user's time format.
     *
     * @param string $type
     * @return string
     */
    public function getTimeFormat($type = "php")
    {
        $valid = in_array($type, array("php", "moment"));
        return $valid ? $this->rcmail->timeFormats[$type] : false;
    }

    /**
     * Converts string to time taking into consideration the user's date/time format. It fixes the problem of
     * strtotime() or DateTime() not working properly with formats day/month/year and converting considering them
     * month/day/year.
     *
     * @param $dateTimeString
     * @param string $type
     * @return bool
     */
    public function stringToTimeWithFormat($dateTimeString, $type = "php")
    {
        $dateTimeString = trim($dateTimeString);
        $format = $this->getDateFormat($type);

        if (strpos($dateTimeString, " ") !== false) {
            $format .= " " . $this->getTimeFormat($type);
        }

        if (!($dateTime = \DateTime::createFromFormat($format, $dateTimeString))) {
            return false;
        }

        return $dateTime->getTimestamp();
    }

    /**
     * Formats the date according to the format specified in the user's config.
     *
     * @param string|int $date - string or integer representation of date
     * @param bool $textOnEmpty
     * @return string|bool
     */
    public function formatDate($date, $textOnEmpty = false)
    {
        if (empty($date)) {
            return $textOnEmpty;
        }

        return date($this->getDateFormat(), is_numeric($date) ? $date : strtotime($date));
    }

    /**
     * Formats the time according to the format specified in the user's config.
     *
     * @param string|int $date - string or integer representation of date
     * @param bool $textOnEmpty
     * @return string|bool
     */
    public function formatTime($time, $textOnEmpty = false)
    {
        if (empty($time)) {
            return $textOnEmpty;
        }

        return date($this->getTimeFormat(), is_numeric($time) ? $time : strtotime($time));
    }

    /**
     * Formats the date and time according to the format specified in the user's config.
     *
     * @param string|int $date - string or integer representation of date
     * @param bool $textOnEmpty
     * @return string|bool
     */
    public function formatDateTime($date, $textOnEmpty = false)
    {
        if (empty($date)) {
            return $textOnEmpty;
        }

        return date($this->getDateFormat() . " " . $this->getTimeFormat(), is_numeric($date) ? $date : strtotime($date));
    }

    /**
     * Formats a currency number using the locale-specific separators.
     *
     * @param float $number
     * @param bool|int $decimals
     * @param bool|string $locale
     * @return string
     */
    public function formatCurrency($number, $decimals = false, $locale = false)
    {
        return $this->formatNumberOrCurrency("monetary", $number, $decimals, $locale);
    }

    /**
     * Formats a regular number using the locale-specific separators.
     *
     * @param float $number
     * @param bool|int $decimals
     * @param bool|string $locale
     * @return string
     */
    public function formatNumber($number, $decimals = false, $locale = false)
    {
        return $this->formatNumberOrCurrency("decimal", $number, $decimals, $locale);
    }

    /**
     * Returns the locale specific number formatting separatators.
     *
     * @param bool|string $locale
     * @return array
     */
    public function getSeparators($locale = false)
    {
        if (!$locale) {
            $rcmail = \rcmail::get_instance();
            $locale = $rcmail->user->language;
        }

        return $this->separators[$locale];
    }

    /**
     * Converts a float to string without regard for the locale. PHP automatically changes the delimiter used depending
     * on the locale set using setlocale(), so depending on the language selected by the user we might end up with
     * 3,1415 when converting floats to strings. This function leaves the dot delimiter intact when converting to
     * string.
     *
     * @param float $float
     * @return string
     */
    public static function floatToString($float)
    {
        if (!is_float($float)) {
            return $float;
        }

        $conv = localeconv();
        return str_replace($conv['decimal_point'], '.', $float);
    }

    /**
     * Returns a formatted number, either decimal or currency.
     *
     * @param string $type Specify 'monetary' or 'decimal'.
     * @param float $number
     * @param bool|int $decimals
     * @param bool|string $locale
     * @return string
     */
    protected function formatNumberOrCurrency($type, $number, $decimals = false, $locale = false)
    {
        if ($type == "monetary") {
            $separator = Format::MONETARY_SEPARATOR_SYMBOL;
            $groupingSeparator = Format::MONETARY_GROUPING_SEPARATOR_SYMBOL;
        } else {
            $separator = Format::DECIMAL_SEPARATOR_SYMBOL;
            $groupingSeparator = Format::GROUPING_SEPARATOR_SYMBOL;
        }

        if (!$decimals) {
            // uncomment to trim the trailing zeros from decimals: 2.1 instead of 2.10
            //$decimals = strlen(trim((string)(($number - round($number)) * 100), 0));
            $decimals = $number - round($number) == 0 ? 0 : 2;
        }

        if (!$locale) {
            $rcmail = \rcmail::get_instance();
            $locale = $rcmail->user->language;
        }

        return number_format(
            $number,
            $decimals,
            $this->separators[$locale][$separator],
            $this->separators[$locale][$groupingSeparator]
        );
    }

    /**
     * Different components use different formats for date and time, we're creating an array of converted formats
     * that can be used in javascript.
     */
    protected function loadFormats()
    {
        // date format
        $dateFormat = $this->rcmail->config->get("date_format", "m/d/Y");

        $this->rcmail->dateFormats = array(
            "php" => $dateFormat,
            "moment" => $this->getMomentDateFormat($dateFormat),
            "datepicker" => $this->getDatepickerDateFormat($dateFormat),
        );

        // time format
        $timeFormat = $this->rcmail->config->get("time_format", "H:i");

        $this->rcmail->timeFormats = array(
            "php" => $timeFormat,
            "moment" => $this->getMomentTimeFormat($timeFormat),
            "datepicker" => $this->getDatepickerTimeFormat($timeFormat),
        );

        // day/month format
        $dmFormat = trim(str_replace("Y", "", $dateFormat), " /.-");

        $this->rcmail->dmFormats = array(
            "php" => $dmFormat,
            "moment" => $this->getMomentDateFormat($dmFormat),
            "datepicker" => $this->getDatepickerDateFormat($dmFormat),
        );

        // set js variables
        if (!empty($this->rcmail->output)) {
            $this->rcmail->output->set_env("dateFormats", $this->rcmail->dateFormats);
            $this->rcmail->output->set_env("dmFormats", $this->rcmail->dmFormats);
            $this->rcmail->output->set_env("timeFormats", $this->rcmail->timeFormats);
        }
    }

    /**
     * Returns the user php date format converted to the javascript moment format.
     *
     * @param string $format
     * @return string
     */
    protected function getMomentDateFormat($format)
    {
        $replace = array(
            "D" => "*1",
            "d" => "DD",
            "l" => "dddd",
            "j" => "D",
            "*1" => "ddd",
            "n" => "*2",
            "M" => "MMM",
            "m" => "MM",
            "F" => "MMMM",
            "*2" => "M",
            "Y" => "YYYY",
            "y" => "YY",
        );

        return str_replace(array_keys($replace), array_values($replace), $format);
    }

    /**
     * Returns the user php time format converted to the javascript moment format.
     *
     * @param string $format
     * @return string
     */
    protected function getMomentTimeFormat($format)
    {
        $replace = array(
            "H" => "HH",
            "G" => "H",
            "h" => "hh",
            "g" => "h",
            "i" => "mm",
            "s" => "ss",
        );

        return str_replace(array_keys($replace), array_values($replace), $format);
    }

    /**
     * Returns the user php date format converted to the jquery ui datepicker format.
     *
     * @param string $format
     * @return string
     */
    protected function getDatepickerDateFormat($format)
    {
        $replace = array(
            "d" => "dd",
            "j" => "d",
            "m" => "mm",
            "n" => "m",
            "F" => "MM",
            "Y" => "yy",
        );

        return str_replace(array_keys($replace), array_values($replace), $format);
    }

    /**
     * Returns the user php date format converted to the jquery ui datepicker format.
     *
     * @param string $format
     * @return string
     */
    protected function getDatepickerTimeFormat($format)
    {
        $replace = array(
            "H" => "HH",
            "G" => "H",
            "h" => "hh",
            "g" => "h",
            "i" => "mm",
            "s" => "ss",
            "a" => "tt",
            "A" => "TT",
        );

        return str_replace(array_keys($replace), array_values($replace), $format);
    }
}