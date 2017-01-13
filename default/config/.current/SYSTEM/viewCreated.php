<?php

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
