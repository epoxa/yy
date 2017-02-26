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

	function registerTranslatable($trace, $slug, $original)
	{
		$attributes['data-translate-slug'] = $slug;
		$this['slugs'][$slug] = $original;
		$slug = json_encode($slug);
		YY::clientExecute("window.yy_translate_agent.registerTranslatable($slug);");
	}

	function showTranslatePrompt($_params)
	{
		$slug = $_params['slug'];
		$original = json_encode($this['slugs'][$slug]);
		$current = json_encode(isset(YY::$CURRENT_VIEW['TRANSLATION'][$slug]) ? YY::$CURRENT_VIEW['TRANSLATION'][$slug] : '');
		$slug = json_encode($slug);
		YY::clientExecute("window.yy_translate_agent.showTranslatePrompt($slug,$original,$current);");
	}

	function setTranslation($_params)
	{
		// TODO: Filter some html tags
		$slug = $_params['slug'];
		$translation = $_params['translation'];
		YY::$CURRENT_VIEW['TRANSLATION'][$slug] = $translation;
	}

}