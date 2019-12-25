<?php

declare(strict_types=1);

namespace Lengbin\Hyperf\Swagger;

use Hyperf\Cache\Driver\FileSystemDriver;
use Hyperf\Contract\ConfigInterface;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Exception\Http\NotFoundException;
use Hyperf\Logger\Exception\InvalidConfigException;
use Hyperf\Utils\Codec\Json;
use Hyperf\Utils\Context;
use Hyperf\Utils\Str;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * $swagger = new Swagger();
 *
 * // show swagger ui html
 * $swagger->html();
 *
 * // scan dir get api
 * $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'swagger';
 * $apiArray = $swagger->api([$path]);
 *
 * Class Swagger
 * @package Lengbin\Hyperf\Swagger
 */
class Swagger
{
    const SWAGGER_VERSION_TWO = 2;
    const SWAGGER_VERSION_DEFAULT = 3;
    const SWAGGER_CACHE = 'swagger';
    const SWAGGER_CACHE_PATH = 'swagger_path_';
    const SWAGGER_CACHE_DEFINITION = 'swagger_definition_';

    protected $config;
    protected $path;
    protected $container;

    public function __construct(ContainerInterface $container, ConfigInterface $config)
    {
        $this->container = $container;

        $swagger = $config->get('swagger');
        if ($swagger === null) {
            throw new InvalidConfigException('Please Config Swagger Params');
        }

        $path = $config->get('server.settings.document_root');
        if ($path === null) {
            throw new InvalidConfigException('Please Setting Server Setting document_root');
        }

        $this->config = $this->init($swagger);

        $staticDir = $config->get('server.settings.static_handler_locations', []);
        if (in_array('/', $staticDir) || in_array($this->config['path'], $swagger)) {
            $this->path = $path . $this->config['path'];
        } else {
            throw new InvalidConfigException('Please Setting Server Setting static_handler_locations, eg: "/" or "/swagger"');
        }
    }

    /**
     * 验证配置文件字段
     *
     * @param $swagger
     *
     * @return array
     */
    protected function init($swagger): array
    {
        $fields = [
            'path',
            'filePath',
            'url',
            'version',
            'oauthConfiguration',
        ];
        foreach ($fields as $field) {
            if (!isset($swagger[$field])) {
                $swagger[$field] = null;
            }
        }
        if (empty($swagger['url'])) {
            throw new InvalidConfigException('Please Setting api url');
        }

        if ($swagger['version'] === null) {
            $swagger['version'] = self::SWAGGER_VERSION_DEFAULT;
        }
        $swagger['version'] = (int)$swagger['version'];

        if (empty($swagger['path'])) {
            $swagger['path'] = '/swagger';
        }
        if (strncmp($swagger['path'], '/', 1) !== 0) {
            $swagger['path'] = '/' . $swagger['path'];
        }

        if (empty($swagger['filePath'])) {
            $swagger['path'] = BASE_PATH . '/runtime/swagger';
        }

        if (!empty($swagger['version']) && !in_array($swagger['version'], [self::SWAGGER_VERSION_DEFAULT, self::SWAGGER_VERSION_TWO])) {
            throw new InvalidConfigException('Swagger version support 1, 2, 3');
        }

        if (empty($swagger['oauthConfiguration'])) {
            $swagger['oauthConfiguration'] = Json::encode([
                'clientId'                    => 'your-client-id',
                'clientSecret'                => 'your-client-secret-if-required',
                'realm'                       => 'your-realms',
                'appName'                     => 'your-app-name',
                'scopeSeparator'              => ' ',
                'additionalQueryStringParams' => [],
            ]);
        }
        return $swagger;
    }

    /**
     * path exists
     *
     * @param $path
     *
     * @return bool
     */
    protected function pathExists($path): string
    {
        $pathInfo = pathinfo($path . '/tmp.txt');
        if (!empty($pathInfo['dirname'])) {
            if (file_exists($pathInfo['dirname']) === false) {
                if (@mkdir($pathInfo['dirname'], 0777, true) === false) {
                    return '';
                }
            }
        }
        return $path;
    }

    /**
     * 复制文件夹/文件
     *
     * @param string  $src        path
     * @param string  $dst        cp path
     * @param array   $filterDir  filter path
     * @param array   $filterFile filter file
     * @param boolean $isUnlink   is delete
     *
     * @author lengbin(lengbin0@gmail.com)
     */
    protected function copyDir($src, $dst, array $filterDir = [], array $filterFile = [], $isUnlink = false): void
    {
        $dir = opendir($src);
        $this->pathExists($dst);
        while (false !== ($file = readdir($dir))) {
            if (($file !== '.') && ($file !== '..') && !in_array($file, $filterDir)) {
                if (is_dir($src . "/" . $file)) {
                    $this->copyDir($src . "/" . $file, $dst . "/" . $file, $filterDir, $filterFile, $isUnlink);
                } else {
                    if (!in_array($file, $filterFile)) {
                        @copy($src . "/" . $file, $dst . "/" . $file);
                        if ($isUnlink) {
                            @unlink($src . "/" . $file);
                        }
                    }
                }
            }
        }
        closedir($dir);
    }

    /**
     * 生成静态文件
     *
     * @param $type
     *
     * @return string
     */
    protected function generatorStaticFiles($type): string
    {
        $paths = [
            self::SWAGGER_VERSION_TWO     => '/theme/two',
            self::SWAGGER_VERSION_DEFAULT => '/theme/three',
        ];
        $lock = $this->path . $paths[$type] . '/.lock';
        $link = $this->config['path'] . $paths[$type];
        if (is_file($lock)) {
            return $link;
        }
        $this->copyDir(__DIR__ . $paths[$type], $this->path . $paths[$type]);
        file_put_contents($lock, '1');
        return $link;
    }

    /**
     * swagger ui html
     *
     * @return ResponseInterface
     */
    public function html(): ResponseInterface
    {
        $link = $this->generatorStaticFiles($this->config['version']);
        $view = __DIR__ . '/view/swagger' . $this->config['version'] . '.php';
        $template = new Template(file_get_contents($view));
        $template->setVars([
            'url'         => $this->config['url'],
            'link'        => $link,
            'oauthConfig' => $this->config['oauthConfiguration'],
        ]);
        $content = $template->produce();
        return Context::get(ResponseInterface::class)
            ->withStatus(200)
            ->withAddedHeader('content-type', 'text/html; charset=utf-8')
            ->withBody(new SwooleStream($content));
    }

    /**
     * 生成注释文件
     *
     * @return string
     */
    protected function generatorAnnotationFiles(): void
    {
        $lock = $this->config['filePath'] . '/.lock';
        if (is_file($lock)) {
            return;
        }
        $this->copyDir(__DIR__ . '/annocation', $this->config['filePath']);
        file_put_contents($lock, '1');
    }

    /**
     * swagger api
     *
     * @param array $scanDir scan dir path
     *
     * @return \Swagger\Annotations\Swagger
     */
    public function api(array $scanDir): \Swagger\Annotations\Swagger
    {
        if (!in_array($this->config['filePath'], $scanDir)) {
            $scanDir[] = $this->config['filePath'];
        }
        $this->generatorAnnotationFiles();
        return \Swagger\scan($scanDir);
    }

    /**
     * Check Generator Config And Assigin Default Value
     *
     * @param string|null $path
     *
     * @return array|mixed
     */
    protected function checkGeneratorConfig($path = null): array
    {
        $generatorConfig = !empty($this->config['generator']) ? $this->config['generator'] : [];

        $generatorConfig['filePath'] = $this->config['filePath'];

        if (!empty($path)) {
            $generatorConfig['filePath'] = $path;
        }

        if (empty($generatorConfig['httpMethods'])) {
            $generatorConfig['httpMethods'] = [
                "GET",
                "POST",
                "DELETE",
                "PUT",
                "PATCH",
                "OPTIONS",
                "HEAD",
                "CONNECT",
                "TRACE",
            ];
        }

        if (empty($generatorConfig['contentTypes'])) {
            $generatorConfig['contentTypes'] = [
                "application/json",
                "application/xml",
                "application/x-www-form-urlencoded",
                "multipart/form-data",
                "text/html",
            ];
        }

        if (empty($generatorConfig['parameterIns'])) {
            $generatorConfig['parameterIns'] = ["formData", "path", "query", "header", "body"];
        }

        if (empty($generatorConfig['parameterTypes'])) {
            $generatorConfig['parameterTypes'] = ["string", "number", "integer", "boolean", "file"];
        }

        if (empty($generatorConfig['definitionTypes'])) {
            $generatorConfig['definitionTypes'] = [
                "string",
                "number",
                "integer",
                "boolean",
                "array",
                "object",
            ];
        }

        if (!isset($generatorConfig['default']['openResponseTemplate'])) {
            $generatorConfig['default']['openResponseTemplate'] = true;
        }

        if (empty($generatorConfig['default']['responseTemplate'])) {
            $generatorConfig['default']['responseTemplate'] = [
                'code'    => 0,
                'message' => 'Success',
                'data'    => '{{replace}}',
            ];
        }

        if (empty($generatorConfig['default']['parameters'])) {
            $generatorConfig['default']['parameters'] = [];
        }

        if (empty($generatorConfig['default']['responses'])) {
            $generatorConfig['default']['responses'] = [];
        }

        if (empty($generatorConfig['default']['definitionTemplate'])) {
            $generatorConfig['default']['definitionTemplate'] = ['default' => []];
        }

        return $generatorConfig;
    }

    /**
     * cache
     * @return CacheInterface
     */
    protected function getCache(): CacheInterface
    {
        return make(FileSystemDriver::class, [
            'container' => $this->container,
            'config'    => [
                'prefix' => 'swagger:',
            ],
        ]);
    }

    /**
     *  get annocation by request
     *
     * @param RequestInterface $request
     * @param array            $config
     * @param bool             $isSave
     *
     * @return array
     */
    protected function generatorByRequest(RequestInterface $request, array $config, $isSave = false): array
    {
        if (!$request->isMethod('post')) {
            throw new NotFoundException("Not Found Page", 404);
        }
        $data = $request->inputs([
            'filePath',
            'path',
            'method',
            'tags',
            'security',
            'deprecated',
            'summary',
            'description',
            'consumes',
            'produces',
            'parameter',
            "openResponseTemplate",
            'response',
            'definition',
        ]);
        $data['responseTemplate'] = $config['default']['responseTemplate'];
        $generator = new Generator($data);
        $annotation = new SwaggerAnnotation($generator);
        if (!$isSave) {
            return $annotation->getAnnotation();
        }
        $result = [
            'message' => [],
            'error'   => [],
        ];
        $filePath = $this->pathExists($generator->filePath);
        if (!$filePath) {
            $result['error'][] = 'Unable to create the directory ' . $generator->filePath;
        }
        foreach ($annotation->getAnnotation(true) as $item) {
            $result['message'][] = $item['file'];
            if (@file_put_contents($item['fullFule'], $item['newStr']) === false) {
                $result['error'][] = "Unable to write the file '{$item['fullFule']}'";
            }
        }
        // cache
        $cache = $this->getCache();
        $path = $cache->get(self::SWAGGER_CACHE_PATH, []);
        $definition = $cache->get(self::SWAGGER_CACHE_DEFINITION, []);
        if (!in_array($generator->path, $path)) {
            $path[] = $generator->path;
        }
        foreach ($annotation->getDefinitionName() as $name) {
            $definition[$name] = $generator->path;
        }

        $cache->setMultiple([
            self::SWAGGER_CACHE_PATH                  => $path,
            self::SWAGGER_CACHE_DEFINITION            => $definition,
            $this->getCachePathName($generator->path) => $generator,
        ]);
        return $result;
    }

    /**
     * cache path name
     *
     * @param $path
     *
     * @return string
     */
    protected function getCachePathName($path)
    {
        return self::SWAGGER_CACHE . str_replace('/', '_', $path) . '_';
    }

    /**
     * swagger generator annotation
     *
     * @param RequestInterface $request
     * @param null|string      $path
     *
     * @return array|mixed
     */
    public function annotation(RequestInterface $request, $path = null): array
    {
        $data = [];
        $config = $this->checkGeneratorConfig($path);
        $t = $request->input('t');
        switch ($t) {
            case "preview":
                $data = $this->generatorByRequest($request, $config);
                break;
            case "generator":
                $result = $this->generatorByRequest($request, $config, true);
                $data = [
                    'message' => "文件生成：" . implode("\n", $result['message']),
                    'error'   => implode("<br>", $result['error']),
                ];
                break;
            case "path":
                $cache = $this->getCache();
                $cachePachs = $cache->get(self::SWAGGER_CACHE_PATH, []);
                $p = $request->input('p');
                $swagger = [];
                $isPath = in_array($p, $cachePachs);
                if ($isPath) {
                    $swagger = $cache->get($this->getCachePathName($p), []);
                }
                $data = [
                    'has'  => $isPath,
                    'form' => $swagger,
                ];
                break;
            case "definition":
                $cache = $this->getCache();
                $cacheDefinitions = $cache->get(self::SWAGGER_CACHE_DEFINITION, []);
                $p = $request->input('p');
                $n = $request->input('n');
                $status = false;
                if (isset($cacheDefinitions[$n]) && $cacheDefinitions[$n] !== $p) {
                    $status = true;
                }
                $data = [
                    'has' => $status,
                ];
                break;
            default:
                $config['path'] = $this->getCache()->get(self::SWAGGER_CACHE_PATH, []);
                $data = $config;
                break;
        }

        return $data;
    }

    /**
     * 生成静态文件
     *
     * @param $type
     *
     * @return string
     */
    protected function redirect($url)
    {
        return Context::get(ResponseInterface::class)->withStatus(302)->withAddedHeader('Location', $url);
    }

    /**
     * swagger generator
     *
     * @return ResponseInterface
     */
    public function generator(RequestInterface $request)
    {
        $link = $this->path . '/generator';
        $lock = $link . '/.lock';
        $uri = $request->getUri();
        $http = $uri->getScheme() . '://' . $uri->getAuthority();
        $url = $http . $this->config['path'] . '/generator/index.html';
        if (is_file($lock)) {
            return $this->redirect($url);
        }
        $this->copyDir(__DIR__ . '/generator', $link);
        file_put_contents($lock, '1');
        return $this->redirect($url);
    }

}
