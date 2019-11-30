<?php

return [
    //static path (dir name) , eg:  'swagger' or '/swagger'
    'path'               => 'swagger',
    //api url
    'url'                => '/swagger/api',
    //support url array eg:  ['xxxx']  or ["name" => "xxx", "url" => "xxx"]
    'urls'               => [],
    //  swagger version , support 2, 3, default 3
    'version'            => \Common\Swagger\Swagger::SWAGGER_VERSION_DEFAULT,
    // The OAuth configration
    'oauthConfiguration' => [],
];