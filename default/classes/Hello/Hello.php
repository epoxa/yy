<?php

namespace YY\Demo\Hello;

use YY\System\YY;
use YY\System\Robot;

class Hello extends Robot
{

	function _PAINT()
	{
		// Define extended control styles

		$phr = ['before' => '<br>&ndash;&nbsp;'];
		$btn = ['class' => ['btn', 'btn-default', 'btn-small'], 'style' => ['margin-left' => '6px']];

		// Draw dialog based on current robot state

		echo YY::drawText($phr, "Hi! What is your name?");
		if (empty($this['name'])) {
			echo YY::drawInput($phr, YY::$ME, 'name');
			echo YY::drawCommand($btn, 'Say', $this, 'setName');
		} else {
			echo YY::drawText($phr, htmlspecialchars(YY::$ME['name']));
			echo YY::drawText($phr, "Glad to meet you " . htmlspecialchars($this['name']));
			echo YY::drawText($phr, "Wanna look at this demo source code?");
			if (!isset($this['answer'])) {
				echo YY::drawText(null, '<br>');
				echo YY::drawCommand($btn, 'Yes please', $this, 'yes');
				echo YY::drawCommand($btn, 'Not now', $this, 'no');
			} else {
				if ($this['answer']) {
					echo YY::drawText($phr, 'Yes please');
					echo YY::drawText($phr, 'Here you are');
					echo YY::drawText(['before' => '<pre>', 'after' => '</pre>'],
						htmlspecialchars(file_get_contents(__FILE__)));
				} else {
					echo YY::drawText($phr, 'Not now');
					echo YY::drawText($phr, "Ok no problem");
				}
				echo YY::drawText(null, '<br>');
				echo YY::drawCommand($btn, 'Reset', $this, 'reset');
			}
		}
	}

	function setName()
	{
		$this['name'] = empty(YY::$ME['name']) ? 'Incognito' : ucfirst(trim(YY::$ME['name']));
	}

	function yes()
	{
		$this['answer'] = true;
	}

	function no()
	{
		$this['answer'] = false;
	}

	function reset()
	{
		unset($this['name'], $this['answer']);
	}

}
