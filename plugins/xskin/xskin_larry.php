<?php
/**
 * Roundcube Plus Skin plugin.
 *
 * Copyright 2019, Tecorama LLC.
 *
 * @license Commercial. See the LICENSE file for details.
 */

require_once(__DIR__ . "/../xframework/common/Plugin.php");

class xskin_larry extends XFramework\Plugin
{
    protected $enabled = true;
    protected $settings = false;

    public $allowed_prefs = array(
        "xcolor_alpha",
        "xcolor_droid",
        "xcolor_icloud",
        "xcolor_outlook",
        "xcolor_litecube",
        "xcolor_litecube-f",
        "xcolor_w21",
    );

    /**
     * Initializes the plugin.
     */
    public function larryInitialize()
    {
        $this->color = false;
        $this->appUrl = "?_task=settings&_action=preferences&_section=general";

        $this->lightSkins = array("droid", "icloud", "outlook", "litecube", "litecube-f", "w21");
        $this->squareSkins = array("outlook");

        $this->setSkin();

        $this->fixPlugins = $this->rcmail->config->get("fix_plugins", array());

        $this->disablePluginsOnMobile = array_merge(
            array("preview_pane", "google_ads", "threecol"),
            $this->rcmail->config->get("disable_plugins_on_mobile", array())
        );

        $this->enabled = $this->isRcpSkin($this->rcmail->output->get_env("xskin"));
        $this->larryAddSkinInterfaceMenuItem();

        if ($this->enabled) {
            $this->add_hook("startup", array($this, "larryStartup"));
            $this->add_hook("config_get", array($this, "larryGetConfig"));
            $this->add_hook("render_page", array($this, "larryRenderPage"));
        } else {
            // include xskin so quick skin change works on larry
            $this->includeAsset("assets/larry_scripts/xskin.min.js");
        }

        if ($overwriteCss = $this->rcmail->config->get("overwrite_css")) {
            $this->includeAsset($overwriteCss);
        }

        if ($this->rcmail->task == "settings") {
            $this->add_hook("preferences_save", array($this, "larryPreferencesSave"));
        }

        return true;
    }

    /**
     * Startup hook. Sets up the plugin functionality.
     *
     * @global array $CONFIG
     */
    public function larryStartup()
    {
        $skin = $this->rcmail->output->get_env("xskin");
        $skinType = $this->rcmail->output->get_env("xskin_type");

        // load skin settings
        $this->settings = @include(INSTALL_PATH . "skins/$skin/settings.php");

        if (empty($this->settings)) {
            $this->enabled = false;
            return;
        }

        // set default skin color: try config, then skin settings
        $defaultColor = $this->rcmail->config->get("default_color_" . $skin);
        if (!$defaultColor || !in_array($defaultColor, $this->settings['colors'])) {
            $defaultColor = $this->settings['default_color'];
        }

        // set skin color: if color menu disabled, use default color
        if ($this->rcmail->config->get("disable_menu_colors")) {
            $this->color = $defaultColor;
        } else {
            $this->color = $this->rcmail->config->get("xcolor_" . $skin, $defaultColor);
        }

        if (!in_array($this->color, $this->settings['colors'])) {
            $this->color = $defaultColor;
        }

        $this->rcmail->output->set_env("xcolor", $this->color);

        // include assets
        $this->includeAsset("assets/larry_scripts/xskin.min.js");

        if ($skinType == "mobile") {
            $this->includeAsset("assets/larry_scripts/hammer.min.js");
            $this->includeAsset("assets/larry_scripts/jquery.hammer.js");
            $this->includeAsset("assets/larry_scripts/xmobile.min.js");
            $this->includeAsset("assets/larry_styles/xmobile.css");
            $this->includeAsset("../../skins/$skin/assets/mobile.css");

            if ($overwrite = $this->rcmail->config->get("overwrite_mobile_css_" . $skin)) {
                $this->includeAsset($overwrite);
            }
        } else {
            $this->includeAsset("assets/larry_scripts/xdesktop.min.js");
            $this->includeAsset("assets/larry_styles/xdesktop.css");
            $this->includeAsset("../../skins/$skin/assets/desktop.css");

            if ($overwrite = $this->rcmail->config->get("overwrite_desktop_css_" . $skin)) {
                $this->includeAsset($overwrite);
            }
        }

        // add labels to env
        $this->rcmail->output->add_label("login", "folders", "search", "attachment", "section", "options");

        // disable composing in html on mobile devices unless config option set to allow
        if ($this->rcmail->output->get_env("xmobile") && !$this->rcmail->config->get("allow_mobile_html_composing")) {
            global $CONFIG;
            $CONFIG['htmleditor'] = false;
        }

        // set the skin logo config value to the one specified in xskin config, or to the default skin logo image
        // if not specified in xskin config
        if ($this->rcmail->action == "print") {
            $logo = $this->rcmail->config->get("print_branding_$skin", "skins/$skin/assets/images/logo_print.png");
        } else {
            $logo = $this->rcmail->config->get("header_branding_$skin", "skins/$skin/assets/images/logo_header.png");
        }

        $configLogo = $this->rcmail->config->get("skin_logo");

        if (is_array($configLogo)) {
            $configLogo["*"] = $logo;
        } else {
            $configLogo = $logo;
        }

        $this->rcmail->config->set("skin_logo", $configLogo);

        // add color boxes to the interface menu
        $this->addColorInterfaceMenuItem();

        // add disable/enable mobile skin interface menu
        $this->addDisableMobileInterfaceMenuItem();

        // set the preview background logo (loaded using js in [skin]/watermark.html)
        $this->rcmail->output->set_env(
            "xwatermark",
            $this->rcmail->config->get("preview_branding", "../../plugins/xskin/assets/images/watermark.png")
        );

        // add classes to body
        $bodyClasses = array(
            "xskin skin-" . $this->rcmail->output->get_env("xskin"),
            "color-{$this->color}",
            "{$this->rcmail->task}-page",
            "x" . $this->rcmail->output->get_env("xskin_type"),
        );

        if (in_array($this->rcmail->output->get_env("xskin"), $this->lightSkins)) {
            $bodyClasses[] = "xskin-light";
        }

        if (in_array($this->rcmail->output->get_env("xskin"), $this->squareSkins)) {
            $bodyClasses[] = "xskin-square";
        }

        if ($this->rcmail->task == "logout") {
            $bodyClasses[] = "login-page";
        }

        if ($this->settings['font_icons_toolbars']) {
            $bodyClasses[] = "font-icons-toolbars";
        }

        if (isset($this->settings['icons'])) {
            $bodyClasses[] = "xicons-" . $this->settings['icons'];
        }

        if ($this->rcmail->config->get("hide_about_link")) {
            $bodyClasses[] = "xno-about-link";
        }

        $this->addBodyClass(implode(" ", $bodyClasses));
    }

    /**
     * Hook retrieving config options (including user settings).
     */
    function larryGetConfig($arg)
    {
        if (!$this->enabled) {
            return $arg;
        }

        if ($this->rcmail->output->get_env("xskin_type") == "mobile") {
            // disable unwanted plugins on mobile devices
            foreach ($this->disablePluginsOnMobile as $val) {
                if (strpos($arg['name'], $val) !== false) {
                    $arg['result'] = false;
                    return $arg;
                }
            }

            // set the layout to list on mobile devices so it can be displayed properly
            // IMPORTANT: we have to unset $_GET['_layout'] because on RC 1.4 setting $arg here results in adding
            // the new layout value to GET, which is then picked up and saved into the database by
            // program/steps/mail/list.inc. So the 'list' value we set here for mobile is then applied to desktop
            // as well. Unsetting GET fixes the issue.
            if ($arg['name'] == "layout") {
                $arg['result'] = "list";
                unset($_GET['_layout']);
                return $arg;
            }
        }

        // Substitute the skin name retrieved from the config file with "larry" for the plugins that treat larry-based
        // skins as "classic."
        if ($arg['name'] != "skin" || !$this->isRcpSkin($arg['result'])) {
            return $arg;
        }

        // check php version to use the right parameters
        if (version_compare(phpversion(), "5.3.6", "<")) {
            $options = false;
        } else {
            $options = DEBUG_BACKTRACE_IGNORE_ARGS;
        }

        // when passing 4 as the second parameter in php < 5.4, debug_backtrace will return null
        if (version_compare(phpversion(), "5.4.0", "<")) {
            $trace = debug_backtrace($options);
        } else {
            $trace = debug_backtrace($options, 4);
        }

        // check if the calling file is in the list of plugins to fix or it's a unit test and set the skin to larry
        if (!empty($trace[3]['file']) &&
            (in_array(basename(dirname($trace[3]['file'])), $this->fixPlugins) || basename($trace[3]['file']) == "TestCase.php")
        ) {
            $arg['result'] = "larry";
        }

        return $arg;
    }

    public function larryRenderPage($arg)
    {
        if ($this->rcmail->task == "login" || $this->rcmail->task == "logout") {
            $this->larryModifyLoginHtml($arg);
        } else {
            $this->larryModifyPageHtml($arg);
        }

        return $arg;
    }

    /**
     * Modifies the login page html, adds branding, product name, etc.
     * Unit tested via renderPage()
     *
     * @param array $arg
     * @codeCoverageIgnore
     */
    protected function larryModifyLoginHtml(&$arg)
    {
        if (!$this->enabled) {
            return $arg;
        }

        // check if it's an error page
        if (strpos($arg['content'], "uibox centerbox errorbox")) {
            return $arg;
        }

        $skin = $this->rcmail->output->get_env("xskin");

        // set the custom login product name if specified, if not used the main product name
        $productName= $this->rcmail->config->get(
            "login_product_name_" . $skin,
            $this->rcmail->config->get("product_name")
        );

        $this->replace(
            '<form name',
            '<div id="company-name">' . $productName . '</div><form name',
            $arg['content'],
            4773
        );

        // set the login branding image if specified, if not add an h1 that says "Login"
        $logo = $this->rcmail->config->get("login_branding_" . $skin);

        if ($logo) {
            $html = \html::img(array("id" => "login-branding", "src" => $logo));
        } else {
            $html = \html::tag("h1", array(), \html::tag("span", array(), $this->rcmail->gettext("login")));
        }

        $this->replace(
            "<form",
            $html . "<form",
            $arg['content'],
            4774
        );

        // roundcube plus logo
        if (!$this->rcmail->config->get("remove_vendor_branding")) {
            $this->replace(
                "</body>",
                \html::a(
                    array(
                        "id" => "vendor-branding",
                        "href" => "https://roundcubeplus.com",
                        "target" => "_blank",
                        "title" => "More Roundcube skins and plugins at roundcubeplus.com",
                    ),
                    \html::span(array(), "+")
                ).
                "</body>",
                $arg['content'],
                4775
            );
        }
    }

    /**
     * Modifies the html of the non-login Roundcube pages.
     * Unit tested via renderPage()
     *
     * @param array $arg
     * @codeCoverageIgnore
     */
    protected function larryModifyPageHtml(&$arg)
    {
        // check if it's an error page
        if (strpos($arg['content'], "uibox centerbox errorbox")) {
            return $arg;
        }

        $skinType = $this->rcmail->output->get_env("xskin_type");
        $menuItems = array();

        // create the 'use mobile skin' button (added only if user switched to desktop skin on mobile)
        if ($skinType == "desktop" && isset($_COOKIE['rcs_disable_mobile_skin'])) {
            $menuItems[] =
                \html::div(
                    array("id" => "switch-mobile-skin", "class" => "section"),
                    "<input type='button' class='button mainaction' onclick='xskin.enableMobileSkin()' value='" .
                    \rcube_utils::rep_specialchars_output($this->rcmail->gettext("xskin.enable_mobile_skin")) . "' />"
                );
        } else if ($skinType != "desktop") {
            $menuItems[] =
                \html::div(
                    array("id" => "switch-mobile-skin", "class" => "section"),
                    "<input type='button' class='button mainaction' onclick='xskin.disableMobileSkin()' value='" .
                    \rcube_utils::rep_specialchars_output($this->rcmail->gettext("xskin.disable_mobile_skin")) . "' />"
                );
        }

        // if using a desktop skin on mobile devices after clicked "use desktop skin" show a link to revert to
        // mobile skin in the top bar
        if (isset($_COOKIE['rcs_disable_mobile_skin'])) {
            $this->replace(
                '<div class="topleft">',
                '<div class="topleft">'.
                \html::a(
                    array(
                        "class" => "enable-mobile-skin",
                        "href" => "javascript:void(0)",
                        "onclick" => "xskin.enableMobileSkin()",
                    ),
                    \rcube_utils::rep_specialchars_output($this->rcmail->gettext("xskin.enable_mobile_skin"))
                ),
                $arg['content']
            );
        }

        // add the toolbar-bg element that is used by alpha
        $this->replace(
            '<div id="mainscreencontent',
            '<div id="toolbar-bg"></div><div id="mainscreencontent',
            $arg['content']
        );
    }

    /**
     * Sets the current skin and color and fills in the correct properties for the desktop, tablet and phone skin.
     */
    public function setSkin()
    {
        $skin = $this->rcmail->config->get("skin", "larry");

        // if for some reason the skin is set to an elastic-based skin on RC that doesn't support elastic (< 1.4)
        // set the skin to larry
        if (!$this->getElasticSupport() && $this->isElasticSkin($skin)) {
            $skin = "larry";
        }

        // check if already set
        if ($this->rcmail->output->get_env("xskin")) {
            return;
        }

        // don't override skin will only be possible if the xskin config file exists
        if ($this->getDontOverride("skin") && file_exists(__DIR__ . "/config.inc.php")) {
            return;
        }

        if ($this->rcmail->output->get_env("xphone")) {
            $skinType = "mobile";
        } else if ($this->rcmail->output->get_env("xtablet")) {
            $skinType = "mobile";
        } else {
            $skinType = "desktop";
        }

        // litecube-f doesn't support mobile, set the device to desktop to avoid errors
        // also set device to desktop if mobile interface is disabled in config
        if ($skin == "litecube-f" || $this->rcmail->config->get("disable_mobile_interface")) {
            $this->setDevice(true);
            $skinType = "desktop";
        }

        // change the skin in the environment
        if (method_exists($GLOBALS['OUTPUT'], "set_skin")) {
            $GLOBALS['OUTPUT']->set_skin($skin);
        }

        // if running a mobile skin, remove the apps menu before it gets added using js
        if ($skinType != "desktop") {
            $this->setJsVar("appsMenu", "");
        }

        // sent environment variables
        $this->rcmail->output->set_env("xskin", $skin);
        $this->rcmail->output->set_env("xskin_type", $skinType);
        $this->rcmail->output->set_env("rcp_skin", $this->isRcpSkin($skin));
    }

    public function addDisableMobileInterfaceMenuItem()
    {
        // create the 'use mobile skin' button (added only if user switched to desktop skin on mobile)
        $skinType = $this->rcmail->output->get_env("xskin_type");

        if ($skinType == "desktop" && isset($_COOKIE['rcs_disable_mobile_skin'])) {
            $this->addToInterfaceMenu(
                "enable-mobile-skin",
                \html::div(
                    array("id" => "enable-mobile-skin", "class" => "section"),
                    "<input type='button' class='button mainaction' onclick='xskin.enableMobileSkin()' value='" .
                    \rcube_utils::rep_specialchars_output($this->rcmail->gettext("xskin.enable_mobile_skin")) . "' />"

                )
            );
        } else if ($skinType != "desktop") {
            $this->addToInterfaceMenu(
                "disable-mobile-skin",
                \html::div(
                    array("id" => "disable-mobile-skin", "class" => "section"),
                    "<input type='button' class='button mainaction' onclick='xskin.disableMobileSkin()' value='" .
                    \rcube_utils::rep_specialchars_output($this->rcmail->gettext("xskin.disable_mobile_skin")) . "' />"
                )
            );
        }
    }

    public function larryAddSkinInterfaceMenuItem()
    {
        // add the skin selection item to interface menu
        if ($this->paid && !$this->getDontOverride("skin") && !$this->rcmail->config->get("disable_menu_skins")) {
            if (count($this->getInstalledSkins()) > 1) {
                $select = new \html_select(array("onchange" => "xskin.quickSkinChange()"));
                $added = 0;

                foreach ($this->getInstalledSkins() as $installedSkin) {
                    if ($this->isRcpSkin($installedSkin)) {
                        $select->add($this->skins[$installedSkin], $installedSkin);
                        $added++;
                    } else if ($installedSkin == "larry") {
                        $select->add("Larry", $installedSkin);
                        $added++;
                    } else if ($installedSkin == "elastic") {
                        $select->add("Elastic", $installedSkin);
                        $added++;
                    }
                }

                if ($added > 1) {
                    $this->addToInterfaceMenu(
                        "quick-skin-change",
                        \html::div(
                            array("id" => "quick-skin-change", "class" => "section"),
                            \html::div(
                                array("class" => "section-title"),
                                \rcube_utils::rep_specialchars_output($this->gettext("skin"))
                            ) .
                            $select->show($this->rcmail->output->get_env("xskin"))
                        )
                    );
                }
            }
        }

        if (!$this->getDontOverride("language") && !$this->rcmail->config->get("disable_menu_languages")) {
            $languages = $this->rcmail->list_languages();
            asort($languages);

            $select = new \html_select(array("onchange" => "xframework.quickLanguageChange()"));
            $select->add(array_values($languages), array_keys($languages));

            $this->addToInterfaceMenu(
                "quick-language-change",
                \html::div(
                    array("id" => "quick-language-change", "class" => "section"),
                    \html::div(array("class" => "section-title"), $this->gettext("language")) .
                    $select->show($this->rcmail->user->language)
                )
            );
        }
    }

    public function addColorInterfaceMenuItem()
    {
        // create the color selection boxes
        if (!empty($this->settings['colors']) && !$this->rcmail->config->get("disable_menu_colors")) {
            $colorPopup = "";
            foreach ($this->settings['colors'] as $color) {
                $colorPopup .= \html::a(
                    array(
                        "class" => "color-box",
                        "onclick" => "xskin.changeColor('$color')",
                        "style" => "background:#$color !important",
                    ),
                    " "
                );
            }

            if ($colorPopup) {
                $this->addToInterfaceMenu(
                    "skin-color-select",
                    \html::div(
                        array("id" => "skin-color-select", "class" => "section"),
                        $colorPopup
                    )
                );
            }
        }
    }

    /**
     * Hook into preferences save to prevent switching to an elastic-based skin when running RC that doesn't support it.
     * This is in case the admin copied the RC+ elastic-based skins to the skins directory on RC 1.3
     *
     * @param $arg
     * @return mixed
     */
    public function larryPreferencesSave($arg)
    {
        if ($arg['section'] == "general" &&
            !$this->getElasticSupport() &&
            $this->isElasticSkin($arg['prefs']['skin'])
        ) {
            // have to set this, otherwise RC will try to load skins/larry/common.css which doesn't exist and will
            // throw an error
            $arg['prefs']['skin'] = "larry";

            // show error message
            $this->rcmail->output->show_message(
                $this->rcmail->gettext("xskin.elastic_skin_not_supported"),
                "error"
            );

            // don't save preferences
            $arg['abort'] = true;
        }

        return $arg;
    }
}