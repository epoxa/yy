<?php

class TestFirst extends PHPUnit_Extensions_Selenium2TestCase
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

	public function test_demo()
	{
// open | / |
		$this->url("/");
// assertTitle | YY Demo |
//		echo $this->source();
		$result = $this->title();
		$this->assertEquals("YY Demo", $result);
// assertText | css=h2 | Hello World
		$result = $this->byCssSelector("h2")->text();
		$this->assertEquals("Hello World", $result);
// assertText | //div[@id='_YY_0']/div/div[2]/div/div[2]/h2 | To Do
		$result = $this->byXPath("//div[@id='_YY_0']/div/div[2]/div/div[2]/h2")->text();
		$this->assertEquals("To Do List", $result);
// click | link=Open demo |
		$this->byLinkText("Open demo")->click();
// type | id=2[name] | epoxa
		$this->byId("2[name]")->value("epoxa");
// click | link=Say |
		$this->byLinkText("Say")->click();
// click | link=Not now |
		$this->byLinkText("Not now")->click();
// click | link=Reset |
		$this->byLinkText("Reset")->click();
// click | link=Back to list |
		$this->byLinkText("Back to list")->click();
// click | xpath=(//a[contains(text(),'Open demo')])[2] |
		$this->byXPath("(//a[contains(text(),'Open demo')])[2]")->click();
// click | link=Back to list |
		$this->byLinkText("Back to list")->click();
	}

}

