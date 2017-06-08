<?php

use YY\Develop\BrowserTestCase;

class TestFirst extends BrowserTestCase
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
		$this->url("/");
		$result = $this->title();
		$this->assertEquals("YY Demo", $result);
		$result = $this->byCssSelector("h3")->text();
		$this->assertEquals("Hello World", $result);
		$result = $this->byXPath("//a[@class='thumbnail']/h3")->text();
		$this->assertEquals("Hello World", $result);
		$this->byXPath("//a[@class='thumbnail']")->click();
		$this->byId("2[name]")->value("epoxa");
		$this->byLinkText("Say")->click();
		$this->byLinkText("Not now")->click();
		$this->byLinkText("Reset")->click();
		$this->byXPath("//a[contains(text(),'Back to index')]")->click();
		$this->byXPath("//a/h3[contains(text(),'Simple To Do List')]")->click();
		$this->byXPath("//a[contains(text(),'Back to index')]")->click();
	}

}

