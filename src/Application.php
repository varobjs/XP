<?php

namespace Varobj\XP;

use Phalcon\Config;
use Phalcon\Mvc\Micro;
use Varobj\XP\Exception\NotFoundException;
use Varobj\XP\Exception\UsageErrorException;

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

    private $_alias = [];

    public function start(): void
    {
        $url = parse_url($_SERVER['REQUEST_URI'] ?? '/');
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

        if (isset($this->_alias[$url['path']])) {
            $controller_arr = [$this->_alias[$url['path']]['controller']];
            $method = $this->_alias[$url['path']]['method'];
            $this->_controllerMethod = strtoupper($method) . 'Action';
        } else {
            $this->_controllerMethod = strtolower($this->request->getMethod()) . 'Action';
            $appName = defined('APP_NAME') ? APP_NAME : '';
            $controller_arr = [
                ($appName ? ($appName . '\\Controller\\') : '') . $controller,
                'Varobj\XP\\Controller\\' . $controller
            ];
        }

        $nofound = true;
        foreach ($controller_arr as $item) {
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
            $nofound = false;
            $this->_controllerClass = $item;
            $this->handle('/' . ltrim($url['path'], '/'));
            break;
        }

        if ($nofound && $this->_controllerMethod === 'options') {
            $this->handle('/' . ltrim($url['path'], '/'));
        } elseif ($nofound) {
            $_action = $this->_controllerMethod;
            $controller_arr = array_map(
                function ($v) use ($_action) {
                    return $v . '@' . $_action;
                },
                $controller_arr
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

    public function addAlias(string $url, string $controllerClass, $method = 'get'): void
    {
        $this->_alias[$url] = [
            'controller' => $controllerClass,
            'method' => $method
        ];
    }
}