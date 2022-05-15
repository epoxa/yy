<?php

/**
 *
 */

namespace YY\Core\Exception;

use Exception;

class EObjectDestroyed extends Exception
{

	public $YYID;

	public function __construct($yyid)
	{
		parent::__construct("Object destroyed! (" . $yyid . ")");
		$this->YYID = $yyid;
	}

}
