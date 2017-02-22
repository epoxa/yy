<?php


namespace YY\Demo\Translation;


use YY\System\Robot;
use YY\System\YY;

class Agent extends Robot
{

	function __construct($init = null)
	{
		parent::__construct($init);
		$this['include'] = [
			'<script src="/translate/agent.js"></script>',
			'',
		];
		$this['slugs'] = [];
	}

	function _PAINT()
	{
		$myHandle = YY::GetHandle($this);
		YY::clientExecute("window.yy_translate_agent.setTranslatorHandle('$myHandle');");
	}

	function registerText($trace, $slug, $original, $current)
	{
		$this['slugs'][$slug] = [
			'original' => $original,
			'current' => $current,
		];
		$slug = json_encode($slug);
		YY::clientExecute("window.yy_translate_agent.registerTranslatable($slug);");
	}

	function showTranslatePrompt($_params)
	{
		$slug = $_params['slug'];
		$original = json_encode($this['slugs'][$slug]['original']);
		$current = json_encode($this['slugs'][$slug]['current']);
		$slug = json_encode($slug);
		YY::clientExecute("window.yy_translate_agent.showTranslatePrompt($slug,$original,$current);");
	}

	function setTranslation($_params)
	{
		$slug = $_params['slug'];
		$translation = $_params['translation'];
		$lang = YY::$CURRENT_VIEW['LANGUAGE'];
		if (!isset(YY::$WORLD['SYSTEM']['LANGUAGES'])) YY::$WORLD['SYSTEM']['LANGUAGES'] = [];
		if (!isset(YY::$WORLD['SYSTEM']['LANGUAGES'][$lang])) YY::$WORLD['SYSTEM']['LANGUAGES'][$lang] = [];
		YY::$WORLD['SYSTEM']['LANGUAGES'][$lang][$slug] = $translation;
	}

}
