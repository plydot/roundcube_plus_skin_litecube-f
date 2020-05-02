<?php
/**
 * Roundcube Plus Skin plugin.
 *
 * Copyright 2019, Tecorama LLC.
 *
 * @license Commercial. See the LICENSE file for details.
 */

require_once(__DIR__ . "/xskin_elastic.php");

class xskin extends xskin_elastic
{
    public function initialize()
    {
        $this->elastic = $this->isElastic();

        // set skin by a url parameter - this is used by the quick skin change select option in the popup
        if (($urlSkin = \rcube_utils::get_input_value('skin', \rcube_utils::INPUT_GET)) &&
            !$this->getDontOverride("skin")
        ) {
            // prevent switching to an elastic-based skin on RC that doesn't support elastic (<= 1.3)
            // this is in case the admin copied the RC+ elastic-based skins to the skins directory on RC 1.3
            if (!$this->getElasticSupport() && $this->isElasticSkin($urlSkin)) {
                $this->rcmail->output->show_message(
                    $this->rcmail->gettext("xskin.elastic_skin_not_supported"),
                    "error"
                );
            } else {
                $pref = $this->rcmail->user->get_prefs();

                if (!empty($pref)) {
                    $pref['skin'] = $urlSkin;
                    $this->rcmail->user->save_prefs($pref);
                    header("Refresh:0; url=" . XFramework\Utils::removeVarsFromUrl("skin"));
                    exit;
                }
            }
        }

        if ($this->elastic) {
            $this->elasticInitialize();
        } else {
            $this->larryInitialize();
        }
    }

    /**
     * Performs string replacement with error checking. If the string to search for cannot be found it exists with an
     * error message.
     *
     * @param string $search
     * @param string $replace
     * @param string $subject
     * @param string $errorNumber
     * @codeCoverageIgnore
     */
    protected function replace($search, $replace, &$subject, $errorNumber = false)
    {
        $count = 0;
        $subject = str_replace($search, $replace, $subject, $count);

        if ($errorNumber && !$count) {
            exit(
                "<p>ERROR $errorNumber: Roundcube is not running properly or it is not compatible with the Roundcube ".
                "Plus skin. Disable the xskin plugin in config.inc.php and refresh this page to check for errors.</p>"
            );
        }
    }


}