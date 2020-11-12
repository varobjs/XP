<?php

namespace Varobj\XP;

use Phalcon\Config;
use Phalcon\Mvc\Controller;

/**
 * Class BaseController
 * @property Config $config
 * @package Varobj\XP
 */
class BaseController extends Controller
{
    public function optionsAction(): string
    {
        return '';
    }
}