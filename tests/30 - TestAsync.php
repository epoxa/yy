<?php

class TestAsync extends PHPUnit_Extensions_Selenium2TestCase
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

	private function stopRefresh() {
		$this->waitUntil(function() {
			return $this->execute(['script' => "return $(blind).is(':visible')", 'args' => []]) ? null : true;
		}, 1000);
		$this->execute(['script' => 'clearTimeout(window.timeoutHandle);', 'args' => []]); // Stop auto refreshing
	}

	public function test_async()
	{
		// Start

		$this->url("/");
		$result = $this->title();
		$this->assertEquals("YY Demo", $result);

		$this->byXPath("(//a[contains(text(),'Open demo')])[4]")->click();

		// Open async demo

		$result = $this->byCssSelector("strong > span")->text();
		$this->assertEquals("$ 0.00", $result);

		// Start second runner

		$this->byCssSelector("#_YY_3 > div.panel.panel-default > div.panel-body > div.row > div.col-md-1 > div.btn-group > a.btn.btn-success > span.glyphicon.glyphicon-play")->click();

		// Switch to demos list and back

		$this->byLinkText("Back to list")->click();
		$this->byXPath("(//a[contains(text(),'Open demo')])[4]")->click();

		// Ensure second runner is run

		usleep(1500000);
		$this->stopRefresh();
		$amount = $this->byCssSelector("#_YY_3 > div.panel.panel-default > div.panel-body > div.row > div.col-md-5.text-right > span");
		$result = $amount->text();
		$this->assertNotEquals("$ 0.00", $result);
		usleep(1500000);
		$this->refresh();
		$amount = $this->byCssSelector("#_YY_3 > div.panel.panel-default > div.panel-body > div.row > div.col-md-5.text-right > span");
		$newResult = $amount->text();
		$this->assertNotEquals($result, $newResult);

		// Check other runners is idle

		$result = $this->byCssSelector("div.col-md-5.text-right > span")->text();
		$this->assertEquals("$ 0.00", $result);
		$result = $this->byCssSelector("#_YY_4 > div.panel.panel-default > div.panel-body > div.row > div.col-md-5.text-right > span")->text();
		$this->assertEquals("$ 0.00", $result);

		// Start all other runners

		$this->stopRefresh();
		$this->byCssSelector("#_YY_4 > div.panel.panel-default > div.panel-body > div.row > div.col-md-1 > div.btn-group > a.btn.btn-success > span.glyphicon.glyphicon-play")->click();

		$this->stopRefresh();
		$this->byCssSelector("#_YY_2 > div.panel.panel-default > div.panel-body > div.row > div.col-md-1 > div.btn-group > a.btn.btn-success > span.glyphicon.glyphicon-play")->click();

		// Go in and out of demo multiple times

		for ($i = 0; $i < 12; $i++) {
			$this->byLinkText("Back to list")->click();
			$this->byXPath("(//a[contains(text(),'Open demo')])[4]")->click();
			usleep(rand(0, 1500000));
		}

/*
		// Pause all runners

		$this->stopRefresh();

		$this->byCssSelector("#_YY_2 > div.panel.panel-default > div.panel-body > div.row > div.col-md-1 > div.btn-group > a.btn.btn-warning > span.glyphicon.glyphicon-pause")->click();

		$this->stopRefresh();

		$this->byCssSelector("#_YY_3 > div.panel.panel-default > div.panel-body > div.row > div.col-md-1 > div.btn-group > a.btn.btn-warning > span.glyphicon.glyphicon-pause")->click();

		$this->stopRefresh();

		$this->byCssSelector("#_YY_4 > div.panel.panel-default > div.panel-body > div.row > div.col-md-1 > div.btn-group > a.btn.btn-warning > span.glyphicon.glyphicon-pause")->click();

		$this->stopRefresh();
*/
		/*
		 *  TODO:
		 *
				// Go to translation demo, add a language and turn translation mode on

				$this->byLinkText("Back to list")->click();
				$this->byXPath("(//a[contains(text(),'Open demo')])[3]")->click();
				$this->byLinkText("Add New")->click();
				$this->byId("5[newLangName]")->value("RU");
				$this->byLinkText("Save")->click();
				$this->byLinkText("Back to list")->click();
				$this->byXPath("(//a[contains(text(),'Open demo')])[4]")->click();

		// answerOnNextPrompt | %2.2f |
		// ERROR: Caught exception [ERROR: Unsupported command [answerOnNextPrompt | %2.2f | ]]
		// click | id=yy-translator |
				$this->byId("yy-translator")->click();
		// assertPrompt | $&nbsp;%2.2f |
		// ERROR: Caught exception [ERROR: Unsupported command [getPrompt |  | ]]
		// assertText | css=strong > span | 673.36

		*/

		// Check total sum is correct

		$this->stopRefresh();

		$val1 = $this->byCssSelector("div.col-md-5.text-right > span")->text();
		$val2 = $this->byCssSelector("#_YY_3 > div.panel.panel-default > div.panel-body > div.row > div.col-md-5.text-right > span")->text();
		$val3 = $result = $this->byCssSelector("#_YY_4 > div.panel.panel-default > div.panel-body > div.row > div.col-md-5.text-right > span")->text();

		$val1 = strtr($val1, ['$' => '', ' ' => '']);
		$val2 = strtr($val2, ['$' => '', ' ' => '']);
		$val3 = strtr($val3, ['$' => '', ' ' => '']);


		$result = $this->byCssSelector("strong > span")->text();
		$result = strtr($result, ['$' => '', ' ' => '']);

		$this->assertEquals(floatval($val1) + floatval($val2) + floatval($val3), floatval($result));
	}

}

