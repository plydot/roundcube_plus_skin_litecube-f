<?php
/**
 * Roundcube Plus Skin plugin.
 *
 * Copyright 2019, Tecorama LLC.
 *
 * @license Commercial. See the LICENSE file for details.
 */

require_once(__DIR__ . "/xskin_larry.php");

class xskin_elastic extends xskin_larry
{
    protected function elasticInitialize()
    {
        $this->lookAndFeelUrl = "?_task=settings&_action=preferences&_section=xskin";

        // add the quick skin selection to the app menu (this adds it to the elastic skin as well)
        $this->elasticAddSkinInterfaceMenuItem();
        $this->elasticAddLanguageInterfaceMenuItem();

        // include scripts (doing it here so the quick skin change works in elastic)
        $this->includeAsset("assets/elastic_scripts/xskin.min.js");

        // return if we're not running a Roundcube Plus skin
        if (!$this->isRcpSkin($this->rcmail->config->get("skin"))) {
            // include the custom css if specified in the xskin config
            if ($overwriteCss = $this->rcmail->config->get("overwrite_css")) {
                $this->includeAsset($overwriteCss);
            }

            return true;
        }

        // set plugins that load incorrect template files; we'll make them think they run under elastic
        $this->fixPlugins = $this->rcmail->config->get("fix_plugins", array());

        // add hooks
        $this->add_hook("startup", array($this, "elasticStartup"));
        $this->add_hook("config_get", array($this, "elasticGetConfig"));
        $this->add_hook("render_page", array($this, "elasticRenderPage"));

        if ($this->rcmail->task == "settings") {
            $this->add_hook('preferences_sections_list', array($this, 'elasticPreferencesSectionsList'));
            $this->add_hook("preferences_list", array($this, "elasticPreferencesList"));
            $this->add_hook("preferences_save", array($this, "elasticPreferencesSave"));
        }

        // include styles
        $skin = $this->rcmail->config->get("skin");
        $this->includeAsset("assets/elastic_styles/styles.css");
        $this->includeAsset("../../skins/$skin/assets/styles.css");
        $this->includeAsset("../../skins/$skin/assets/scripts.min.js");

        // include the custom css if specified in the xskin config
        if ($overwriteCss = $this->rcmail->config->get("overwrite_css")) {
            $this->includeAsset($overwriteCss);
        }

        // include the custom css if specified in skin json
        if ($customCss = $this->rcmail->config->get("custom_css")) {
            $this->includeAsset($customCss);
        }

        $this->includeSkinConfig();

        return true;
    }

    public function elasticStartup()
    {
        // set the preview background logo (loaded using js in [skin]/watermark.html)
        $this->rcmail->output->set_env(
            "xwatermark",
            $this->rcmail->config->get("xlogo_preview", "../../plugins/xskin/assets/images/watermark.png")
        );

        $this->rcmail->output->set_env("rcp_skin", $this->isRcpSkin($this->rcmail->config->get("skin")));

        $this->addClasses();
    }

    public function elasticGetConfig($arg)
    {
        // Substitute the skin name retrieved from the config file with "elastic" for the plugins that treat
        // elastic-based skins as "elastic."
        if ($arg['name'] != "skin" || !array_key_exists(str_replace("_elastic", "", $arg['result']), $this->getSkins())) {
            return $arg;
        }

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);

        // this is a call from the rc core, let's hope they fix this
        if (!empty($trace[3]['class']) && $trace[3]['class'] == "jqueryui") {
            $arg['result'] = "elastic";
        }

        // check if the calling file is in the list of plugins to fix or it's a unit test and set the skin to elastic
        if (!empty($trace[3]['file']) &&
            (in_array(basename(dirname($trace[3]['file'])), $this->fixPlugins) || basename($trace[3]['file']) == "TestCase.php")
        ) {
            $arg['result'] = "elastic";
        }

        return $arg;
    }

    public function elasticRenderPage($arg)
    {
        if ($this->rcmail->task == "login" || $this->rcmail->task == "logout") {
            $this->elasticModifyLoginHtml($arg);
        } else {
            $this->elasticModifyPageHtml($arg);
        }

        return $arg;
    }

    protected function elasticModifyPageHtml(&$arg)
    {
        // insert header logo if specified in the config, first take the value from the skin config,
        // and if it doesn't exist, try the xskin config, if it doesn't exist, use the default path
        $skin = $this->rcmail->config->get("skin");
        $logo = $this->rcmail->config->get(
            "xlogo_header",
            $this->rcmail->config->get(
                "header_branding_$skin",
                "skins/$skin/assets/images/logo_header.png"
            )
        );

        if ($logo) {
            if ($this->rcmail->config->get("xlogo-location") == "menu") {
                $this->html->insertAtBeginning(
                    'id="taskmenu"',
                    \html::a(
                        array("href" => "?task=mailbox", "id" => "xlogo-header-container"),
                        \html::img(array("id" => "xlogo-header", "src" => $logo))
                    ),
                    $arg['content']
                );
            } else {
                $this->html->insertAtBeginning(
                    'class="popover-header"',
                    \html::img(array("id" => "xlogo-header", "src" => $logo)),
                    $arg['content']
                );
            }
        }
    }

    /**
     * Modifies the login page html, adds branding, product name, etc.
     * Unit tested via renderPage()
     *
     * @param array $arg
     * @codeCoverageIgnore
     */
    protected function elasticModifyLoginHtml(&$arg)
    {
        // set the login branding image if specified, if not add an h1 that says "Login"
        $logo = $this->rcmail->config->get("xlogo_login");

        if ($logo) {
            $html = \html::img(array("id" => "logo-login", "src" => $logo));
        } else {
            $html = \html::tag("div", array("id" => "login-title"), $this->rcmail->gettext("login"));
        }

        $this->html->insertAtBeginning('id="login-form"', $html, $arg['content']);

        // roundcube plus logo
        if (!$this->rcmail->config->get("remove_vendor_branding")) {
            $this->html->insertBeforeBodyEnd(
                \html::a(
                    array(
                        "id" => "vendor-branding",
                        "href" => "https://roundcubeplus.com",
                        "target" => "_blank",
                        "title" => "More Roundcube skins and plugins at roundcubeplus.com",
                    ),
                    \html::span(array(), "+")
                ),
                $arg['content']
            );
        }
    }

    public function elasticPreferencesSectionsList($arg)
    {
        if ($this->elastic) {
            $arg['list']['xskin'] = array('id' => 'xskin', 'section' => $this->gettext("skin_look_and_feel"));
        }

        return $arg;
    }

    /**
     * Replaces the preference skin selection with a dialog-based selection that allows specifying separate desktop
     * table and phone skins.
     *
     * @param array $arg
     * @return array
     */
    public function elasticPreferencesList($arg)
    {
        if (!$this->elastic || $arg['section'] != 'xskin') {
            return $arg;
        }

        if ($this->getDontOverride("look_and_feel")) {
            return $arg;
        }

        $arg['blocks']['skin_look_and_feel']['name'] = $this->gettext("skin_look_and_feel");
        $skin = $this->rcmail->config->get("skin");

        if (!$this->getDontOverride("xskin_icons")) {
            $this->getSettingSelect(
                $arg,
                "skin_look_and_feel",
                "icons_$skin",
                array(
                    $this->gettext("icons_solid") => "solid",
                    $this->gettext("icons_traditional") => "traditional",
                    $this->gettext("icons_outlined") => "outlined",
                    $this->gettext("icons_material") => "material",
                    $this->gettext("icons_cartoon") => "cartoon",
                ),
                $this->getCurrentIcons(),
                false,
                array("onchange" => "xskin.applySetting(this, 'xicons', 'body')"),
                "icons"
            );
        }

        if (!$this->getDontOverride("xskin_list_icons")) {
            $this->getSettingCheckbox(
                $arg,
                "skin_look_and_feel",
                "list_icons_$skin",
                $this->getCurrentListIcons(),
                false,
                array("onchange" => "xskin.applySetting(this, 'xlist-icons', 'body')"),
                "list_icons"
            );
        }

        if (!$this->getDontOverride("xskin_button_icons")) {
            $this->getSettingCheckbox(
                $arg,
                "skin_look_and_feel",
                "button_icons_$skin",
                $this->getCurrentButtonIcons(),
                false,
                array("onchange" => "xskin.applySetting(this, 'xbutton-icons', 'body')"),
                "button_icons"
            );
        }

        if (!$this->getDontOverride("xskin_font_family")) {
            $fonts = array();
            $fontList = array(
                "Arial", "Courier", "Times", "Roboto", // already included
                "Quattrocento", // loaded from web: serif
                "Cairo", "Sarala", "Montserrat", // loaded from web: sans-serif
                "Merienda", // loaded from web: cursive
            );

            foreach ($fontList as $font) {
                $fonts[$font] = strtolower(str_replace(" ", "-", $font));
            }

            ksort($fonts);

            $this->getSettingSelect(
                $arg,
                "skin_look_and_feel",
                "font_family_$skin",
                $fonts,
                $this->getCurrentFontFamily(),
                false,
                array("onchange" => "xskin.applySetting(this, 'xfont-family', 'html')"),
                "font_family"
            );
        }

        if (!$this->getDontOverride("xskin_font_size")) {
            $this->getSettingSelect(
                $arg,
                "skin_look_and_feel",
                "font_size_$skin",
                array(
                    $this->gettext("font_size_xs") => "xs",
                    $this->gettext("font_size_s") => "s",
                    $this->gettext("font_size_n") => "n",
                    $this->gettext("font_size_l") => "l",
                    $this->gettext("font_size_xl") => "xl",
                ),
                $this->getCurrentFontSize(),
                false,
                array("onchange" => "xskin.applySetting(this, 'xfont-size', 'html')"),
                "font_size"
            );
        }

        if (!$this->getDontOverride("xskin_thick_font")) {
            $this->getSettingCheckbox(
                $arg,
                "skin_look_and_feel",
                "thick_font_$skin",
                $this->getCurrentThickFont(),
                false,
                array("onchange" => "xskin.applySetting(this, 'xthick-font', 'html')"),
                "thick_font"
            );
        }

        if (!$this->getDontOverride("xskin_color")) {
            $colorBoxes = "";
            foreach ($this->rcmail->config->get("xskin_colors") as $color) {
                $colorBoxes .= \html::a(
                    array(
                        "class" => "color-box",
                        "onclick" => "xskin.applySetting('#xcolor-input', 'xcolor', 'body', '$color')",
                        "style" => "background:#$color !important",
                    ),
                    " "
                );
            }

            $this->addSetting(
                $arg,
                "skin_look_and_feel",
                "color_$skin",
                $colorBoxes . "<input id='xcolor-input' type='hidden' name='color_$skin' value='" .
                $this->getCurrentColor() . "' />",
                null,
                "color"
            );
        }

        $arg['blocks']["skin_look_and_feel"]['options']["save_hint"] = array(
            "title" => "",
            "content" => "<span class='xsave-hint'>" .
                \rcube_utils::rep_specialchars_output($this->gettext("save_hint")) .
                "</span>" .
                "<script>xskin.updateIFrameClasses();</script>"
        );

        return $arg;
    }

    /**
     * Saves the skin selection preferences.
     *
     * @param array $arg
     * @return array
     */
    public function elasticPreferencesSave($arg)
    {
        if (!$this->elastic || $arg['section'] != "xskin") {
            return $arg;
        }

        $skin = $this->rcmail->config->get("skin");
        $this->saveSetting($arg, "icons_$skin");
        $this->saveSetting($arg, "list_icons_$skin");
        $this->saveSetting($arg, "button_icons_$skin");
        $this->saveSetting($arg, "font_family_$skin");
        $this->saveSetting($arg, "font_size_$skin");
        $this->saveSetting($arg, "thick_font_$skin");
        $this->saveSetting($arg, "color_$skin");
        $this->addClasses();

        return $arg;
    }

    public function elasticAddSkinInterfaceMenuItem()
    {
        if ($this->getDontOverride("skin") || $this->rcmail->config->get("disable_menu_skins")) {
            return;
        }

        if ($html = $this->getShortcutSkinsHtml()) {
            $this->addToInterfaceMenu(
                "skin-options",
                \html::div(
                    array("id" => "xskin-options", "class" => "section"),
                    \html::div(array("class" => "section-title"), $this->gettext("skin")) . $html
                )
            );
        }
    }

    private function addClasses()
    {
        // add html classes
        $classes = array(
            "xfont-family-" . $this->getCurrentFontFamily(),
            "xfont-size-" . $this->getCurrentFontSize(),
            "xthick-font-" . ($this->getCurrentThickFont() ? "yes" : "no"),
        );

        $this->addHtmlClass(implode(" ", $classes));

        // add body classes
        $classes = array(
            "{$this->rcmail->task}-page",
            "xskin",
            "skin-" . $this->rcmail->config->get("skin"),
            "xcolor-" . $this->getCurrentColor(),
            "xicons-" . $this->getCurrentIcons(),
            "xlist-icons-" . ($this->getCurrentListIcons() ? "yes" : "no"),
            "xbutton-icons-" . ($this->getCurrentButtonIcons() ? "yes" : "no"),
        );

        // add body classes from skin's meta.json
        $classes[] = $this->rcmail->config->get("xbody-classes", "");

        if ($this->rcmail->task == "logout") {
            $classes[] = "login-page";
        }

        // add body classes from xskin config
        if ($this->rcmail->config->get("hide_about_link")) {
            $classes[] = "xno-about-link";
        }

        $this->addBodyClass(implode(" ", $classes));
    }

    private function getShortcutSkinsHtml()
    {
        if (count($this->getInstalledSkins()) <= 1 ||
            $this->getDontOverride("skin") ||
            $this->rcmail->config->get("disable_menu_skins")
        ) {
            return false;
        }

        $select = new \html_select(array("onchange" => "xskin.changeSkin()", "class" => "form-control"));
        $added = 0;

        foreach ($this->getInstalledSkins() as $installedSkin) {
            if (array_key_exists($installedSkin, $this->skins)) {
                $select->add($this->skins[$installedSkin], $installedSkin);
                $added++;
            } else if ($installedSkin == "elastic" || $installedSkin == "larry") {
                $select->add(ucfirst($installedSkin), $installedSkin);
                $added++;
            }
        }

        if ($added > 1) {
            if ($this->isRcpSkin($this->rcmail->config->get("skin"))) {
                $lookAndFeelHtml = \html::div(
                    array("id" => "look-and-feel-shortcut"),
                    "<a href='{$this->lookAndFeelUrl}' class='btn btn-sm btn-success'>" . $this->gettext("skin_look_and_feel") . "</a>"
                );
            } else {
                $lookAndFeelHtml = "";
            }


            return \html::div(
                array("id" => "xshortcut-skins", "class" => "shortcut-item"),
                $select->show($this->rcmail->config->get("skin"))
            ) . $lookAndFeelHtml;
        }

        return false;
    }

    /**
     * Adds the skin config files from <skin>/config.inc.php to the main config, if the file exists.
     * Elastic only.
     */
    private function includeSkinConfig()
    {
        // include the default setting values from the skin's meta.json in the config
        // values from meta.json get automatically included in the config, but at the same time they're included
        // in dontoverride, which is not good because we want admins to be able to include/exclude it from dontoverride
        // so we set the default values in meta as 'xskin_default_*' and here we translate them to 'xskin_*'
        // this way the values 'xskin_*' can be used normally in dontoverride
        foreach ($this->rcmail->config->all() as $key => $val) {
            if (strpos($key, "xskin_default") === 0) {
                $this->rcmail->config->set("xskin" . substr($key, 13), $val);
            }
        }

        $file = RCUBE_INSTALL_PATH . "skins/" . $this->rcmail->config->get("skin") . "/config.inc.php";

        if (!file_exists($file)) {
            return;
        }

        $config = array();
        @include($file);

        if (is_array($config)) {
            foreach ($config as $key => $val) {
                $this->rcmail->config->set($key, $val);
            }
        }
    }

    private function getCurrentColor()
    {
        if ($this->getDontOverride("xskin_color")) {
            return $this->rcmail->config->get("xskin_color");
        }

        $color = $this->rcmail->config->get(
            "xskin_color_" . $this->rcmail->config->get("skin"),
            $this->rcmail->config->get("xskin_color")
        );

        // have to do strlen because in_array thinks that "0" == "000000"
        $colors = $this->rcmail->config->get("xskin_colors");

        if (strlen($color) != 6 || !is_array($colors) || !in_array($color, $colors)) {
            $color = $this->rcmail->config->get("xskin_color");
        }

        return $color;
    }

    private function getCurrentFontFamily()
    {
        if ($this->getDontOverride("xskin_font_family")) {
            return $this->rcmail->config->get("xskin_font_family");
        }

        return $this->rcmail->config->get(
            "xskin_font_family_" . $this->rcmail->config->get("skin"),
            $this->rcmail->config->get("xskin_font_family")
        );
    }

    private function getCurrentFontSize()
    {
        if ($this->getDontOverride("xskin_font_size")) {
            return $this->rcmail->config->get("xskin_font_size");
        }

        return $this->rcmail->config->get(
            "xskin_font_size_" . $this->rcmail->config->get("skin"),
            $this->rcmail->config->get("xskin_font_size")
        );
    }

    private function getCurrentThickFont()
    {
        if ($this->getDontOverride("xskin_thick_font")) {
            return $this->rcmail->config->get("xskin_thick_font");
        }

        return $this->rcmail->config->get(
            "xskin_thick_font_" . $this->rcmail->config->get("skin"),
            $this->rcmail->config->get("xskin_thick_font")
        );
    }

    private function getCurrentIcons()
    {
        if ($this->getDontOverride("xskin_icons")) {
            return $this->rcmail->config->get("xskin_icons");
        }

        return $this->rcmail->config->get(
            "xskin_icons_" . $this->rcmail->config->get("skin"),
            $this->rcmail->config->get("xskin_icons")
        );
    }

    private function getCurrentListIcons()
    {
        if ($this->getDontOverride("xskin_list_icons")) {
            return $this->rcmail->config->get("xskin_list_icons");
        }

        return $this->rcmail->config->get(
            "xskin_list_icons_" . $this->rcmail->config->get("skin"),
            $this->rcmail->config->get("xskin_list_icons")
        );
    }

    private function getCurrentButtonIcons()
    {
        if ($this->getDontOverride("xskin_button_icons")) {
            return $this->rcmail->config->get("xskin_button_icons");
        }

        return $this->rcmail->config->get(
            "xskin_button_icons_" . $this->rcmail->config->get("skin"),
            $this->rcmail->config->get("xskin_button_icons")
        );
    }

    private function elasticAddLanguageInterfaceMenuItem()
    {
        if ($this->getDontOverride("language") || $this->rcmail->config->get("disable_menu_languages")) {
            return;
        }

        $languages = $this->rcmail->list_languages();
        asort($languages);

        $select = new \html_select(array("onchange" => "xframework.quickLanguageChange()", "class"=>"form-control"));
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