<?php

declare(strict_types=1);

namespace Lengbin\Hyperf\Swagger;

use http\Exception\InvalidArgumentException;

class Generator
{
    /**
     * 生成文件目录
     * @var string $filePath
     */
    public $filePath;

    /**
     * uri 请求地址
     * @var string $path
     */
    public $path;

    /**
     * 请求方式
     * @var string $method
     */
    public $method;

    /**
     * 标签分类
     * @var array $tags
     */
    public $tags;

    /**
     * 是否授权
     * @var boolean $security
     */
    public $security;

    /**
     * 是否弃用
     * @var boolean $deprecated
     */
    public $deprecated;

    /**
     * 接口名称
     * @var string $summary
     */
    public $summary;

    /**
     * 接口描述
     * @var string $description
     */
    public $description;

    /**
     * 请求内容类型
     * @var array $consumes
     */
    public $consumes;

    /**
     * 请求返回内容类型
     * @var array $produces
     */
    public $produces;

    /**
     * 请求参数
     * @var array $parameter
     */
    public $parameter;

    /**
     * 开启使用默认返回自定义模版
     *
     * @var boolean $openResponseTemplate
     * @see checkResponse
     */
    public $openResponseTemplate;

    /**
     * 请求响应设置
     * @var array $response
     */
    public $response;

    /**
     * 自定义参数
     * @var array $definition
     */
    public $definition;

    /**
     * response template
     *
     * @var array|null $responseTemplate
     * @see $openResponseTemplate
     */
    public $responseTemplate;

    /**
     * Generator constructor.
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->init($config);
    }

    /**
     * assgin
     *
     * @param array $config
     */
    public function init(array $config)
    {
        foreach ($config as $key => $value) {
            $this->$key = $value;
        }
        if ($this->responseTemplate === null) {
            $this->responseTemplate = [
                'code'    => 0,
                'message' => 'Success',
                'data'    => '{{replace}}',
            ];
        }
    }

    /**
     * 获得文件名称
     * @return string
     */
    public function getGeneratorFileName()
    {
        if (empty($this->tags)) {
            throw new InvalidArgumentException('Tags Cannot Be Empty', 400);
        }
        $tags = $this->tags;
        sort($tags);
        return strtolower($tags[0]);
    }

    /**
     * 文件名称
     * @return string
     */
    public function getFileName($fileName, $isDefinitionFile = false)
    {
        return substr($this->getFile($fileName, $isDefinitionFile), strlen(dirname($this->filePath)) + 1);
    }

    /**
     * 文件 - 绝对路径
     * @return string
     */
    public function getFile($fileName, $isDefinitionFile = false)
    {
        $sufix = '.php';
        if ($isDefinitionFile) {
            $sufix = '-definition.php';
        }
        return $this->filePath . DIRECTORY_SEPARATOR . $fileName . $sufix;
    }

    
}
