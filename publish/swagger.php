<?php

return [
    //static path (dir name) , eg:  'swagger' or '/swagger'
    'path'               => 'swagger',
    //api url
    'url'                => '/swagger/api',
    //  swagger version , support 2, 3, default 3
    'version'            => \Lengbin\Hyperf\Swagger\Swagger::SWAGGER_VERSION_DEFAULT,
    // The OAuth configration
    'oauthConfiguration' => [],
];