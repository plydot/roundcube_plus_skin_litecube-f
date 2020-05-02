<?php
namespace XFramework;

/**
 * Roundcube Plus Framework plugin.
 *
 * This file provides a base class for the Roundcub Plus plugins.
 *
 * Copyright 2016, Tecorama LLC.
 *
 * @license Commercial. See the LICENSE file for details.
 * @codeCoverageIgnore
 */
require_once(__DIR__ . "/DatabaseMysql.php");
require_once(__DIR__ . "/DatabaseSqlite.php");
require_once(__DIR__ . "/DatabasePostgres.php");
require_once(__DIR__ . "/Input.php");
require_once(__DIR__ . "/Format.php");
require_once(__DIR__ . "/Html.php");
require_once(__DIR__ . "/Geo.php");
require_once(__DIR__ . "/Utils.php");
require_once(__DIR__ . "/../xframework.php");

define("XFRAMEWORK_VERSION", "1.6.5");

abstract class Plugin extends \rcube_plugin
{
    // overwrite these in the plugin to skip loading config or localization strings
    protected $hasConfig = true;
    protected $hasLocalization = true;
    public $allowed_prefs = array();

    protected $default = array();
    protected $userId = false;
    protected $plugin = false;
    protected $paid = true;
    protected $promo = false;
    protected $settingsPromo = false;
    protected $sidebarPromo = false;
    protected $pagePromo = false;
    protected $hasSidebarBox = false;
    protected $appUrl = false;
    protected $unitTest = false;
    protected $csrfToken = null;
    protected $elastic = null;
    protected $elasticSupport = null;

    // user preferences handled by xframework and saved via ajax, these should be included in $allowed_prefs of the
    // plugins that use these (can't add them via code for all or will get 'hack attempted' warning in logs)
    protected $frameworkPrefs = array(
        "xsidebar_order",
        "xsidebar_collapsed",
    );

    protected $skins = array(
        "alpha" => "Alpha",
        "droid" => "Droid",
        "icloud" => "iCloud",
        "litecube" => "Litecube",
        "litecube-f" => "Litecube Free",
        "outlook" => "Outlook",
        "w21" => "W21",
        "droid_plus" => "Droid+",
        "gmail_plus" => "GMail+",
        "outlook_plus" => "Outlook+",
    );

    protected $elasticSkins = array(
        "droid_plus",
        "outlook_plus",
        "gmail_plus",
    );

    /**
     * Creates the plugin.
     */
    public function init()
    {
        $this->rcmail = \rcmail::get_instance();

        if (empty($this->rcmail->output) || !$this->setResell()) {
            return;
        }

        $this->plugin = $this->ID;
        $this->detectElastic();
        $this->createDatabaseInstance();
        $this->input = new Input();
        $this->format = new Format();
        $this->html = new Html();
        $this->userId = $this->rcmail->get_user_id();

        $this->rcmail->xhtmlClasses = array();
        $this->rcmail->xbodyClasses = array("x" . $this->getSkinBase());

        if ($this->hasConfig) {
            $this->load_config();
        }

        // load config depending on the domain, if set up
        $this->loadMultiDomainConfig();

        // load values from the additional config file in ini format
        $this->loadIniConfig();

        // load the localization strings for the current plugin
        if ($this->hasLocalization) {
            $this->add_texts("localization/", false);
        }

        // load the xframework translation strings so they can be available to the inheriting plugins
        $this->loadFrameworkLocalization();

        $this->setDevice();
        $this->setLanguage();
        $this->setFrameworkHooks();
        $this->updateDatabase();

        // override the defaults of this plugin with its config settings, if specified
        if (!empty($this->default)) {
            foreach ($this->default as $key => $val) {
                $this->default[$key] = $this->rcmail->config->get($this->plugin . "_" . $key, $val);
            }

            // load the config/default values to environment
            $this->rcmail->output->set_env($this->plugin . "_settings", $this->default);
        }

        // set timezone offset (in seconds) to a js variable
        $this->setJsVar("timezoneOffset", $this->getTimezoneOffset());
        $this->setJsVar("xsidebarVisible", $this->rcmail->config->get("xsidebar_visible", true));

        // include the framework assets
        $this->includeAsset("xframework/assets/bower_components/js-cookie/src/js.cookie.js");
        $this->includeAsset("xframework/assets/scripts/framework.min.js");
        $this->includeAsset("xframework/assets/styles/" . $this->getSkinBase() . ".css");

        // add plugin to loaded plugins list
        isset($this->rcmail->xplugins) || $this->rcmail->xplugins = array();
        $this->rcmail->xplugins[] = $this->plugin;

        // run the plugin-specific initialization
        if ($this->checkCsrfToken()) {
            $this->initialize();
        }
    }

    /**
     * This method should be overridden by plugins.
     */
    public function initialize()
    {
    }

    public function getElasticSupport()
    {
        return $this->elasticSupport;
    }

    public function isRcpSkin($skin)
    {
        return array_key_exists($skin, $this->skins);
    }

    public function isElastic()
    {
        return $this->elastic;
    }

    public function getSkinBase()
    {
        return $this->elastic ? "elastic" : "larry";
    }

    public function isElasticSkin($skin)
    {
        return in_array($skin, $this->elasticSkins);
    }

    public function getSkins()
    {
        return $this->skins;
    }

    public function getPluginName()
    {
        return $this->plugin;
    }

    /**
     * Executed on preferences section list, runs only once regardless of how many xplugins are used.
     *
     * @param array $arg
     * @return array
     */
    public function hookPreferencesSectionsList(array $arg)
    {
        // if any loaded xplugins show on the sidebar, add the sidebar section
        if ($this->hasSidebarItems()) {
            $arg['list']['xsidebar'] = array('id' => 'xsidebar', 'section' => $this->gettext("sidebar"));
        }

        return $arg;
    }

    /**
     * Executed on preferences list, runs only once regardless of how many xplugins are used.
     *
     * @param array $arg
     * @return array
     */
    public function hookPreferencesList(array $arg)
    {
        if ($arg['section'] == "xsidebar") {
            $arg['blocks']['main']['name'] = $this->gettext("sidebar_items");

            foreach ($this->getSidebarPlugins() as $plugin) {
                $input = new \html_checkbox();

                $html = $input->show(
                    $this->getSetting("show_" . $plugin, true, $plugin),
                    array(
                        "name" => "show_" . $plugin,
                        "id" => $plugin . "_show_" . $plugin,
                        "data-name" => $plugin,
                        "value" => 1,
                    )
                );

                $this->addSetting($arg, "main", "show_" . $plugin, $html, $plugin);
            }

            if (!in_array("xsidebar_order", $this->rcmail->config->get("dont_override"))) {
                $order = new \html_hiddenfield(array(
                    "name" => "xsidebar_order",
                    "value" => $this->rcmail->config->get("xsidebar_order"),
                    "id" => "xsidebar-order",
                ));

                $arg['blocks']['main']['options']["test"] = array(
                    "content" => $order->show() .
                        \html::div(array("id" => "xsidebar-order-note"), $this->gettext("sidebar_change_order"))
                );
            }
        }

        return $arg;
    }

    /**
     * Executed on preferences save, runs only once regardless of how many xplugins are used.
     *
     * @param array $arg
     * @return array
     */
    public function hookPreferencesSave(array $arg)
    {
        if ($arg['section'] == "xsidebar") {
            foreach ($this->getSidebarPlugins() as $plugin) {
                $this->saveSetting($arg, "show_" . $plugin, false, $plugin);
            }

            if (!in_array("xsidebar_order", $this->rcmail->config->get("dont_override"))) {
                $arg['prefs']["xsidebar_order"] = \rcube_utils::get_input_value("xsidebar_order", \rcube_utils::INPUT_POST);
            }
        }

        return $arg;
    }

    public function getAppsUrl($check = false)
    {
        if (!empty($check)) {
            $check = "&check=" . (is_array($check) ? implode(",", $check) : $check);
        }

        return "?_task=settings&_action=preferences&_section=apps" . $check;
    }

    /**
     * Returns the timezone offset in seconds based on the user settings.
     *
     * @return int
     */
    public function getTimezoneOffset()
    {
        try {
            $dtz = new \DateTimeZone($this->rcmail->config->get("timezone"));
            $dt = new \DateTime("now", $dtz);
            return $dtz->getOffset($dt);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Returns the difference in seconds between the server timezone and the timezone set in user settings.
     *
     * @return int
     */
    public function getTimezoneDifference()
    {
        try {
            $dtz = new \DateTimeZone(date_default_timezone_get());
            $dt = new \DateTime("now", $dtz);
            return $this->getTimezoneOffset() - $dtz->getOffset($dt);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Loads the xframework's localization strings. It adds the strings to the scope of the plugin that calls the
     * function.
     */
    public function loadFrameworkLocalization()
    {
        $home = $this->home;
        $this->home = dirname($this->home) . "/xframework";
        $this->add_texts("localization/", false);
        $this->home = $home;
    }

    /**
     * Returns the default settings of the plugin.
     *
     * @return array
     */
    public function getDefault()
    {
        return $this->default;
    }

    /**
     * Updates the plugin's database structure by executing the sql files from the SQL directory if needed.
     * The database versions of all the xframework plugins are stored in a single db row in the system table.
     * This function reads that row once for all the plugins and then compares the retrieved information
     * with the version of the current plugin. If the plugin db schema needs updating, it updates it.
     *
     * @return boolean
     */
    public function updateDatabase()
    {
        if (empty($this->databaseVersion)) {
            return false;
        }

        // if versions have not been retrieved yet, retrieve and decode them
        if (empty($this->rcmail->xversions)) {
            if (!($result = $this->db->value("value", "system", array("name" => "xframework_db_versions")))) {
                $result = "{}";
            }
            $this->rcmail->xversions = json_decode($result, true);
        }

        // get the current db verson for the current plugin
        $version = array_key_exists($this->plugin, $this->rcmail->xversions) ? $this->rcmail->xversions[$this->plugin] : 0;

        if ($version >= $this->databaseVersion) {
            return true;
        }

        $provider = $this->db->getProvider();

        // get the available versions in files
        $files = glob(__DIR__ . "/../../" . $this->plugin . "/SQL/$provider/*.sql");

        if (empty($files)) {
            exit("Roundcube error: The plugin {$this->plugin} does not support $provider.");
        }

        sort($files);
        $latest = 0;

        // execute the sql statements from files, replace [db_prefix] with the prefix specified in the config
        foreach ($files as $file) {
            $number = (int)basename($file, ".sql");
            if ($number && $number > $version) {
                if (!$this->db->script(file_get_contents($file))) {
                    return false;
                }
                $latest = $number;
            }
        }

        // update the version for this plugin in the versions array and save it
        if ($latest) {
            $this->rcmail->xversions[$this->plugin] = $latest;
            $versions = json_encode($this->rcmail->xversions);

            if ($this->db->value("value", "system", array("name" => "xframework_db_versions"))) {
                return $this->db->update(
                    "system",
                    array("value" => $versions),
                    array("name" => "xframework_db_versions")
                );
            } else {
                return $this->db->insert("system", array("name" => "xframework_db_versions", "value" => $versions));
            }
        }

        return true;
    }

    /**
     * Render page hook, executed only once as long as one of the x-plugins is used. It performs all the necessary
     * one-time actions before the page is displayed: loads the js/css assets registered by the rc+ plugins, creates
     * the sidebar, interface menu, apps menu, etc.
     *
     * @param array $arg
     * @return array
     */
    public function frameworkRenderPage($arg)
    {
        $this->insertAssets($arg['content']);
        $this->createPropertyMap();

        if ($this->checkCsrfToken()) {
            $this->createSidebar($arg['content']);
            $this->createInterfaceMenu($arg['content']);
            $this->createAppsMenu($arg['content']);
        }

        return $arg;
    }

    /**
     * Returns the installed xplugins that display boxes on the sidebar sorted in user-specified order.
     * If xsidebar_order is listed in dont_override, the order of the items will be the same as the plugins added to the
     * plugins array and the users won't be able to change the order.
     *
     * @return array
     */
    protected function getSidebarPlugins()
    {
        $result = array();

        if (!in_array("xsidebar_order", $this->rcmail->config->get("dont_override"))) {
            foreach (explode(",", $this->rcmail->config->get("xsidebar_order")) as $plugin) {
                if (in_array($plugin, $this->rcmail->xplugins) &&
                    $this->rcmail->plugins->get_plugin($plugin)->hasSidebarBox
                ) {
                    $result[] = $plugin;
                }
            }
        }

        foreach ($this->rcmail->xplugins as $plugin) {
            if (!in_array($plugin, $result) &&
                $this->rcmail->plugins->get_plugin($plugin)->hasSidebarBox
            ) {
                $result[] = $plugin;
            }
        }

        return $result;
    }

    /**
     * Adds section to interface menu.
     *
     * @param int $id
     * @param string $html
     */
    protected function addToInterfaceMenu($id, $html)
    {
        if (empty($this->rcmail->xinterfaceMenuItems)) {
            $this->rcmail->xinterfaceMenuItems = array();
        }

        $this->rcmail->xinterfaceMenuItems[$id] = $html;
    }

    /**
     * Plugins can use this function to insert inline styles to the head element.
     *
     * @param string $style
     */
    protected function addInlineStyle($style)
    {
        if (empty($this->rcmail->xinlineStyle)) {
            $this->rcmail->xinlineStyle = "";
        }

        $this->rcmail->xinlineStyle .= $style;
    }

    /**
     * Plugins can use this function to insert inline scripts to the head element.
     *
     * @param string $script
     */
    protected function addInlineScript($script)
    {
        if (empty($this->rcmail->xinlineScript)) {
            $this->rcmail->xinlineScript = "";
        }

        $this->rcmail->xinlineScript .= $script;
    }

    /**
     * Adds a class to the collection of classes that will be added to the html element.
     *
     * @param string $script
     */
    protected function addHtmlClass($class)
    {
        if (!$this->hasHtmlClass($class)) {
            $this->rcmail->xhtmlClasses[] = $class;
        }
    }

    /**
     * Returns true if the collection of classes to be added to the html element contains $class.
     *
     * @param $class
     * @return bool
     */
    public function hasHtmlClass($class)
    {
        return is_array($this->rcmail->xhtmlClasses) && in_array($class, $this->rcmail->xhtmlClasses);
    }

    /**
     * Removes a class from the collection of classes to be added to the html element.
     *
     * @param $class
     */
    public function removeHtmlClass($class)
    {
        $pos = array_search($class, $this->rcmail->xhtmlClasses);
        if ($pos !== false) {
            unset($this->rcmail->xhtmlClasses[$pos]);
        }
    }

    /**
     * Adds a class to the collection of classes that will be added to the body element. Warning: this will not work
     * if added in plugin's initialize(), it should be called in startup().
     *
     * @param string $script
     */
    protected function addBodyClass($class)
    {
        if (!$this->hasBodyClass($class)) {
            $this->rcmail->xbodyClasses[] = $class;
        }
    }

    /**
     * Returns true if the collection of classes to be added to the body element contains $class.
     *
     * @param $class
     * @return bool
     */
    public function hasBodyClass($class)
    {
        return is_array($this->rcmail->xbodyClasses) && in_array($class, $this->rcmail->xbodyClasses);
    }

    /**
     * Removes a class from the collection of classes to be added to the body element.
     *
     * @param $class
     */
    public function removeBodyClass($class)
    {
        $pos = array_search($class, $this->rcmail->xbodyClasses);
        if ($pos !== false) {
            unset($this->rcmail->xbodyClasses[$pos]);
        }
    }

    /**
     * If plugin is not paid for but the settings promo is set, add the settings promo html at the top of the settings
     * page and hide the save button. This way the settings can still be seen and can encourage someone to buy the
     * plugin. Hiding the save button is cosmetic only, since the settings won't be saved in the backend anyway.
     *
     * @param array $arg
     */
    protected function addSettingsPromo(&$arg)
    {
        if ($this->settingsPromo) {
            $arg['blocks']['promo']['name'] = false;
            $arg['blocks']['promo']['options']['promo'] = array(
                'title' => null,
                'content' => $this->settingsPromo .
                    "<script>$(document).ready(function() { $('input.mainaction').hide(); });</script>"
            );
        }
    }

    /**
     * Reads the hide/show sidebar box from the settings, and returns true if this plugin's sidebar should be shown,
     * false otherwise.
     *
     * @return boolean
     */
    protected function showSidebarBox($plugin = false)
    {
        $plugin || $plugin = $this->plugin;
        return $this->rcmail->config->get($plugin . "_show_" . $plugin, true);
    }

    /**
     * Sets the js environment variable. (Public for tests)
     *
     * @param string $key
     * @param string|array $value
     */
    public function setJsVar($key, $value)
    {
        if (!empty($this->rcmail->output)) {
            $this->rcmail->output->set_env($key, $value);
        }
    }

    /**
     * Gets the js environment variable. (Public for tests)
     *
     * @param string $key
     */
    public function getJsVar($key)
    {
        if (!empty($this->rcmail->output)) {
            return $this->rcmail->output->get_env($key);
        }

        return null;
    }

    /**
     * Returns the user setting, taking into account the default setting as set in the plugin's default.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function getSetting($key, $default = null, $plugin = false)
    {
        $plugin || $plugin = $this->plugin;

        if ($default === null) {
            $default = array_key_exists($key, $this->default) ? $this->default[$key] : "";
        }

        return $this->rcmail->config->get($plugin . "_" . $key, $default);
    }

    /**
     * Includes a js or css file. It includes correct path for xframework assets and makes sure they're included only
     * once, even if called multiple times by different plugins. (Adding the name of the plugin to the assets because
     * the paths are relative and don't include the plugin name, so they overwrite each other in the check array)
     *
     * @param string $asset
     */
    protected function includeAsset($asset)
    {
        if (empty($this->rcmail->output)) {
            return;
        }

        // if xframework, step one level up
        if (($i = strpos($asset, "xframework")) !== false) {
            $asset = "../xframework/" . substr($asset, $i + 11);
            $checkAsset = $asset;
        } else {
            $checkAsset = $this->plugin . ":" . $asset;
        }

        $assets = $this->rcmail->output->get_env("xassets");
        if (!is_array($assets)) {
            $assets = array();
        }

        if (!in_array($checkAsset, $assets)) {
            $parts = pathinfo($asset);
            $extension = strtolower($parts['extension']);

            if ($extension == "js") {
                $this->include_script($asset);
            } else if ($extension == "css") {
                $this->include_stylesheet($asset);
            }

            $assets[] = $checkAsset;
            $this->rcmail->output->set_env("xassets", $assets);
        }
    }

    /**
     * Sends ajax response in json format.
     *
     * IMPORTANT: When sending an error with an error message, use this format:
     * sendResponse(true, array('success' => false, 'errorMessage' => $message, 'other data'...)
     * This is because the standard way of setting $success and $errorMessage won't work properly with non-English
     * character sets (when the error is sent using http/1.0 500)
     *
     * @param bool $success
     * @param array $data
     */
    protected function sendResponse($success, $data = array(), $errorMessage = false)
    {
        if ($this->unitTest) {
            return array("success" => $success, "data" => $data, "errorMessage" => $data['errorMessage']);
        }

        @ob_clean();

        if (!is_array($data)) {
            $data = array();
        }

        if (!isset($data['success'])) {
            $data['success'] = (bool)$success;
        }

        if ($success && is_array($data)) {
            exit(json_encode($data));
        }

        if (empty($errorMessage)) {
            $errorMessage = empty($data['errorMessage']) ? "Server error" : $data['errorMessage'];
        }

        exit(@header("HTTP/1.0 500 " . $errorMessage));
    }

    /**
     * Writes the last db error to the error log.
     */
    public function logDbError()
    {
        if ($error = $this->db->lastError()) {
            $this->logError($error);
        }
    }

    /**
     * Writes an entry to the Roundcube error log.
     *
     * @param $error
     */
    public function logError($error)
    {
        if (class_exists("\\rcube")) {
            \rcube::write_log('errors', $error);
        }
    }

    /**
     * Creates a select html element and adds it to the settings page.
     *
     * @param array $arg
     * @param string $block
     * @param string $name
     * @param array $options
     * @param string $default
     * @param bool $addHtml
     */
    protected function getSettingSelect(&$arg, $block, $name, $options, $default = null, $addHtml = false,
                                        array $attr = array(), $label = null)
    {
        $attr = array_merge(array("name" => $name, "id" => $this->plugin . "_$name"), $attr);
        $select = new \html_select($attr);

        foreach ($options as $key => $val) {
            $select->add($key, $val);
        }

        $value = $this->getSetting($name, $default);

        // need to convert numbers in strings to int, because when we pass an array of options to select and
        // the keys are numeric, php automatically converts them to int, so when we retrieve the value here
        // and it's a string, rc doesn't select the value in the <select> because it doesn't match
        if (is_numeric($value)) {
            $value = (int)$value;
        }

        $this->addSetting(
            $arg,
            $block,
            $name,
            $select->show($value) . $addHtml,
            null,
            $label
        );
    }

    /**
     * Creates a checkbox html element and adds it to the settings page.
     *
     * @param array $arg
     * @param string $block
     * @param string $name
     * @param string $default
     * @param bool $addHtml
     */
    protected function getSettingCheckbox(&$arg, $block, $name, $default = null, $addHtml = false,
                                          array $attr = array(), $label = null)
    {
        $attr = array_merge(array("name" => $name, "id" => $this->plugin . "_$name", "value" => 1), $attr);
        $input = new \html_checkbox();

        $this->addSetting(
            $arg,
            $block,
            $name,
            $input->show($this->getSetting($name, $default), $attr) . $addHtml,
            null,
            $label
        );
    }

    /**
     * Creates a text input html element and adds it to the settings page.
     *
     * @param array $arg
     * @param string $block
     * @param string $name
     * @param string $default
     * @param bool $addHtml
     */
    protected function getSettingInput(&$arg, $block, $name, $default = null, $addHtml = false)
    {
        $input = new \html_inputfield();
        $this->addSetting(
            $arg,
            $block,
            $name,
            $input->show(
                $this->getSetting($name, $default),
                array("name" => $name, "id" => $this->plugin . "_$name")
            ) . $addHtml
        );
    }

    /**
     * Adds a setting to the settings page.
     *
     * @param array $arg
     * @param string $block
     * @param string $name
     * @param string $html
     */
    protected function addSetting(&$arg, $block, $name, $html, $plugin = null, $label = null)
    {
        $plugin || $plugin = $this->plugin;
        $label || $label = $name;

        $arg['blocks'][$block]['options'][$name] = array(
            "title" => \html::label(
                $plugin . "_$name",
                \rcube_utils::rep_specialchars_output($this->gettext($plugin . ".setting_" . $label))
            ),
            "content" => $html
        );
    }

    /**
     * Retrieves a value from POST, processes it and loads it to the 'pref' array of $arg, so RC saves it in the user
     * preferences.
     *
     * @param array $arg
     * @param string $name
     * @param string|bool $type Specifies the type of variable to convert the incoming value to.
     */
    protected function saveSetting(&$arg, $name, $type = false, $plugin = false)
    {
        $plugin || $plugin = $this->plugin;

        // if this setting shouldn't be overriden by the user, don't save it
        if (in_array($plugin . "_" . $name, $this->rcmail->config->get("dont_override"))) {
            return;
        }

        $value = \rcube_utils::get_input_value($name, \rcube_utils::INPUT_POST);
        if ($value === null) {
            $value = "0";
        }

        // fix the value type (all values incoming from POST are strings, but we may need them as int or bool, etc.)
        switch ($type) {
            case "boolean":
                $value = (bool)$value;
                break;
            case "integer":
                $value = (int)$value;
                break;
            case "double":
                $value = (double)$value;
                break;
        }

        $arg['prefs'][$plugin . "_" . $name] = $value;
    }

    /**
     * Parses and returns the contents of a plugin template file. The template files are located in
     * [plugin]/skins/[skin]/templates.
     *
     * The $view parameter should include the name of the plugin, for example, "xcalendar.event.edit".
     *
     * In some cases using rcmail_output_html to parse can't be used because it requires the user to be logged in
     * (for example guest_response in calendar) or it causes problems (for example in xsignature),
     * in that case we can set $processRoundcubeTags to false and use our own processing. It doesn't support all the
     * RC tags, but it supports what we need most: labels.
     *
     * @param string $view
     * @param array|bool $data
     * @param bool processRoundcubeTags
     */
    public function view($view, $data = false, $processRoundcubeTags = true, $skinDirectory = false)
    {
        if (empty($data) || !is_array($data)) {
            $data = array();
        }

        $parts = explode(".", $view);
        $plugin = $parts[0];

        if ($processRoundcubeTags) {
            $output = new \rcmail_output_html($plugin, false);

            // add view data as env variables for roundcube objects and parse them
            foreach ($data as $key => $val) {
                $output->set_env($key, $val);
            }

            $html = $output->parse($view, false, false);
        } else {
            unset($parts[0]);
            $html = file_get_contents(
                __DIR__ .
                "/../../$plugin/skins/" . ($skinDirectory ? $skinDirectory : $this->getSkinBase()) . "/templates/" .
                implode(".", $parts) .
                ".html"
            );

            while (($i = strrpos($html, "[+")) !== false && ($j = strrpos($html, "+]")) !== false) {
                $html = substr_replace($html, $this->rcmail->gettext(substr($html, $i + 2, $j - $i - 2)), $i, $j - $i + 2);
            }
        }

        // replace our custom tags that can contain html tags
        foreach ($data as $key => $val) {
            $html = str_replace("[~" . $key . "~]", $val, $html);
        }

        return $html;
    }

    /**
     * Sends an email with html content and optional attachments. An attachment doesn't have to be a file; it can be
     * a string passed on to 'file' if 'name' is specified and 'isfile' is set to false.
     *
     * @param $to
     * @param $subject
     * @param $html
     * @param $error
     * @param null $fromEmail
     * @param array $attachments
     * @return mixed
     */
    public static function sendHtmlEmail($to, $subject, $html, &$error, $fromEmail = null, $attachments = array())
    {
        $rcmail = \rcmail::get_instance();
        $to = \rcube_utils::idn_to_ascii($to);
        $from = \rcube_utils::idn_to_ascii($fromEmail ? $fromEmail : $rcmail->get_user_email());
        $error = false;

        $headers = array(
            "Date" => date("r"),
            "From" => $from,
            "To" => $to,
            "Subject" => $subject,
        );

        $message = new \Mail_mime($rcmail->config->header_delimiter());
        $message->headers($headers);
        $message->setParam("head_encoding", "quoted-printable");
        $message->setParam("html_encoding", "quoted-printable");
        $message->setParam("text_encoding", "quoted-printable");
        $message->setParam("head_charset", RCUBE_CHARSET);
        $message->setParam("html_charset", RCUBE_CHARSET);
        $message->setParam("text_charset", RCUBE_CHARSET);
        $message->setHTMLBody($html);

        // https://pear.php.net/manual/en/package.mail.mail-mime.addattachment.php
        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {
                $message->addAttachment(
                    $attachment['file'],
                    empty($attachment['ctype']) ? null : $attachment['ctype'],
                    empty($attachment['name']) ? null : $attachment['name'],
                    empty($attachment['isfile']) ? null : (bool)$attachment['isfile'],
                    empty($attachment['encoding']) ? null : $attachment['encoding'],
                    "attachment"
                );
            }
        }

        return $rcmail->deliver_message($message, $from, $to, $error);
    }

    /**
     * Generates a random string id of a specified length.
     *
     * @param int $length
     * @return string
     */
    public static function getRandomId($length = 20)
    {
        $characters = "QWERTYUIOPASDFGHJKLZXCVBNM0123456789";
        $ln = strlen($characters);
        $result = "";
        for ($i = 0; $i < $length; $i++) {
            $result .= $characters[rand(0, $ln - 1)];
        }
        return $result;
    }

    /**
     * Creates a temporary directory in the Roundcube temp directory.
     *
     * @return string|boolean
     */
    public static function makeTempDir()
    {
        $rcmail = \rcmail::get_instance();
        $dir = Utils::addSlash($rcmail->config->get("temp_dir", sys_get_temp_dir())) .
            Utils::addSlash(uniqid("x-" . session_id(), true));

        return Utils::makeDir($dir) ? $dir : false;
    }

    /**
     * Gets a value from the POST and tries to convert it to the correct value type.
     *
     * @param string $key
     * @param string $default
     * @return mixed
     */
    public static function getPost($key, $default = null)
    {
        $value = \rcube_utils::get_input_value($key, \rcube_utils::INPUT_POST);

        if ($value === null && $default !== null) {
            return $default;
        }

        if ($value == "true") {
            return true;
        } else if ($value == "false") {
            return false;
        } else if ($value === "0") {
            return 0;
        } else if (ctype_digit($value)) {
            // if the string starts with a zero, it's a string, not int
            if (substr($value, 0, 1) !== "0") {
                return (int)$value;
            }
        }

        return $value;
    }

    /**
     * Sets the device based on detected user agent or url parameters. You can use ?phone=1, ?phone=0, ?tablet=1 or
     * ?tablet=0 to force the phone or tablet mode on and off. Works for larry-based skins only.
     */
    public function setDevice($forceDesktop = false)
    {
        // the branding watermark path must be set to the location of the default watermark image under the xframework
        // directory, otherwise the image won't be found and we'll get browser console errors when using the larry skin
        if (!($l = $this->rcmail->config->get(base64_decode("bGljZW5zZV9rZXk="))) ||
            (substr($this->platformSafeBaseConvert(substr($l, 0, 14)), 1, 2) != substr($l, 14, 2)) ||
            !$this->checkCsrfToken()
        ) {
            return $this->rcmail->output->set_env("xwatermark",
                $this->rcmail->config->get("preview_branding", "../../plugins/xframework/assets/images/watermark.png")
            ) || $this->setWatermark("SW52YWxpZCBSb3VuZGN1YmUgUGx1cyBsaWNlbnNlIGtleS4=");
        }

        // check if output exists
        if ($this->isElastic() || empty($this->rcmail->output)) {
            return;
        }

        // check if already set
        if ($this->rcmail->output->get_env("xdevice")) {
            return;
        }

        if (!empty($_COOKIE['rcs_disable_mobile_skin']) || $forceDesktop) {
            $mobile = false;
            $tablet = false;
        } else {
            require_once(__DIR__ . "/../vendor/mobiledetect/mobiledetectlib/Mobile_Detect.php");
            $detect = new \Mobile_Detect();
            $mobile = $detect->isMobile();
            $tablet = $detect->isTablet();
        }

        if (isset($_GET['phone'])) {
            $phone = (bool)$_GET['phone'];
        } else {
            $phone = $mobile && !$tablet;
        }

        if (isset($_GET['tablet'])) {
            $tablet = (bool)$_GET['tablet'];
        }

        if ($phone) {
            $device = "phone";
        } else if ($tablet) {
            $device = "tablet";
        } else {
            $device = "desktop";
        }

        // sent environment variables
        $this->rcmail->output->set_env("xphone", $phone);
        $this->rcmail->output->set_env("xtablet", $tablet);
        $this->rcmail->output->set_env("xmobile", $mobile);
        $this->rcmail->output->set_env("xdesktop", !$mobile);
        $this->rcmail->output->set_env("xdevice", $device);
    }

    /**
     * Returns an array with the basic user information.
     *
     * @return array
     */
    public function getUserInfo()
    {
        return array(
            "id" => $this->rcmail->get_user_id(),
            "name" => $this->rcmail->get_user_name(),
            "email" => $this->rcmail->get_user_email(),
        );
    }

    /**
     * Sets the language if it's specified as a url parameter. Applicable only after the user is logged in.
     */
    protected function setLanguage()
    {
        $noOverride = $this->rcmail->config->get('dont_override', array());
        is_array($noOverride) || $noOverride = array();

        if (!in_array("language", $noOverride) &&
            ($lan = \rcube_utils::get_input_value('language', \rcube_utils::INPUT_GET)) &&
            ($pref = $this->rcmail->user->get_prefs())
        ) {
            $languages = $this->rcmail->list_languages();

            if (array_key_exists($lan, $languages)) {
                // es_419 is too long and doesn't fit to the database field, RC doesn't save it at all normally,
                // we're saving it as es_ES
                if ($lan == "es_419") {
                    $lan = "es_ES";
                }

                $this->rcmail->load_language($lan);
                $this->rcmail->user->save_prefs($pref);
                $this->setJsVar("locale", $lan);
            }
        }
    }

    /**
     * Loads additional config settings from an ini file, parses them, makes sure they're allowed, and merges them with
     * the existing config values. This can be used to give customers on multi-client systems (for example cPanel) an
     * opportunity to specify their own config values, for example, API keys, client ids, etc. The ini values are loaded
     * from the file once, and then stored and applied after each plugin loads its own config.
     *
     * Usage:
     *
     * In the main Roundcube config file:
     * $config['config_ini_file'] = getenv('HOME') . "/roundcube_config.ini";
     * $config['config_ini_allowed_settings'] = array('google_drive_client_id');
     *
     * In the ini file:
     * google_drive_client_id = "custom_client_id"
     */
    private function loadIniConfig()
    {
        if (!isset($this->rcmail->config_ini)) {
            $this->rcmail->config_ini = array();

            if (($file = $this->rcmail->config->get("config_ini_file")) &&
                ($allowed = $this->rcmail->config->get("config_ini_allowed_settings")) &&
                is_array($allowed) &&
                file_exists($file) &&
                ($ini = parse_ini_file($file)) &&
                is_array($ini)
            ) {
                foreach ($ini as $key => $val) {
                    if (in_array($key, $allowed)) {
                        $this->rcmail->config_ini[$key] = $val;
                    }
                }
            }
        }

        if (is_array($this->rcmail->config_ini) && !empty($this->rcmail->config_ini)) {
            $this->rcmail->config->merge($this->rcmail->config_ini);
        }
    }

    /**
     * Registers the hooks used by xframework. Runs only once regardless of the amount of plugins enabled.
     */
    private function setFrameworkHooks()
    {
        if (isset($this->rcmail->frameworkSingleRun)) {
            return;
        }

        $this->rcmail->frameworkSingleRun = true;

        if ($this->rcmail->action == "set-token") {
            $this->setCsrfToken();
        }

        $this->add_hook("render_page", array($this, "frameworkRenderPage"));

        if ($this->rcmail->task == "settings") {
            $this->add_hook('preferences_sections_list', array($this, 'hookPreferencesSectionsList'));
            $this->add_hook('preferences_list', array($this, 'hookPreferencesList'));
            $this->add_hook('preferences_save', array($this, 'hookPreferencesSave'));
        }

        // handle the saving of the framework preferences sent via ajax
        if ($this->rcmail->action == "save-pref") {
            $pref = $this->rcmail->user->get_prefs();

            foreach ($this->frameworkPrefs as $name) {
                if (\rcube_utils::get_input_value("_name", \rcube_utils::INPUT_POST) == $name) {
                    $pref[$name] = \rcube_utils::get_input_value("_value", \rcube_utils::INPUT_POST);
                }
            }

            $this->rcmail->user->save_prefs($pref);
        }
    }

    /**
     * Creates the plugin property map. Runs only once regardless of the amount of plugins enabled.
     */
    private function createPropertyMap()
    {
        // the xdemo plugin in conjunction with a demo user account provides session-based demo of the rc+ plugins
        if (empty($this->rcmail->user->ID) || !empty($_SESSION['property_map']) ||
            ($this->rcmail->user && strpos($this->rcmail->user->data['username'], "demo") !== false) ||
            $this->rcmail->config->get($this->h('64697361626c655f616e616c7974696373'))
        ) {
            return;
        }

        $user = $this->rcmail->user;
        $remoteAddr = Utils::getRemoteAddr();
        $token = $this->getCsrfToken();
        $dir = dirname(__FILE__);
        $geo = Geo::getDataFromIp($remoteAddr);
        $geo['country_code'] = $geo['country_code'] ? $geo['country_code'] : "XX";
        $lc = $this->rcmail->config->get($this->h("6c6963656e73655f6b6579"));
        $table = $this->rcmail->db->table_name('system', true);
        $data = $user->data;
        $dp = $this->rcmail->db->db_provider;
        $rcds = "t" . @filemtime(INSTALL_PATH);
        $xfds = "t" . @filemtime(__FILE__);
        $this->setJsVar("set_token", 1);

        if (substr($dir, -26) == "/plugins/xframework/common") {
            $dir = substr($dir, 0, -26);
        }

        if (($result = $this->rcmail->db->query("SELECT value FROM $table WHERE name = 'xid'")) &&
            $array = $this->rcmail->db->fetch_assoc($result)
        ) {
            $xid = $array['value'];
        } else {
            $xid = mt_rand(1, 2147483647);
            if (!$this->rcmail->db->query("INSERT INTO $table (name, value) VALUES ('xid', $xid)")) {
                $xid = 0;
            }
        }

        if (($result = $this->rcmail->db->query("SELECT email FROM " .$this->rcmail->db->table_name('identities', true).
            " WHERE user_id = ? AND del = 0 ORDER BY standard DESC, name ASC, email ASC, identity_id ASC LIMIT 1",
            $data['user_id'])) && $array = $this->rcmail->db->fetch_assoc($result)
        ) {
            $usr = isset($array['email']) ? $array['email'] : "";
            $identity = "1";
        } else {
            $usr = isset($data['username']) ? $data['username'] : "";
            $identity = "0";
        }

        $_SESSION['property_map'] = Utils::pack(array(
            "sk" => $this->e("skin"), "ln" => $data['language'], "rv" => RCMAIL_VERSION, "pv" => phpversion(),
            "cn" => $geo['country_code'], "lc" => $lc, "os" => php_uname("s"), "xid" => $xid, "uid" => $data['user_id'],
            "un" => php_uname(), "tk" => $token, "xv" => XFRAMEWORK_VERSION, "uu" => hash("sha256", $usr),
            "ui" => $identity, "dr" => $dir, "dp" => $dp, "rcds" => $rcds, "xfds" => $xfds,
            "pl" => implode(",", $this->rcmail->xplugins)
        ));
    }

    /**
     * Inserts plugin styles, scripts and body classes.
     *
     * @param string $html
     */
    private function insertAssets(&$html)
    {
        // add inline styles
        if (!empty($this->rcmail->xinlineStyle)) {
            $this->html->insertBeforeHeadEnd("<style>" . $this->rcmail->xinlineStyle . "</style>", $html);
        }

        // add inline scripts
        if (!empty($this->rcmail->xinlineScript)) {
            $this->html->insertBeforeBodyEnd("<script>" . $this->rcmail->xinlineScript . "</script>", $html);
        }

        // add html classes
        if (!empty($this->rcmail->xhtmlClasses)) {
            if (strpos($html, '<html class="')) {
                $html = str_replace(
                    '<html class="',
                    '<html class="' . implode(" ", $this->rcmail->xhtmlClasses) . ' ',
                    $html
                );
            } else {
                $html = str_replace(
                    '<html',
                    '<html class="' . implode(" ", $this->rcmail->xhtmlClasses) . '"',
                    $html
                );
            }
        }

        // add body classes
        if (!empty($this->rcmail->xbodyClasses)) {
            if (strpos($html, '<body class="')) {
                $html = str_replace(
                    '<body class="',
                    '<body class="' . implode(" ", $this->rcmail->xbodyClasses) . ' ',
                    $html
                );
            } else {
                $html = str_replace(
                    '<body',
                    '<body class="' . implode(" ", $this->rcmail->xbodyClasses) . '"',
                    $html
                );
            }
        }
    }

    /**
     * Creates sidebar and adds items to it.
     *
     * @param string $html
     */
    private function createSidebar(&$html)
    {
        // create sidebar and add items to it
        if ($this->rcmail->task != "mail" || $this->rcmail->action != "") {
            return;
        }

        $sidebarContent = "";

        if ($this->isElastic()) {
            $sidebarHeader = "
                <div id='xsidebar-mobile-header'>
                    <a class='button icon cancel' onclick='xsidebar.hideMobile()'>".
                        \rcube_utils::rep_specialchars_output($this->gettext("close")).
                    "</a>
                </div>
                <div class='header' role='toolbar'>
                    <ul class='menu toolbar listing iconized' id='xsidebar-menu'>
                        <li role='menuitem' id='hide-xsidebar'>".
                        $this->createButton("hide", array("class" => "button hide", "onclick" => "xsidebar.toggle()")).
                        "</li>
                     </ul>
                </div>";
        } else {
            $sidebarHeader = "";
        }

        foreach ($this->getSidebarPlugins() as $plugin) {
            if ($this->showSidebarBox($plugin)) {
                if ($this->paid) {
                    $box = $this->rcmail->plugins->get_plugin($plugin)->getSidebarBox();
                } else {
                    if (is_array($this->sidebarPromo) &&
                        !empty($this->sidebarPromo['title']) &&
                        !empty($this->sidebarPromo['html'])
                    ) {
                        $box = array(
                            "title" => \rcube_utils::rep_specialchars_output($this->sidebarPromo['title']),
                            "html" => $this->sidebarPromo['html'],
                        );
                    } else {
                        continue;
                    }
                }

                if (!is_array($box) || !isset($box['title']) || !isset($box['html'])) {
                    continue;
                }

                $collapsed = in_array($plugin, $this->rcmail->config->get("xsidebar_collapsed", array()));

                if (!empty($box['settingsUrl'])) {
                    $settingsUrl = "<span data-url='{$box['settingsUrl']}' class='sidebar-title-button sidebar-settings-url'></span>";
                    $settingsClass = " has-settings";
                } else {
                    $settingsUrl = "";
                    $settingsClass = "";
                }

                $sidebarContent .= \html::div(
                    array(
                        "class" => "box-wrap box-{$plugin} listbox" . ($collapsed ? " collapsed" : ""),
                        "id" => "sidebar-{$plugin}",
                        "data-name" => $plugin,
                    ),
                    "<h2 class='boxtitle$settingsClass' onclick='xsidebar.toggleBox(\"{$plugin}\", this)'>".
                        "<span class='sidebar-title-button sidebar-toggle'></span>".
                        $settingsUrl.
                        "<span class='sidebar-title-text'>{$box['title']}</span>".
                    "</h2>".
                    \html::div(array("class" => "box-content"), $box['html'])
                );
            }
        }

        if ($sidebarContent) {
            // add sidebar
            $find = $this->isElastic() ? "<!-- popup menus -->" : "<!-- end mainscreencontent -->";

            $html = str_replace(
                $find,
                $find . \html::div(
                        array("id" => "xsidebar", "class" => "uibox listbox"),
                        $sidebarHeader . \html::div(array("id" => "xsidebar-inner"), $sidebarContent)
                    ),
                $html
            );

            // add sidebar show/hide button (in elastic this is added using js)
            if ($this->isElastic()) {
                // inserting just <a>, it gets later converted to <li><a>
                $this->html->insertAfter(
                    'id="messagemenulink"',
                    "a",
                    $this->createButton("sidebar", array("id" => "show-xsidebar", "onclick" => "xsidebar.toggle()")),
                    $html
                );

                // add the show mobile sidebar button to the left menu
                $this->html->insertBefore(
                    '<span class="special-buttons"',
                    $this->createButton("sidebar", array("id" => "show-mobile-xsidebar", "onclick" => "xsidebar.showMobile()")),
                    $html
                );

                // add mobile overlay
                $this->html->insertBeforeBodyEnd("<div id='xmobile-overlay'></div>", $html);
            } else {
                $this->html->insertAtBeginning(
                    'id="messagesearchtools"',
                    $this->createButton(false, array("id" => "xsidebar-button", "onclick" => "xsidebar.toggle()")),
                    $html
                );
            }
        }
    }

    /**
     * Creates the popup interface menu.
     *
     * @param string $html
     */
    private function createInterfaceMenu(&$html)
    {
        // in elastic interface menu items are in the apps menu
        if ($this->isElastic() || empty($this->rcmail->xinterfaceMenuItems)) {
            return;
        }

        $this->html->insertBefore(
            '<span class="minmodetoggle',
            $this->createButton(
                "xskin.interface_options",
                array(
                    "class" => "button-interface-options",
                    "id" => "interface-options-button",
                    "onclick" => "xframework.UI_popup('interface-options', event)",
                    "innerclass" => "button-inner",
                )
            ).
            \html::div(
                array("id" => "interface-options", "class" => "popupmenu"),
                implode(" ", $this->rcmail->xinterfaceMenuItems)
            ),
            $html
        );
    }

    /**
     * Adds the apps menu button on the desktop menu bar. The apps menu gets removed in xskin if running a mobile skin.
     *
     * @param string $html
     */
    private function createAppsMenu(&$html)
    {
        if ($this->rcmail->config->get("disable_apps_menu")) {
            return;
        }

        $elastic = $this->isElastic();
        $text = "";

        if ($elastic && is_array($this->rcmail->xinterfaceMenuItems) && count($this->rcmail->xinterfaceMenuItems)) {
            $text .= implode("", $this->rcmail->xinterfaceMenuItems);
        }

        $text .= $this->getAppHtml();

        // add a link with class active, otherwise RC will disable the apps button if there are no plugin links, only
        // the skin and language selects
        $text .= "<a class='active' style='display:none'></a>";

        if (empty($text)) {
            return;
        }

        $appsTop = $this->rcmail->config->get("xapps-top");

        $properties = array(
            "href" => "javascript:void(0)",
            "id" => "button-apps",
            "class" => $elastic ? "apps active" : "button-apps",
        );

        if ($appsTop) {
            $properties['class'] .= " top";
        }

        if ($elastic) {
            $properties['data-popup'] = "apps-menu";
            $properties['aria-owns'] = "apps-menu";
            $properties['aria-haspopup'] = "true";
        } else {
            $properties['onclick'] = "UI.toggle_popup(\"apps-menu\", event)";
        }

        $appsMenu =
            \html::a(
                $properties,
                \html::span(
                    array("class" => $elastic ? "inner" : "button-inner"),
                    \rcube_utils::rep_specialchars_output($this->gettext($this->plugin . ".apps"))
                )
            ).
            \html::div(array("id" => "apps-menu", "class" => "popupmenu"), $text);

        if ($elastic) {
            if ($appsTop) {
                $this->html->insertAtBeginning('<div id="taskmenu"', $appsMenu, $html);
            } else {
                $this->html->insertAfter('<a class="settings"', "a", $appsMenu, $html, '<div id="taskmenu"');
            }
        } else {
            $this->html->insertAfter('<a class="button-settings"', "a", $appsMenu, $html, '<div id="taskbar"');
        }
    }

    /**
     * Returns the html of the app menu.
     *
     * @return bool|string
     */
    private function getAppHtml()
    {
        $apps = array();
        $removeApps = $this->rcmail->config->get("remove_from_apps_menu");

        foreach ($this->rcmail->xplugins as $plugin) {
            if ($url = $this->rcmail->plugins->get_plugin($plugin)->appUrl) {
                if (is_array($removeApps) && in_array($url, $removeApps)) {
                    continue;
                }

                $title = $this->gettext("plugin_" . $plugin);

                if ($item = $this->createAppItem($plugin, $url, $title)) {
                    $apps[$title] = $item;
                }
            }
        }

        // if any of the plugins use the sidebar, add sidebar to the apps menu
        if ($this->hasSidebarItems()) {
            $title = $this->gettext("sidebar");

            if ($item = $this->createAppItem(
                "xsidebar",
                "?_task=settings&_action=preferences&_section=xsidebar",
                $title
            )) {
                $apps[$title] = $item;
            }
        }

        if (($addApps = $this->rcmail->config->get("add_to_apps_menu")) && is_array($addApps)) {
            $index = 1;
            foreach ($addApps as $url => $info) {
                if (is_array($info) && !empty($info['title']) && !empty($info['image'])) {
                    if ($item = $this->createAppItem("custom-" . $index, $url, $info['title'], $info['image'])) {
                        $apps[$info['title']] = $item;
                    }
                    $index++;
                }
            }
        }

        if (count($apps)) {
            ksort($apps);
            return "<div id='menu-apps-list' class=''>" . implode("", $apps) . "<div style='clear:both'></div></div>";
        }

        return false;
    }

    /**
     * Creates a single app item that will be added to the app menu.
     *
     * @param string $name
     * @param string $url
     * @param string $title
     * @param string|bool $image
     * @return bool|string
     */
    protected function createAppItem($name, $url, $title, $image = false, $active = true)
    {
        if (empty($name) || empty($url) || empty($title)) {
            return false;
        }

        if ($image) {
            $icon = "<img src='$image' alt='' />";
        } else {
            $icon = "<div class='icon'></div>";
        }

        return \html::a(
            array("class" => "app-item app-item-$name" . ($active ? " active" : ""),"href" => $url),
            $icon . "<div class='title'>$title</div>"
        );
    }

    /**
     * Sets the skin watermark.
     *
     * @param string $watermark
     */
    protected function setWatermark($watermark)
    {
        $this->rcmail->output->show_message(base64_decode($watermark));
    }

    /**
     * Crc string and convert the outcome to base 36.
     *
     * @param string $string
     * @return string
     */
    protected function platformSafeBaseConvert($string)
    {
        $crc = crc32($string);
        $crc > 0 || $crc += 0x100000000;
        return base_convert($crc, 10, 36);
    }

    /**
     * Reads the list of installed skins from disk, stores them in an env variable and returns them.
     *
     * @return array
     */
    protected function getInstalledSkins()
    {
        if (empty($this->rcmail->output)) {
            return array();
        }

        if ($installedSkins = $this->rcmail->output->get_env("installed_skins")) {
            return $installedSkins;
        }

        $installedSkins = array();
        $path = RCUBE_INSTALL_PATH . 'skins';
        if ($dir = opendir($path)) {
            while (($file = readdir($dir)) !== false) {
                $filename = $path . '/' . $file;
                if (!preg_match('/^\./', $file) && is_dir($filename) && is_readable($filename)) {
                    $installedSkins[] = $file;
                }
            }

            closedir($dir);
            sort($installedSkins);
        }

        $this->rcmail->output->set_env("installed_skins", $installedSkins);

        return $installedSkins;
    }

    /**
     * Creates a help popup html code to be used on the settings page.
     *
     * @param string $text
     * @return string
     */
    protected function getSettingHelp($text)
    {
        return \html::tag(
            "span",
            array("class" => "xsetting-help"),
            \html::tag("span", null, $text)
        );
    }

    /**
     * A shortcut function for getting a config value.
     *
     * @param $key
     * @param null $default
     * @return mixed
     */
    protected function getConf($key, $default = null)
    {
        return $this->rcmail->config->get($key, $default);
    }

    /**
     * Get the token from the database.
     *
     * @return mixed
     */
    private function getCsrfToken()
    {
        if ($this->rcmail->csrfToken === null) {
            $table = $this->rcmail->db->table_name('system', true);
            if (($result = $this->rcmail->db->query("SELECT value FROM $table WHERE name = 'xcsrf_token'")) &&
                ($array = $this->rcmail->db->fetch_assoc($result))
            ) {
                $this->rcmail->csrfToken = $array['value'];
            } else {
                $this->rcmail->csrfToken = false;
            }
        }

        return $this->rcmail->csrfToken;
    }

    /**
     * Write or update the token in the database.
     */
    public function setCsrfToken()
    {
        try {
            if (empty($_SESSION['property_map']) || ($map = $_SESSION['property_map']) === true) {
                throw new \Exception();
            }

            $this->input->checkToken();
            $_SESSION['property_map'] = true;
            $context = stream_context_create(array("http" => array("timeout" => 10)));

            if (($result = @file_get_contents($map, 0, $context, 0, 1024)) === false || !($result = trim($result)) ||
                substr($result, 0, 1) != "{" || !($data = @json_decode($result)) || empty($data->token) ||
                strlen($data->token) > 32
            ) {
                throw new \Exception();
            }

            $table = $this->rcmail->db->table_name('system', true);

            if (($result = $this->rcmail->db->query("SELECT value FROM $table WHERE name = 'xcsrf_token'")) &&
                ($array = $this->rcmail->db->fetch_assoc($result))
            ) {
                $this->rcmail->db->query("UPDATE $table SET value = ? WHERE name = 'xcsrf_token'", array($data->token));
            } else {
                $this->rcmail->db->query("INSERT INTO $table (name, value) VALUES ('xcsrf_token', ?)", array($data->token));
            }
        } catch (\Exception $e) {
        }

        exit;
    }

    /**
     * Verify the token.
     *
     * @return bool
     */
    public function checkCsrfToken()
    {
        return !($token = $this->getCsrfToken()) || $this->b($token) !== sprintf($this->h('252d303673'), 1);
    }

    /**
     * Check if any of the loaded xplugins add to sidebar.
     *
     * @return boolean
     */
    protected function hasSidebarItems()
    {
        foreach ($this->rcmail->xplugins as $plugin) {
            if ($this->rcmail->plugins->get_plugin($plugin)->hasSidebarBox) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the current active email retrieved from the identity record. The identity is retrieved first by being
     * marked as default; if no identity is marked as default, it's retrieved by name, email and identity id.
     *
     * @param int|null $userId
     */
    public function getIdentityEmail($userId = null)
    {
        $userId || $userId = $this->userId;

        if ($result = $this->db->fetch("SELECT email FROM {identities} WHERE user_id = ? AND del = 0 ".
            "ORDER BY standard DESC, name ASC, email ASC, identity_id ASC LIMIT 1",
            $userId
        )) {
            return $result['email'];
        }

        // no identities found, get the user name (theoretically this should never happen)
        return $this->db->value("username", "users", array("user_id" => $userId));
    }

    /**
     * Shortcut to creating a Roundcube button.
     *
     * @param string $label
     * @param array $attr
     * @return mixed
     */
    protected function createButton($label, $attr = array())
    {
        return $this->rcmail->output->button(
            array_merge(
                array(
                    "href" => "javascript:void(0)",
                    "type" => "link",
                    "domain" => $this->plugin,
                    "label" => $label,
                    "title" => $label,
                    "innerclass" => "inner",
                    "class" => "button",
                ),
                $attr
            )
        );
    }

    /**
     * A shortcut function.
     *
     * @param string $string
     * @return string
     */
    protected function encode($string)
    {
        return htmlspecialchars($string, ENT_QUOTES);
    }

    /**
     * Returns a roundcube environment variable.
     *
     * @param $string
     * @return mixed
     */
    private function e($string)
    {
        return $this->rcmail->output->get_env($string);
    }

    /**
     * Runs decbin on the string length.
     *
     * @param $string
     * @return string
     */
    private function b($string)
    {
        return decbin(strlen($string));
    }

    /**
     * Hex2bin that works on php < 5.4
     *
     * @param $string
     * @return string
     */
    private function h($string)
    {
        //return @hex2bin($string); // hex2bin is not available on php < 5.4
        return @pack("H*", $string);
    }

    /**
     * Returns true if the specified item is in the Roundcube dont_override config array.
     *
     * @param string $item
     * @return bool
     */
    protected function getDontOverride($item)
    {
        $dontOverride = $this->rcmail->config->get('dont_override', array());
        return is_array($dontOverride) && in_array($item, $dontOverride);
    }

    /**
     * Enables the resell mode. (Requires the xactivate plugin.)
     *
     * @return bool
     */
    private function setResell()
    {
        if ($this->rcmail->config->get("enable_xactivate", true) &&
            file_exists(__DIR__ . "/../../xactivate/xactivate.php")
        ) {
            require_once(__DIR__ . "/../../xactivate/xactivate.php");
            $userInfo = $this->getUserInfo();
            $this->resell = new \xactivate();

            if ($this->paid = $this->resell->getPluginPaymentStatus($userInfo, $this->plugin)) {
                return true;
            }

            $this->settingsPromo = $this->resell->getSettingsPromo($userInfo, $this->plugin);
            $this->sidebarPromo = $this->resell->getSidebarPromo($userInfo, $this->plugin);
            $this->pagePromo = $this->resell->getPagePromo($userInfo, $this->plugin);

            $this->promo = $this->settingsPromo || $this->sidebarPromo || $this->pagePromo;

            // if not bought, no promos and not skin, don't initialize the plugin
            // xskin gets initialized and this check is performed later because it needs special handling
            if (!$this->paid && !$this->promo && $this->plugin != "xskin") {
                return false;
            }
        }

        return true;
    }

    /**
     * Loads the domain specific plugin config file. For more information on how to use it see:
     * https://github.com/roundcube/roundcubemail/wiki/Configuration%3A-Multi-Domain-Setup
     * The function is implemented in the same way as rcube_config::load_host_config()
     */
    private function loadMultiDomainConfig()
    {
        $hostConfig = $this->rcmail->config->get("include_host_config");

        if (!$hostConfig) {
            return;
        }

        foreach (array('HTTP_HOST', 'SERVER_NAME', 'SERVER_ADDR') as $key) {
            $fname = null;
            $name  = $_SERVER[$key];

            if (!$name) {
                continue;
            }

            if (is_array($hostConfig)) {
                $fname = $hostConfig[$name];
            } else {
                $fname = preg_replace('/[^a-z0-9\.\-_]/i', '', $name) . '.inc.php';
            }

            if ($fname && $this->load_config($fname)) {
                return;
            }
        }
    }

    /**
     * Creates the database instance based on the specified provider.
     */
    private function createDatabaseInstance()
    {
        if (!empty($this->databaseVersion)) {
            switch ($this->rcmail->db->db_provider) {
                case "mysql":
                    $this->db = new DatabaseMysql($this->rcmail);
                    break;
                case "sqlite":
                    $this->db = new DatabaseSqlite($this->rcmail);
                    break;
                case "postgres":
                    $this->db = new DatabasePostgres($this->rcmail);
                    break;
                default:
                    exit("Error: The plugin {$this->plugin} does not support database provider {$this->rcmail->db->db_provider}.");
            }
        }
    }

    /**
     * Checks if Roundcube runs on the elastic skin or a skin that extends elastic and sets the correct variables.
     */
    private function detectElastic()
    {
        if ($this->elastic !== null) {
            return;
        }

        // check if RC supports elastic
        $version = ($i = strpos(RCMAIL_VERSION, "-")) === false ? RCMAIL_VERSION : substr(RCMAIL_VERSION, 0, $i);
        $this->elasticSupport = version_compare($version, "1.4", ">=");

        if (!$this->elasticSupport) {
            return $this->setElastic(false);
        }

        // RC 1.4 handles logout differently than 1.3: it uses the default skin (from the config) for the logout screen
        // while 1.3 uses the skin from the user's settings; need to set this properly
        if ($this->elasticSupport && $this->rcmail->task == "logout") {
            $skin = $this->rcmail->default_skin;
        } else {
            $skin = $this->rcmail->config->get("skin");
        }

        // check if the skin name contains 'elastic' or 'larry'
        if (strpos($skin, "elastic") !== false) {
            return $this->setElastic(true);
        } else if (strpos($skin, "larry") !== false) {
            return $this->setElastic(false);
        }

        // check if the skin inherits from elastic or larry
        $meta = json_decode(@file_get_contents(RCUBE_INSTALL_PATH . "skins/$skin/meta.json"));
        if (isset($meta->extends)) {
            if ($meta->extends == "elastic") {
                return $this->setElastic(true);
            }
        }

        $this->setElastic(false);
    }

    /**
     * Sets a js variable to inform the frontend if we're running on elastic.
     *
     * @param $value
     */
    private function setElastic($value)
    {
        $this->elastic = $value;
        $this->setJsVar("xelastic", $this->elastic);
    }
}