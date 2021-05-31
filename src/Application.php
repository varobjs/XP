<?php

namespace Varobj\XP;

use Phalcon\Config;
use Phalcon\Di\DiInterface;
use Phalcon\Mvc\Micro;
use Varobj\XP\Exception\NotFoundException;
use Varobj\XP\Exception\UsageErrorException;
use function Clue\StreamFilter\fun;

/**
 * Class Application
 * @property Config $config
 * @property XLogger $logger
 * @package Varobj\XP
 */
class Application extends Micro
{
    private $_defaultController = 'IndexController';
    private $_controllerClass = '';
    private $_controllerMethod = '';
    protected $_namespaces = [
        'Varobj\XP\\Controllers\\'
    ];

    private $_alias = [];

    public function __construct(DiInterface $container = null)
    {
        parent::__construct($container);
        !is_prod() and ErrorHandler::$turn_undefined_index_to_exception = true;
    }

    public function addNamespace($namespace): void
    {
        array_unshift($this->_namespaces, $namespace);
    }

    public function start(): void
    {
        $url = parse_url($_SERVER['REQUEST_URI'] ?? '/');
        $url['path'] === '' && $url['path'] = '/';
        $parts = explode('/', $url['path'] ?: '/');

        // 去掉前后空字符串
        if ($parts[0] === '') {
            array_shift($parts);
        }
        $count = count($parts);
        if ($parts[$count - 1] === '') {
            array_pop($parts);
        }
        if (empty($parts)) {
            $controller = $this->_defaultController;
        } else {
            $controller = implode('\\', array_map('ucfirst', $parts)) . 'Controller';
            $controller = implode('', array_map('ucfirst', explode('-', $controller)));
        }

        $isAlias = false;
        if (isset($this->_alias[$url['path']])) {
            $isAlias = true;
            $this->_namespaces = [$this->_alias[$url['path']]['controller']];
            $method = $this->_alias[$url['path']]['method'];
            $this->_controllerMethod = strtoupper($method) . 'Action';
        } else {
            $this->_controllerMethod = strtolower($this->request->getMethod()) . 'Action';
            $this->_namespaces = array_map(static function ($value) use ($controller) {
                return rtrim($value, '\\\\') . '\\' . $controller;
            }, $this->_namespaces);
        }

        $notfound = true;
        foreach ($this->_namespaces as $item) {
            if (!class_exists($item)) {
                continue;
            }
            $class = new $item();
            if (!method_exists($class, $this->_controllerMethod)) {
                continue;
            }
            $class->setDI($this->getDI());
            is_callable([$class, 'initialize']) and $class->initialize();

            $l = strlen($url['path']);

            if ($url['path'][$l - 1] === '/') {
                $url['path'] = substr($url['path'], 0, $l - 1);
            }

            $_path = '/' . ltrim($url['path'], '/');
            $_handler = [$class, $this->_controllerMethod];
            switch (strtolower($this->request->getMethod())) {
                case 'get':
                    $this->get($_path, $_handler);
                    break;
                case 'post':
                    $this->post($_path, $_handler);
                    break;
                case 'put':
                    $this->put($_path, $_handler);
                    break;
                case 'delete':
                    $this->delete($_path, $_handler);
                    break;
                case 'options':
                    $this->options($_path, $_handler);
                    break;
                default:
                    throw new UsageErrorException('暂时不支持的方法[' . $this->request->getMethod());
            }
            $notfound = false;
            $this->_controllerClass = $item;
            $this->handle('/' . ltrim($url['path'], '/'));
            break;
        }

        if ($notfound && $this->_controllerMethod === 'options') {
            $this->handle('/' . ltrim($url['path'], '/'));
        } elseif ($notfound) {
            $_action = $this->_controllerMethod;
            $controller_arr = array_map(
                static function ($v) use ($_action) {
                    return $v . '@' . $_action;
                },
                $this->_namespaces
            );
            throw (new NotFoundException('uri not found'))
                ->debug(
                    [
                        'try_classes' => $controller_arr
                    ]
                );
        }
    }

    public function getClass(): string
    {
        return $this->_controllerClass;
    }

    public function getMethod(): string
    {
        return $this->_controllerMethod;
    }

    /**
     * @param string $url
     * @param string $controllerClass
     * @param string $method
     */
    public function addAlias(string $url, string $controllerClass, string $method = 'get'): void
    {
        $this->_alias[$url] = [
            'controller' => $controllerClass,
            'method' => $method
        ];
    }
}