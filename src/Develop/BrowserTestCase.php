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
		$this->installConsoleHook();
	}

    public function onNotSuccessfulTest($e)
	{
		if ($this->listener && $e instanceof Exception) {
			$this->listener->addError($this, $e, null);
		}
        if ($this->artifactDir) {
            $fName = $this->artifactDir . "/" . get_class($this) . '__' . $this->getName() . '__' . date('Y-m-d\TH-i-s') . '.log';
            try {
                $consoleOutput = $this->getConsoleMessages();
                file_put_contents($fName, print_r($consoleOutput, true));
            } catch(Exception $e) {
                file_put_contents($fName, "Can not get console output: " . $e->getMessage());
            }
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
    var oldError = console.error;
    var oldWarn = console.warn;
    var messages = [];
    console.log = function (message) {
        messages.push(message);
        oldLog.apply(console, arguments);
    };
    console.error = function (message) {
        messages.push(message);
        oldError.apply(console, arguments);
    };
    console.warn = function (message) {
        messages.push(message);
        // DO MESSAGE HERE.
        oldWarn.apply(console, arguments);
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
		return $this->exec('return window.getNewConsoleMessages ? window.getNewConsoleMessages() : ""');
	}

	protected function isBlindDisplayed()
	{
		return $this->exec("return typeof blind !== 'undefined' && blind.style.display == ''");
	}

	public function waitForEngine()
	{
		try {
			parent::byId('blind');
		} catch(Exception $e) {
			return;
		}
        /** @var Exception $except */
        $except = null;
		$this->waitUntil(function() use (&$except) {
            try {
                return $this->isBlindDisplayed() ? null : true;
            } catch(Exception $e) {
                if ($e->getCode() == 26 && strpos($e->getMessage(), 'unexpected alert open') === 0) {
                    // Just skip it
                } else {
                    $except = $e;
                }
                return true;
            }
		}, 5000);
        if ($except) {
            throw $except;
        }
	}

    public function alertIsPresent()
    {
        usleep(100000);
        $this->waitForEngine();
        return parent::alertIsPresent();
    }

    public function alertText($value = null)
    {
        usleep(100000);
        $this->waitForEngine();
        return parent::alertText($value);
    }

    public function acceptAlert()
    {
        usleep(100000);
        $this->waitForEngine();
        parent::acceptAlert();
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
