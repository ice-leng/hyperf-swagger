<?php

declare(strict_types=1);

namespace Lengbin\Hyperf\Swagger;

use http\Exception\InvalidArgumentException;

class SwaggerAnnotation
{
    /**
     * 文件名称
     * @var string $fileName
     */
    protected $fileName;

    /**
     * generator
     * @var Generator;
     */
    protected $generator;

    /**
     * 自定义参数
     * @var array $definition
     */
    protected $definition;

    public function __construct(Generator $generator)
    {
        $this->generator = $generator;
        $this->definition = $generator->definition;
    }

    /**
     * 是否为 json
     *
     * @param $string
     *
     * @return bool|string
     */
    protected function isJson($string)
    {
        if (is_array($string)) {
            return $string;
        }
        $data = json_decode($string, true);
        return (json_last_error() == JSON_ERROR_NONE) ? is_array($data) : '';
    }

    protected function getExample($items, $definitions)
    {
        $itemData = [];
        foreach ($items as $item) {
            if ($item['type'] === 'object') {
                $item['example'] = $this->getExample($definitions[$item['ref']], $definitions);
            }
            if ($item['type'] === 'array') {
                $item['example'] = [$this->getExample($definitions[$item['ref']], $definitions)];
            }
            $itemData[$item['property']] = $item['example'];
        }
        return $itemData;
    }

    /**
     * 布尔 转 布尔字符串
     *
     * @param $value
     *
     * @return string
     */
    protected function boolChangeString($value)
    {
        if ($value) {
            return 'true';
        } else {
            return 'false';
        }
    }

    protected function format($params, $type = 0, $definitions = [])
    {
        $data = [];
        foreach ($params as $filed => $value) {
            if (is_null($value)) {
                continue;
            }
            if ($filed === 'ref' && empty($value) && $type !== 1) {
                continue;
            }
            // response
            if ($type === 1 && in_array($filed, ['example', 'type'])) {
                continue;
            }

            $string = $filed . ' = "' . $value . '"';
            if (in_array($filed, ['example', 'default', 'required']) || is_bool($value)) {
                if (strlen((string)$value) <= 0) {
                    continue;
                }
                $status = false;

                if (isset($params['type']) && $params['type'] !== "string") {
                    $status = true;
                }

                if (is_bool($value)) {
                    $status = true;
                    $value = $this->boolChangeString($value);
                }

                if ($status) {
                    $string = $filed . ' = ' . $value;
                }

            }
            if ($filed === 'ref') {
                $string = 'ref="#/definitions/' . $value . '"';
                if ($type === 1) {
                    if (empty($value)) {
                        if (empty($params['example'])) {
                            continue;
                        }
                        $v = $params['example'];
                        if ($params['type'] === 'string') {
                            $v = '"' . $params['example'] . '"';
                        }
                        $string = 'example=' . $v;
                    }
                    $string = '@SWG\Schema(' . $string . ')';
                }
                if (in_array($type, [2, 3])) {
                    $exampleItems = $definitions[$value];
                    if ($type === 2) {
                        $string = 'items={"$ref":"#/definitions/' . $value . '"}';
                        $itemData = [$this->getExample($exampleItems, $definitions)];
                    }
                    if ($type === 3) {
                        $itemData = $this->getExample($exampleItems, $definitions);
                    }
                    $item = json_encode($itemData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    $item = str_replace(['[', ']'], ['{', '}'], $item);
                    if (!empty($item)) {
                        $data[2] = 'example=' . $item;
                    }
                }
            }
            $data[] = $string;
        }
        return $data;
    }

    /**
     * 获得 参数
     * @return string
     */
    protected function getParameter()
    {
        $parameters = $this->formatParameter();
        $parameterAnnotations = [];
        foreach ($parameters as $parameter) {
            $type = $parameter['in'] === 'body' ? 1 : 0;
            $annotations = implode(",\n *        ", $this->format($parameter, $type));
            $parameterAnnotations[] = <<<parameterAnnotation
 *    
 *    @SWG\Parameter(
 *        {$annotations}
 *    )
parameterAnnotation;
        }
        return !empty($parameterAnnotations) ? (implode(",\n", $parameterAnnotations) . ",") : '';
    }

    /**
     * 获得 响应参数
     * @return string
     */
    protected function getResponses()
    {
        $responses = $this->formateResponses();
        $responseAnnotations = [];
        foreach ($responses as $response) {
            $annotations = implode(",\n *        ", $this->format($response, 1));
            $responseAnnotations[] = <<<responseAnnotation
 *    
 *    @SWG\Response(
 *        {$annotations}
 *    )
responseAnnotation;
        }
        return !empty($responseAnnotations) ? implode(",\n", $responseAnnotations) : '';
    }

    /**
     * 获得生成文件的名称
     *
     * @return string
     * @see tags
     */
    protected function getGeneratorFileName()
    {
        if ($this->fileName === null) {
            $this->fileName = $this->generator->getGeneratorFileName();
        }
        return $this->fileName;
    }

    /**
     * 开始 信息
     *
     * @param string $anchor 锚点名称
     * @param string $file   生成文件（绝对路径）
     * @param bool   $isAdd  是否添加
     *
     * @return string
     */
    protected function startInfo($anchor)
    {
        return <<<start
 * ----{$anchor} start ----
start;
    }

    /**
     * 结束 信息
     *
     * @param string $anchor 锚点名称
     *
     * @return string
     */
    protected function endInfo($anchor, $isCloes = true)
    {
        if ($isCloes) {
            return <<<end
 * )
 * ----{$anchor} end ----
end;
        } else {
            return <<<end
 * ----{$anchor} end ----
end;
        }
    }

    /**
     * 数组转字符串
     *
     * @param array $data
     *
     * @return string
     */
    protected function toJsonString(array $data)
    {
        return '{"' . implode('", "', $data) . '"}';
    }

    /**
     * 基础 信息
     * @return string
     */
    protected function getBaseInfo()
    {
        $tags = $this->toJsonString($this->generator->tags);
        $consumes = $this->toJsonString($this->generator->consumes);
        $produces = $this->toJsonString($this->generator->produces);
        return <<<base
 * @SWG\\{$this->generator->method}(
 *    path="{$this->generator->path}",
 *    tags={$tags}, 
 *    summary="{$this->generator->summary}",
 *    description="{$this->generator->description}",
 *    consumes={$consumes},
 *    produces={$produces},
base;
    }

    /**
     * 验证请求参数字段
     * 支持别名
     *
     * @param array        $requests      请求参数
     * @param array        $validateField 验证字段，支持别名  ['别名' => 字段， 0 => 字段]
     * @param string|array $default       字段默认值
     *
     * @return array
     * @author lengbin(lengbin0@gmail.com)
     */
    protected function validateRequestParams($requests, $validateField, $default = null)
    {
        $data = [];
        foreach ($validateField as $key => $field) {
            $param = isset($requests[$field]) ? $requests[$field] : null;
            if ($default !== null && $param === null) {
                if (is_array($default)) {
                    $param = (isset($default[$field])) ? $default[$field] : null;
                } else {
                    $param = $default;
                }
            }
            if (is_int($key)) {
                $data[$field] = $param;
            } else {
                $data[$key] = $param;
            }
        }
        return $data;
    }

    /**
     * 格式化返回
     *
     * @return array
     */
    protected function formatParameter()
    {
        $fields = [
            'name',
            'description',
            'in',
            'type',
            'required',
            'ref',
            'default',
        ];
        $data = [];
        foreach ($this->generator->parameter as $parameter) {
            if (empty($parameter['name'])) {
                continue;
            }
            $data[] = $this->validateRequestParams($parameter, $fields);
        }
        return $data;
    }

    /**
     * check response  status  200
     */
    protected function checkResponse()
    {
        if (empty($this->generator->response)) {
            throw new InvalidArgumentException('Response Cannot Be Empty', 400);
        }
        $fields = [
            'response' => 'status',
            'description',
            'ref',
            'example',
            'type',
        ];
        $status = $data = [];
        foreach ($this->generator->response as $response) {
            $status[] = $response['status'];
            $data[$response['status']] = $this->validateRequestParams($response, $fields);
        }

        if (!in_array('200', $status)) {
            throw new InvalidArgumentException('Response Status Must Contain 200', 400);
        }
        return $data;
    }

    /**
     * 将模版 格式化 成自定义参数
     *
     * @param $name
     * @param $ref
     *
     * @return array
     */
    protected function formateResponseTemplate($response, $name)
    {
        $data = [];
        foreach ($this->generator->responseTemplate as $key => $value) {
            $r = null;
            $type = gettype($value);
            if ($value === "{{replace}}") {
                $r = $name;
                $type = $response['type'];
                $value = $response['example'];
            }
            $data[] = [
                'property'    => $key,
                'description' => $key,
                'example'     => $value,
                'type'        => $type,
                'ref'         => $r,
            ];
        }
        return [
            'name'       => $response['ref'],
            'definition' => $data,
        ];

    }

    /**
     * 格式化 返回参数
     * @return array
     */
    protected function formateResponses()
    {
        $responses = $this->checkResponse();
        if ($this->generator->openResponseTemplate) {
            $definitionRef = $responses[200]['ref'];
            if (empty($definitionRef)) {
                $definitionRef = implode(array_map('ucfirst', explode("/", trim($this->generator->path))));
            }
            $definitionName = $definitionRef . 'Success';
            $responses[200]['ref'] = $definitionName;
            $successResponse = $this->formateResponseTemplate($responses[200], $definitionRef);
            array_unshift($this->definition, $successResponse);
        }
        return $responses;
    }

    /**
     * 判断 信息
     * @return string
     */
    protected function getJudgeInfo()
    {
        $data = [];
        if ($this->generator->security) {
            $data[] = <<<security
 *    security={{"api_key":{}}},
security;
        }

        if ($this->generator->deprecated) {
            $data[] = <<<security
 *    deprecated=true,
security;
        }
        return implode("\n", array_filter($data));
    }

    /**
     * 获得 api注释
     * @return string
     */
    protected function getAnnotationApi()
    {
        $anchor = $this->generator->path;
        $info = [
            $this->startInfo($anchor),
            $this->getBaseInfo(),
            $this->getJudgeInfo(),
            $this->getParameter(),
            $this->getResponses(),
            $this->endInfo($anchor),
        ];
        return implode("\n", array_filter($info));
    }

    protected function fomateAnnotation($file, $content, $isAdd = false)
    {
        if (($file && is_file($file)) || $isAdd) {
            $content = "/**\n" . $content . "\n */";
        } else {
            $content = "<?php\n/**\n" . $content . "\n */";
        }
        return $content;
    }

    /**
     * 获得 api注释
     * @return string
     */
    public function getApiAnnotation()
    {
        $file = $this->generator->getFile($this->getGeneratorFileName());
        return $this->fomateAnnotation($file, $this->getAnnotationApi());
    }

    /**
     * 获得接口注释
     * @return string
     */
    public function getFullApiAnnotation()
    {
        $anchor = $this->generator->path;
        $file = $this->generator->getFile($this->getGeneratorFileName());
        return [
            'file'    => $file,
            'name'    => $this->generator->getFileName($this->getGeneratorFileName()),
            'replace' => [
                [
                    'start'   => "----{$anchor} start ----",
                    'end'     => "----{$anchor} end ----",
                    'content' => $this->getAnnotationApi(),
                ],
            ],
        ];
    }

    /**
     * 格式化 自定义参数
     * @return array
     */
    protected function formatDefinition()
    {
        $definitions = [];
        $fields = [
            'property',
            'description',
            'example',
            'type',
            'ref',
        ];
        foreach ($this->definition as $definition) {
            $vaule = [];
            foreach ($definition['definition'] as $item) {
                $vaule[] = $this->validateRequestParams($item, $fields);
            }
            $definitions[$definition['name']] = $vaule;
        }
        return $definitions;
    }

    /**
     * 获得 自定义参数注释
     * @return string
     */
    protected function getDefinitions($isFull = false)
    {
        $add = true;
        $definitionAnnotations = [];
        $definitions = $this->formatDefinition();
        $defaultFile = $this->generator->getFile($this->getGeneratorFileName(), true);
        foreach ($definitions as $name => $definition) {
            $definitionItems = [];
            foreach ($definition as $datum) {
                $type = 0;
                if ($datum['type'] === 'array') {
                    $type = 2;
                }
                if ($datum['type'] === 'object' && $datum['property'] !== 'data') {
                    $type = 3;
                }
                $definitionItem = implode(",\n *        ", $this->format($datum, $type, $definitions));
                $definitionItems[] = <<<definitionItems
 *                
 *    @SWG\Property(
 *        {$definitionItem}
 *    )
definitionItems;
            }
            $definitionAnnotation = implode(",\n", $definitionItems);
            $item = <<<definitionAnnotation
 * @SWG\Definition(
 *    definition = "{$name}",
{$definitionAnnotation}
 * )
definitionAnnotation;
            $info = [
                $this->startInfo($name, $add),
                $item,
                $this->endInfo($name, false),
            ];
            $add = false;
            $data = implode("\n", $info);
            if ($isFull) {
                $data = [
                    'start'   => "----{$name} start ----",
                    'end'     => "----{$name} end ----",
                    'content' => implode("\n", $info),
                ];
            }
            $definitionAnnotations[] = $data;
        }
        return $definitionAnnotations;
    }

    /**
     * 获得 自定义参数注释
     * @return string
     */
    public function getFullDefinitionAnnotation()
    {
        $file = $this->generator->getFile($this->getGeneratorFileName(), true);
        return [
            'file'    => $file,
            'name'    => $this->generator->getFileName($this->getGeneratorFileName(), true),
            'replace' => $this->getDefinitions(true),
        ];
    }

    /**
     * 获得 自定义参数注释
     * @return string
     */
    public function getDefinitionAnnotation()
    {
        $str = '';
        $isAdd = false;
        $length = count($this->getDefinitions());
        $length -= 1;
        $file = $this->generator->getFile($this->getGeneratorFileName(), true);
        foreach ($this->getDefinitions() as $key => $annotation) {
            $str .= $this->fomateAnnotation($file, $annotation, $isAdd);
            if ($length !== $key) {
                $str .= "\n";
            }
            $isAdd = true;
        }
        return $str;
    }

    /**
     * 获得 注释
     *
     * @param bool $fullFile
     *
     * @return array
     */
    public function getAnnotation($fullFile = false)
    {
        $files = [];
        foreach ([$this->getFullApiAnnotation(), $this->getFullDefinitionAnnotation()] as $fullAnnotation) {
            $isFile = is_file($fullAnnotation['file']);
            if ($isFile) {
                $action = 0;
                $oldStr = file_get_contents($fullAnnotation['file']);;
            } else {
                $action = 1;
                $oldStr = '';
            }
            $newStr = $this->contentReplease($isFile, $oldStr, $fullAnnotation['replace']);
            if ($action === 0 && $oldStr !== $newStr) {
                $action = 2;
            }
            $data = [
                'file'   => $fullAnnotation['name'],
                'action' => $action,
                'oldStr' => $oldStr,
                'newStr' => $newStr,
            ];
            if ($fullFile) {
                $data['fullFule'] = $fullAnnotation['file'];
            }
            $files[] = $data;
        }
        return $files;
    }

    /**
     * 内容 替换
     *
     * @param $isFile
     * @param $source
     * @param $replaces
     *
     * @return string|string[]|null
     */
    protected function contentReplease($isFile, $source, $replaces)
    {
        if (empty($replaces)) {
            return '';
        }
        $str = [];
        $isAdd = false;
        foreach ($replaces as $replace) {
            $content = $replace['content'];
            if ($isFile) {
                $regex = sprintf('~%s([\s\S]*)%s~m', $replace['start'], $replace['end']);
                if (preg_match($regex, $source)) {
                    $content = substr($content, 3);
                    $source = preg_replace($regex, $content, $source);
                } else {
                    $content = "/**\n" . $content . "\n */";
                    $source = $source . "\n" . $content;
                }

            } else {
                $str[] = $this->fomateAnnotation($isFile, $content, $isAdd);
                $isAdd = true;
            }
        }
        return !empty($str) ? implode("\n", $str) : $source;
    }

    public function getDefinitionName()
    {
        $name = [];
        foreach ($this->definition as $definition) {
            $name[] = $definition['name'];
        }
        return $name;
    }

}
