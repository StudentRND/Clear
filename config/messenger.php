<?php

$config = json_decode(file_get_contents(__DIR__.DIRECTORY_SEPARATOR.'local.json'), true);

return [
    'access_token' => $config['messenger']['access_token'] ?? ''
];
