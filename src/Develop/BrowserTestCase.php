<?php

namespace YY\Develop;


use Exception;
use PHPUnit_Extensions_Selenium2TestCase;
use PHPUnit_Extensions_Selenium2TestCase_Exception;
use PHPUnit_Extensions_Selenium2TestCase_ScreenshotListener;

class BrowserTestCase extends PHPUnit_Extensions_Selenium2TestCase
{

    /**
     * @var PHPUnit_Extensions_Selenium2TestCase_ScreenshotListener $listener
     */
	private $listener;
    private $artifactDir;

    protected function setArtifactFolder($dir)
    {
        if ($dir === $this->artifactDir) return;
        $this->artifactDir = $dir;
        if ($dir) {
            $this->listener = new PHPUnit_Extensions_Selenium2TestCase_ScreenshotListener($dir);
        } else {
            $this->listener = null;
        }
    }

	protected function setUp()
	{
		parent::setUp();
	}

	public function setUpPage()
	{
		$this->timeouts()->implicitWait(5000);
		$this->installConsoleHook();
	}

    public function onNotSuccessfulTest($e)
	{
		if ($this->listener) {
			$this->listener->addError($this, $e, null);
		}
		parent::onNotSuccessfulTest($e);
	}

	protected function exec($script)
	{
		return $this->execute([
			'script' => $script,
			'args' => [],
		]);
	}

	protected function installConsoleHook()
	{
		$script
			= '
(function() {
    if (window.getNewConsoleMessages) return; // Already installed
    var oldLog = console.log;
    var messages = [];
    console.log = function (message) {
        messages.push(message);
        // DO MESSAGE HERE.
        oldLog.apply(console, arguments);
    };
    window.getNewConsoleMessages = function() {
      var r = messages;
      messages = [];
      return r;
    }
})();';
		$this->exec($script);
	}

	protected function getConsoleMessages() {
		return $this->exec('return window.getNewConsoleMessages()');
	}

	protected function isBlindDisplayed()
	{
		return $this->exec("return blind && blind.style.display == ''");
	}

	public function waitForEngine()
	{
		try {
			parent::byId('blind');
		} catch(Exception $e) {
			return;
		}
		$this->waitUntil(function() {
			return $this->isBlindDisplayed() ? null : true;
		}, 5000);
	}

	protected function isElementPresent($how, $what)
	{
		$this->waitForEngine();
		try {
			$this->element($this->using($how)->value($what));
			return true;
		} catch (PHPUnit_Extensions_Selenium2TestCase_Exception $e) {
			return false;
		}
	}

	protected function assertTextPresent($phrases)
	{
		$this->waitForEngine();
		if (is_string($phrases)) $phrases = [$phrases];
		$body = $this->byTag('body')->text();
		foreach($phrases as $string) {
			$this->assertContains($string, $body, 'Text not present: ' . $string);
		}
	}

	protected function assertTextNotPresent($phrases)
	{
		$this->waitForEngine();
		if (is_string($phrases)) $phrases = [$phrases];
		$body = $this->byTag('body')->text();
		foreach($phrases as $string) {
			$this->assertNotContains($string, $body, 'Text present: ' . $string);
		}
	}

	public function byClassName($value)
	{
		$this->waitForEngine();
		return parent::byClassName($value);
	}

	public function byCssSelector($value)
	{
		$this->waitForEngine();
		return parent::byCssSelector($value);
	}

	public function byId($value)
	{
		$this->waitForEngine();
		return parent::byId($value);
	}

	public function byLinkText($value)
	{
		$this->waitForEngine();
		return parent::byLinkText($value);
	}

	public function byName($value)
	{
		$this->waitForEngine();
		return parent::byName($value);
	}

	public function byTag($value)
	{
		$this->waitForEngine();
		return parent::byTag($value);
	}

	public function byXPath($value)
	{
		$this->waitForEngine();
		return parent::byXPath($value);
	}

}
