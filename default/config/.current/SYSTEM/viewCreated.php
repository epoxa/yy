<?php

use YY\Demo\Translation\Agent;
use YY\System\YY;
use YY\Demo\Demo;

$curator = null;

if (isset(YY::$ME['curator'])) {

	$curator = YY::$ME['curator'];

} else {

	$curator = new Demo();
	YY::$ME['curator'] = $curator;

}

YY::$CURRENT_VIEW['ROBOT'] = $curator;

if (isset(YY::$ME['selectedLanguage'])) {
	YY::$CURRENT_VIEW['LANGUAGE'] = YY::$ME['selectedLanguage'];
}
if (isset(YY::$ME['translateMode'])) {
	YY::$CURRENT_VIEW['TRANSLATOR'] = new Agent();
}
