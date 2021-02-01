<?php

namespace Varobj\XP\Controller;

use Phalcon\Config;
use Phalcon\Mvc\View\Simple;
use Varobj\XP\BaseController;

/**
 * Class DocsController
 *
 * 使用前需要引入
 *
 * composer require zircote/swagger-php:^3.0
 *
 * 复制 DocsController Docs\ApiController.php src\Docs 目录到项目中
 *
 * @property Config $config;
 * @package MApi\Library\Docs\Controllers
 */
class DocsController extends BaseController
{
    /**
     * @return string
     */
    public function getAction(): string
    {
        $view = new Simple();
        return $view->render(
            __DIR__ . '/../Docs/views/docs',
            [
                'url' => 'docs/api',
            ]
        );
    }
}