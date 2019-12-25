<?php

return [
    //static path (dir name) , eg:  'swagger' or '/swagger'
    'path'               => 'swagger',
    // generator annotation file path
    'filePath'           => BASE_PATH . '/runtime/swagger',
    //api url
    'url'                => '/swagger/api',
    //  swagger version , support 2, 3, default 3
    'version'            => \Lengbin\Hyperf\Swagger\Swagger::SWAGGER_VERSION_DEFAULT,
    // The OAuth Configration
    'oauthConfiguration' => [],
    // generator config
    'generator'          => [
        // http method , eg: get, post, delete put
        'httpMethods'     => [],
        // content-type, eg: "application/json", "application/x-www-form-urlencoded"
        'contentTypes'    => [],
        //Parameters of the position, eg: "formData", "path", "query", "header", "body"
        'parameterIns'    => [],
        // Parameter type, eg: "string", "number", "integer", "boolean", "file"
        'parameterTypes'  => [],
        // definition type, eg: "array" , "object" , "string", "number", "integer", "boolean"
        'definitionTypes' => [],
        // default value
        'default'         => [
            // open response template
            "openResponseTemplate" => false,
            /**
             * response default definition template, eg:
             *'responseTemplate' => [
             *  'code' => 0,
             *  'message' => 'Success',
             *  'data' => '{{replace}}',
             *],
             */
            'responseTemplate'     => [],
            /**
             * parameters
             * [
             *  [
             *      "name" => "token",
             *      "description" => "Token",
             *      "in" => "header",
             *      "type" => "string",
             *      "ref" => "",
             *      "required" => "yes", // "yes" or "no"
             *      "default" => "1",
             *  ]
             * ]
             */
            'parameters'           => [],
            /**
             * responses
             * [
             *  [
             *   "status"      => 200,
             *   "description" => "success",
             *   "type"        => "object",
             *   "ref"         => "SuccessDefault",
             *   "example"     => '',
             *  ]
             * ]
             */
            'responses'            => [
                [
                    "status"      => 200,
                    "description" => "success",
                    "type"        => "object",
                    "ref"         => "SuccessDefault",
                    "example"     => '',
                ],
                [
                    "status"      => "default",
                    "description" => "请求失败， http status 强行转为200, 通过code判断",
                    "type"        => "object",
                    "ref"         => "ErrorDefault",
                    "example"     => '',
                ],
            ],
            /**
             * definition select template, eg:
             */
            'definitionTemplate'   => [
                'default' => [],
                'page'    => [
                    [
                        'property'    => 'list',
                        'description' => '列表',
                        'type'        => 'array',
                        'ref'         => '',
                        'example'     => '',
                    ],
                    [
                        'property'    => 'currentPage',
                        'description' => '当前分页',
                        'type'        => 'integer',
                        'ref'         => '',
                        'example'     => 1,
                    ],
                    [
                        'property'    => 'pageSize',
                        'description' => '分页大小',
                        'type'        => 'integer',
                        'ref'         => '',
                        'example'     => 10,
                    ],
                    [
                        'property'    => 'totalPage',
                        'description' => '总分页',
                        'type'        => 'integer',
                        'ref'         => '',
                        'example'     => 1,
                    ],
                    [
                        'property'    => 'totalCount',
                        'description' => '总条数',
                        'type'        => 'integer',
                        'ref'         => '',
                        'example'     => 11,
                    ],
                ],
            ],
        ],
    ],
];
