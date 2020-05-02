<?php
/**
 * Roundcube Plus xframework plugin
 *
 * Copyright 2016, Tecorama LLC.
 *
 * @license Commercial. See the file LICENSE for details.
 */

require_once(__DIR__ . "/../../xframework/common/Test.php");
require_once(__DIR__ . "/../common/Html.php");

class HtmlTest extends XFramework\Test
{
    public function __construct() {
        parent::__construct();

        $this->code = "<html><head><style></style></head><body class='roundcube'>".
            "<div id='hello'><p><span class='not-closed'>text</p><p>paragraph</p></div></body></html>";
        $this->html = new \XFramework\Html();
    }

    public function testInsertBefore()
    {
        $code = $this->code;
        $this->html->insertBefore("div id='hello", "<inserted>", $code);
        $this->assertTrue(strpos($code, "><inserted><div") !== false);

        $code = $this->code;
        $this->html->insertBefore("invalid marker", "<inserted>", $code);
        $this->assertTrue(strpos($code, "<inserted>") === false);

        $code = $this->code;
        $this->html->insertBefore("invalid marker", "<inserted>", $code, "invalid container");
        $this->assertTrue(strpos($code, "<inserted>") === false);
    }

    public function testInsertAfter()
    {
        $code = $this->code;
        $this->html->insertAfter("div id='hello", "div", "<inserted>", $code);
        $this->assertTrue(strpos($code, "</div><inserted></body>") !== false);

        $code = $this->code;
        $this->html->insertAfter("invalid marker", "div", "<inserted>", $code);
        $this->assertTrue(strpos($code, "<inserted>") === false);
    }

    public function testInsertAtBeginning()
    {
        $code = $this->code;
        $this->html->insertAtBeginning("div id='hello", "<inserted>", $code);
        $this->assertTrue(strpos($code, "<div id='hello'><inserted><p>") !== false);

        $code = $this->code;
        $this->html->insertAtBeginning("invalid marker", "<inserted>", $code);
        $this->assertTrue(strpos($code, "<inserted>") === false);
    }

    public function testInsertAtEnd()
    {
        $code = $this->code;
        $this->html->insertAtEnd("div id='hello", "<inserted>", $code);
        $this->assertTrue(strpos($code, "</p><inserted></div>") !== false);

        $code = $this->code;
        $this->html->insertAtEnd("invalid marker", "<inserted>", $code);
        $this->assertTrue(strpos($code, "<inserted>") === false);

        $code = $this->code;
        $this->html->insertAtEnd("paragraph", "<inserted>", $code);
        $this->assertTrue(strpos($code, "<inserted>") === false);

        $code = $this->code;
        $this->html->insertAtEnd("not-closed", "<inserted>", $code);
        $this->assertTrue(strpos($code, "<inserted>") === false);
    }

    public function testInsertBeforeBodyEnd()
    {
        $code = $this->code;
        $this->html->insertBeforeBodyEnd("<inserted>", $code);
        $this->assertTrue(strpos($code, "</div><inserted></body>") !== false);
    }

    public function testInsertBeforeHeadEnd()
    {
        $code = $this->code;
        $this->html->insertBeforeHeadEnd("<inserted>", $code);
        $this->assertTrue(strpos($code, "</style><inserted></head>") !== false);
    }
}