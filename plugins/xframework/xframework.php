<?php
/**
 * Roundcube Plus Framework plugin.
 *
 * Copyright 2019, Tecorama LLC.
 *
 * @license Commercial. See the LICENSE file for details.
 */

if (!function_exists("dd")) {
    /**
     * @codeCoverageIgnore
     */
    function dd($var) {
        var_dump($var);
        exit;
    }
}

if (!function_exists("x")) {
    /**
     * @codeCoverageIgnore
     */
    function x($var) {
        var_dump($var);
    }
}

class xframework extends \rcube_plugin
{
    /**
     * @codeCoverageIgnore
     */
    public function init()
    {
    }
}
