<?php

return [
    'maxViewsPerIncarnation' => 20,
    'defaultStyles' => self::FS('../CONFIG/visuals'),
    'started' => self::FS_SCRIPT('started.php'),
    'processGetRequest' => self::FS_SCRIPT('processGetRequest.php'),
    'incarnationCreated' => self::FS_SCRIPT('incarnationCreated.php'),
    'viewCreated' => self::FS_SCRIPT('viewCreated.php'),
    'error' => self::FS_SCRIPT('error.php'),
];
