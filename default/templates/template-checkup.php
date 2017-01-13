<?php
use YY\System\YY;
use YY\System\Utils;
$tempKey = Utils::GenerateTempKey();
?><!DOCTYPE html>
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
  <meta name="Robots" content="noindex,nofollow"/>
  <title><?= YY::Config('title') ?></title>
  <link rel="icon" href="<?= PROTOCOL . ROOT_URL ?>favicon.ico" type="image/x-icon">
  <link rel="shortcut icon" href="<?= PROTOCOL . ROOT_URL ?>favicon.ico" type="image/x-icon">
</head>
<body style="margin: 0">
<script type="text/javascript">
    document.cookie = "<?= INSTALL_COOKIE_NAME ?>=<?= $tempKey ?>";
    if (document.cookie.indexOf('<?= INSTALL_COOKIE_NAME ?>=') == -1) {
      document.write('<h1>Error</h1><p>Сookies must be enabled!</p>');
    } else {
      window.location = "<?= PROTOCOL . ROOT_URL ?>";
    }
</script>
<noscript>
  <h1>Error</h1>
  <p>Javascript must be enabled!</p>
</noscript>
</html>
