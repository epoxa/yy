<?php use YY\System\YY; ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="Robots" content="noindex,nofollow"/>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
    <title><?= YY::Config('title') ?></title>
    <script
        src="//code.jquery.com/jquery-3.1.1.min.js"
        integrity="sha256-hVVnYaiADRTO2PzUGmuLJr8BLUSjGIZsDYGmIJLv2b8="
        crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
    <script ok="1" type="text/javascript">
        var rootUrl = '<?= PROTOCOL . ROOT_URL ?>';
        var viewId = '<?= YY::GenerateNewYYID() ?>';
    </script>

    <script ok="1" type="text/javascript" src="/js/engine.js"></script>
</head>
<body>
<div id="blind" style="position: fixed; width: 100%; height: 100%; z-index: 2147483647; cursor:progress; display: none">
</div>
</body>
</html>
