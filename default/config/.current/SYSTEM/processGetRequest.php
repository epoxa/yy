<?php

use YY\System\Utils;
use YY\System\YY;

YY::TryRestore();

if (isset(YY::$ME)) {

	Utils::StoreParamsInSession();
	YY::DrawEngine("template-engine.php");

} else {

	$ready = isset($_COOKIE[INSTALL_COOKIE_NAME]) && Utils::CheckTempKey($_COOKIE[INSTALL_COOKIE_NAME]);
	if (isset($_COOKIE[INSTALL_COOKIE_NAME])) {
		setcookie(INSTALL_COOKIE_NAME, "", time() - 3600);
		unset($_COOKIE[INSTALL_COOKIE_NAME]);
	}
	if ($ready) {
		YY::Log('system', 'Requirements checkup is ok');
		YY::createNewIncarnation();
		Utils::StartSession(YY::$ME->_YYID);
		Utils::StoreParamsInSession();
		Utils::RedirectRoot();
	} else {
		YY::Log('system', 'Draw requirements checkup');
		include TEMPLATES_DIR . 'template-checkup.php';
	}

}

