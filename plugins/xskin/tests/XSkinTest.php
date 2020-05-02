<?php
/**
 * Roundcube Plus Tests
 *
 * Copyright 2019, Tecorama LLC.
 *
 * @license Commercial. See the file LICENSE for details.
 */

require_once(__DIR__ . "/../../xframework/common/Test.php");

class XSkinTest extends XFramework\Test
{
    public function testAssets()
    {
        // test translations
        $this->assertEquals($this->class->gettext("xskin.interface_options"), "Interface Options");

        // test assets
        $this->assertFileExists(__DIR__ . "/../assets/styles/xsettings_skin_selector.css");
        $this->assertFileExists(__DIR__ . "/../assets/scripts/xsettings_skin_selector.min.js");
        $this->assertFileExists(__DIR__ . "/../assets/scripts/xskin.min.js");
        $this->assertFileExists(__DIR__ . "/../assets/scripts/hammer.min.js");
        $this->assertFileExists(__DIR__ . "/../assets/scripts/jquery.hammer.js");
        $this->assertFileExists(__DIR__ . "/../assets/scripts/xmobile.min.js");
        $this->assertFileExists(__DIR__ . "/../assets/scripts/xdesktop.min.js");
        $this->assertFileExists(__DIR__ . "/../assets/styles/xdesktop.css");
        $this->assertFileExists(__DIR__ . "/../assets/scripts/xdesktop.min.js");
        $this->assertFileExists(__DIR__ . "/../assets/scripts/xdesktop.min.js");

        $skins = $this->class->getSkins();
        unset($skins['litecube-f']);

        foreach ($skins as $skin => $name) {
            $this->assertFileExists(__DIR__ . "/../../../skins/$skin/meta.json");
            $this->assertFileExists(__DIR__ . "/../../../skins/$skin/settings.php");
            $this->assertFileExists(__DIR__ . "/../../../skins/$skin/thumbnail.png");
            $this->assertFileExists(__DIR__ . "/../../../skins/$skin/watermark.html");
            $this->assertFileExists(__DIR__ . "/../../../skins/$skin/assets/desktop.css");
            $this->assertFileExists(__DIR__ . "/../../../skins/$skin/assets/mobile.css");
            $this->assertFileExists(__DIR__ . "/../../../skins/$skin/includes/header.html");
            $this->assertFileExists(__DIR__ . "/../../../skins/$skin/includes/links.html");
        }

    }

    /**
     * @dataProvider initializeData
     */
    public function testInitialize($data, $expected)
    {
        //$this->class->rcmail->output->set_env("xskin", $data['skin']);
        $this->class->rcmail->task = $data['task'];
        $this->setProperty($this->class, "paid", $data['paid']);
        $this->setProperty($this->class, "settingsPromo", $data['settingsPromo']);
        $this->class->rcmail->output->set_env("xskin", $data['skin']);
        $this->class->rcmail->output->set_env("xassets", array());

        $this->assertEquals($this->class->initialize(), $expected['result']);
        $this->assertEquals($this->hasAsset("xskin.min.js"), $expected['skinAssetsIncluded']);
        $this->assertEquals($this->hasAsset("xsettings_skin_selector.min.js"), $expected['settingsAssetsIncluded']);

    }

    public function initializeData()
    {
        $data = [
            "paid" => true,
            "settingsPromo" => true,
            "skin" => "outlook",
            "task" => "",
        ];

        $expected = [
            "result" => true,
            "skinAssetsIncluded" => false,
            "settingsAssetsIncluded" => false,
        ];

        return [
            [
                $data,
                $expected
            ],
            [
                array_merge($data, ["paid" => false, "settingsPromo" => false]),
                array_merge($expected, ["result" => false]),
            ],
            [
                array_merge($data, ["skin" => "larry"]),
                array_merge($expected, ["skinAssetsIncluded" => true]),
            ],
            [
                array_merge($data, ["task" => "settings"]),
                array_merge($expected, ["settingsAssetsIncluded" => true]),
            ],
        ];

    }

    /**
     * @dataProvider startupData
     */
    public function testStartup($data, $expected)
    {
        $this->class->rcmail->output->set_env("xassets", []);
        $this->class->rcmail->output->set_env("xskin", $data['skin']);
        $this->class->rcmail->config->set("xcolor_" . $data['skin'], $data['color']);
        $this->class->rcmail->output->set_env("xskin_type", $data['skinType']);
        $this->class->rcmail->action = $data['action'];

        $this->setProperty($this->class, "enabled", true);

        $this->class->larryStartup();

        $this->assertEquals($this->getProperty($this->class, "enabled"), $expected['enabled']);
        $this->assertEquals($this->class->rcmail->output->get_env("xcolor") == $data['color'], $expected['color']);

        if ($expected['enabled']) {
            $this->assertTrue($this->hasAsset($expected['asset']));
            $this->assertContains($expected['branding'], $this->class->rcmail->config->get("skin_logo"));
        }
    }

    public function startupData()
    {
        $data = [
            "skin" => "outlook",
            "color" => "df5aad",
            "skinType" => "desktop",
            "action" => "",
        ];

        $expected = [
            "enabled" => true,
            "color" => true,
            "asset" => "xdesktop.min.js",
            "branding" => "header",
        ];

        return [
            [$data, $expected],
            [array_merge($data, ["skin" => "larry"]), array_merge($expected, ["enabled" => false])],
            [array_merge($data, ["color" => "1234"]), array_merge($expected, ["color" => false])],
            [array_merge($data, ["skinType" => "mobile"]), array_merge($expected, ["asset" => "xmobile.min.js"])],
            [array_merge($data, ["action" => "print"]), array_merge($expected, ["branding" => "print"])],
        ];
    }

    public function testSetSkin()
    {
        $this->class->rcmail->output->set_env("xskin", false);
        $this->class->rcmail->output->set_env("xskin_type", false);

        $this->class->rcmail->config->set_user_prefs(array("skin" => "outlook"));
        $this->class->setSkin();

        $this->assertEquals($this->class->rcmail->output->get_env("xskin"), "outlook");
        $this->assertEquals($this->class->rcmail->output->get_env("xphone_skin"), "outlook");
        $this->assertEquals($this->class->rcmail->output->get_env("xtablet_skin"), "outlook");
        $this->assertEquals($this->class->rcmail->output->get_env("xdesktop_skin"), "outlook");
        $this->assertEquals($this->class->rcmail->output->get_env("xskin_type"), "desktop");
    }

    public function testAddSkinInterfaceMenuItem()
    {
        $this->class->larryAddSkinInterfaceMenuItem();

        $this->assertTrue(strpos($this->class->rcmail->xinterfaceMenuItems['quick-skin-change'], "Alpha") !== false);
        $this->assertTrue(strpos($this->class->rcmail->xinterfaceMenuItems['quick-language-change'], "Albanian") !== false);
    }

    public function testAddColorInterfaceMenuItem()
    {
        $this->class->addColorInterfaceMenuItem();
        $this->assertTrue(strpos($this->class->rcmail->xinterfaceMenuItems['skin-color-select'], "skin-color-select") !== false);
    }

    public function testGetConfig()
    {
        $this->setProperty($this->class, "enabled", false);
        $this->assertEquals([], $this->class->larryGetConfig([]));

        $this->setProperty($this->class, "enabled", true);

        $arg = $this->class->larryGetConfig(array("name" => "skin", "result" => "outlook"));
        $this->assertEquals($arg['result'], "larry");
    }

    public function testRenderPage()
    {
        $this->setProperty($this->class, "enabled", false);
        $this->assertEquals([], $this->class->larryRenderPage([]));

        $this->setProperty($this->class, "enabled", true);

        // check rendering of the mail page
        $arg = $this->class->renderPage(array("content" => '<div id="mainscreencontent'));

        $this->assertContains("toolbar-bg", $arg['content']);

        // check rendering of the login page
        $this->class->rcmail->task = "login";
        $arg = $this->class->renderPage(array("content" => '<div id="login-form"><form name></body>'));

        $this->assertContains("company-name", $arg['content']);
        $this->assertContains("vendor-branding", $arg['content']);

        // check 'login_branding_*' config option
        $this->rcmail->config->set("login_branding_outlook", "http://skin-login-branding-image");
        $arg = $this->class->renderPage(array("content" => '<div id="login-form"><form name></body>'));

        $this->assertContains("skin-login-branding-image", $arg['content']);

        // check 'remove_vendor_branding' config option
        $this->rcmail->config->set("remove_vendor_branding", true);
        $arg = $this->class->renderPage(array("content" => '<div id="login-form"><form name></body>'));

        $this->assertNotContains("vendor-branding", $arg['content']);
    }

//    public function testDisabledPreferencesList()
//    {
//        $this->assertEquals([], $this->class->disabledPreferencesList([]));
//
//        $arg = $this->class->disabledPreferencesList(
//            array("section" => "general", "blocks" => array("skin" => array("options" => array("outlook" => "outlook"))))
//        );
//
//        $this->assertTrue(!isset($arg['blocks']['skin']['options']['outlook']));
//    }

    /**
     * @dataProvider preferencesListData
     */
//    public function testPreferencesList($data, $expected)
//    {
//        $arg = [
//            "section" => $data['section'],
//            "blocks" => [
//                "skin" => [
//                    "options" => [
//                        "outlook" => "outlook",
//                    ],
//                ],
//            ],
//        ];
//
//        $this->class->rcmail->config->set('dont_override', $data['dontOverride']);
//
//        $arg = $this->class->preferencesList($arg);
//
//        $this->assertEquals(isset($arg['blocks']['browser']['options']['currentbrowser']['title']), $expected);
//        $this->assertEquals($this->has("skin", $arg['blocks']['skin']['name']), $expected);
//        $this->assertEquals($this->has("Alpha", $arg['blocks']['skin']['options']['desktop_skin']['content']), $expected);
//        $this->assertEquals($this->has("Alpha", $arg['blocks']['skin']['options']['tablet_skin']['content']), $expected);
//        $this->assertEquals($this->has("Alpha", $arg['blocks']['skin']['options']['phone_skin']['content']), $expected);
//
//    }

//    public function preferencesListData()
//    {
//        $data = [
//            "section" => "general",
//            "dontOverride" => [],
//        ];
//
//        return [
//            [$data, true],
//            [array_merge($data, ["section" => "hello"]), false],
//            [array_merge($data, ["dontOverride" => ['skin']]), false],
//        ];
//    }

    /**
     * @dataProvider preferencesSaveData
     */
//    public function testPreferencesSaveData($data, $expected)
//    {
//        $this->class->rcmail->output->set_env("xphone", $data['xphone']);
//        $this->class->rcmail->output->set_env("xtablet", $data['xtablet']);
//
//        $_POST = array(
//            "_skin" => "desktop",
//            "_tablet_skin" => "tablet",
//            "_phone_skin" => "phone",
//        );
//
//        $arg = $this->class->preferencesSave(array('section' => "general"));
//
//        $this->assertEquals($arg['prefs']["desktop_skin"], "desktop");
//        $this->assertEquals($arg['prefs']["tablet_skin"], "tablet");
//        $this->assertEquals($arg['prefs']["phone_skin"], "phone");
//        $this->assertEquals($arg['prefs']["skin"], $expected);
//    }

//    public function preferencesSaveData()
//    {
//        $data = [
//            "xphone" => false,
//            "xtablet" => false,
//        ];
//
//        return [
//            [$data, "desktop"],
//            [array_merge($data, ["xphone" => true]), "phone"],
//            [array_merge($data, ["xtablet" => true]), "tablet"],
//        ];
//    }
}
