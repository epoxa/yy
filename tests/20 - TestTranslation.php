<?php

class TestTranslation extends PHPUnit_Extensions_Selenium2TestCase
{

	protected function setUp()
	{
//		$this->setBrowser(getenv('YY_TEST_BROWSER')); //  firefox
		$this->setBrowser('chrome'); //  Because of firefox "moveto" method error
		$this->setBrowserUrl(getenv('YY_TEST_BASE_URL')); // http://yy.local/
//		$this->setHost(getenv('YY_TEST_SELENIUM_HOST')); // 127.0.0.1
		$this->setHost('hub'); // 127.0.0.1
		$this->setPort((int)getenv('YY_TEST_SELENIUM_PORT')); // 4444
	}

	public function setUpPage()
	{
		$this->timeouts()->implicitWait(5000);
	}

	public function test_trans()
	{
		$this->url("/");
		$this->byXPath("(//a[contains(text(),'Open demo')])[3]")->click();
		$result = $this->byCssSelector("h3")->text();
		$this->assertEquals("Current language", $result);
		$this->byLinkText("Add New")->click();
		$this->byId("1[newLangName]")->value("RU");
		$this->byLinkText("Save")->click();
		$result = $this->byCssSelector("div.alert.alert-danger")->text();
		$this->assertEquals("Selected language does not have a translation at the moment.\nYou can translate any text by clicking red dot while \"Translate mode\" is on.", $result);
		$result = $this->byCssSelector("div.alert.alert-danger > span")->text();
		$this->assertEquals("Selected language does not have a translation at the moment.\nYou can translate any text by clicking red dot while \"Translate mode\" is on.", $result);

		$this->moveto(['element' => $this->byLinkText("Back to list"), 'xoffset' => 10, 'yoffset' => 10]);
		sleep(1);
		$this->byId("yy-translator")->click();
		sleep(1);
		$result = $this->alertText();
		$this->assertEquals("Back to list", $result);
		$this->alertText("К списку");
		$this->acceptAlert();
		sleep(1);
		$body = $this->byTag('body')->text();
		$this->assertNotContains("Back to list", $body);
		$this->byLinkText("К списку")->click();

		$this->moveto(['element' => $this->byXPath("(//h2)[3]"), 'xoffset' => 6, 'yoffset' => 6]);
		sleep(1);
		$this->byId("yy-translator")->click();
		sleep(1);
		$result = $this->alertText();
		$this->assertEquals("Translation", $result);
		$this->alertText("Перевод");
		$this->acceptAlert();
		sleep(1);
		$body = $this->byTag('body')->text();
		$this->assertContains("Перевод", $body);
		$this->assertNotContains("Translation", $body);
	}

}

