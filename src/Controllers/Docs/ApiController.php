<?php

namespace Varobj\XP\Controllers\Docs;

use Phalcon\Config;
use Varobj\XP\BaseController;

use function OpenApi\scan;

/**
 * Class DocsController
 *
 * 使用前需要引入
 *
 * composer require zircote/swagger-php:^3.0
 *
 * @property Config $config;
 * @package MApi\Library\Docs\Controllers
 */
class ApiController extends BaseController
{
    /**
     * @all_LoadCacheMiddleware(expired=300)
     * @return string
     */
    public function getAction(): string
    {
        $swagger = scan(APP_PATH);

        return $swagger->toJson();
    }
}