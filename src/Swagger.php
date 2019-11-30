<?php

declare(strict_types=1);

namespace Lengbin\Hyperf\Swagger;

use Hyperf\Contract\ConfigInterface;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\Logger\Exception\InvalidConfigException;
use Hyperf\Utils\Codec\Json;
use Hyperf\Utils\Context;
use Psr\Http\Message\ResponseInterface;

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
    const SWAGGER_GENERATOR = 'generator';

    protected $config;
    protected $path;

    public function __construct(ConfigInterface $config)
    {
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

        if (!empty($swagger['version']) && !in_array($swagger['version'], [self::SWAGGER_VERSION_DEFAULT, self::SWAGGER_VERSION_TWO])) {
            throw new InvalidConfigException('Swagger version support 1, 2, 3');
        }

        if (empty($swagger['oauthConfiguration'])) {
            $swagger['oauthConfiguration'] = json_encode([
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
    public function pathExists($path)
    {
        $pathInfo = pathinfo($path . '/tmp.txt');
        if (!empty($pathInfo['dirname'])) {
            if (file_exists($pathInfo['dirname']) === false) {
                if (@mkdir($pathInfo['dirname'], 0777, true) === false) {
                    return false;
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
    public function copyDir($src, $dst, array $filterDir = [], array $filterFile = [], $isUnlink = false)
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
            self::SWAGGER_GENERATOR       => '/theme/generator',
        ];
        $lock = $this->path . $paths[$type] . '/install.lock';
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
     * swagger api
     *
     * @param array $scanDir  scan dir path
     *
     * @return \Swagger\Annotations\Swagger
     */
    public function api(array $scanDir)
    {
        return \Swagger\scan($scanDir);
    }

}