<?php

class TestTranslation extends PHPUnit_Extensions_Selenium2TestCase
{

	protected function setUp()
	{
		$this->setBrowser(getenv('YY_TEST_BROWSER')); //  firefox
		$this->setBrowserUrl(getenv('YY_TEST_BASE_URL')); // http://yy.local/
		$this->setHost(getenv('YY_TEST_SELENIUM_HOST')); // 127.0.0.1
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
		$this->byId("1[#translateMode]")->click();
		$result = $this->byCssSelector("div.alert.alert-danger > span")->text();
		$this->assertEquals("Selected language does not have a translation at the moment.\nYou can translate any text by clicking red dot while \"Translate mode\" is on.", $result);
		return;
// answerOnNextPrompt | К списку |
// ERROR: Caught exception [ERROR: Unsupported command [answerOnNextPrompt | К списку | ]]

		$this->byXPath("//body/div[3]")->click();

// assertPrompt | Back to list |
// ERROR: Caught exception [ERROR: Unsupported command [getPrompt |  | ]]

		$this->byLinkText("К списку")->click();
// mouseMoveAt | link=Back to list |
// ERROR: Caught exception [ERROR: Unsupported command [mouseMoveAt | link=Back to list | ]]
// mouseMoveAt | //div[@id='_YY_0']/div/div[2]/div/div[3]/h2/span |
// ERROR: Caught exception [ERROR: Unsupported command [mouseMoveAt | //div[@id='_YY_0']/div/div[2]/div/div[3]/h2/span | ]]
// answerOnNextPrompt | Перевод |
// ERROR: Caught exception [ERROR: Unsupported command [answerOnNextPrompt | Перевод | ]]
// click | //body/div[3] |
		$this->byXPath("//body/div[3]")->click();
// assertPrompt | Translation |
// ERROR: Caught exception [ERROR: Unsupported command [getPrompt |  | ]]
// click | xpath=(//a[contains(text(),'Open demo')])[3] |
		$this->byXPath("(//a[contains(text(),'Open demo')])[3]")->click();
	}

}

