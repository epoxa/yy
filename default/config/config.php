<?php

define('CRON_MODE', php_sapi_name() === 'cli');

ini_set('default_charset', 'utf-8');
mb_internal_encoding("UTF-8");

define('ROOT_DIR', realpath(__DIR__ . '/..') . '/');
define('CLASSES_DIR', ROOT_DIR . 'classes/');
define('TEMPLATES_DIR', ROOT_DIR . 'templates/');
define('CONFIGS_DIR', ROOT_DIR . 'config/');
define('DATA_DIR', ROOT_DIR .'runtime/data/');
define('LOG_DIR', ROOT_DIR . 'runtime/log/');
define('SESSIONS_DIR', ROOT_DIR . 'runtime/sessions/');
define('FILES_DIR', ROOT_DIR . 'www/files/');

if (!CRON_MODE) {
  define('DOMAIN_NAME', isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_ADDR']);
  define('PROTOCOL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']) ? 'https://' : 'http://');
  define('ROOT_PATH', ''); // If not empty must have trailing slash but not initial
  define('ROOT_URL', DOMAIN_NAME . '/' . ROOT_PATH);
  define('COOKIE_NAME', 'YY');
  define('INSTALL_COOKIE_NAME', 'XX');
  define('DEFAULT_SESSION_IP_CHECKING', true);
  define('DEFAULT_SESSION_LIFETIME', 3600 * 24 * 3); // Three days

  ini_set('session.gc_maxlifetime', DEFAULT_SESSION_LIFETIME);
  ini_set('session.cookie_lifetime', DEFAULT_SESSION_LIFETIME);
  ini_set('session.save_path', SESSIONS_DIR);
  ini_set('session.cookie_domain', DOMAIN_NAME);
  ini_set('session.gc_probability', '5');
}

// To be customized

date_default_timezone_set('UTC');
define('DEBUG_MODE', true);
define('DEBUG_ALLOWED_IP', CRON_MODE || in_array($_SERVER['REMOTE_ADDR'], array(
		'127.0.0.1',
	)));
define('YYID', 'YYID');

// Totally custom
