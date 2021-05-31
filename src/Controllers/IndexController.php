<?php

namespace Varobj\XP\Controllers;

use Varobj\XP\BaseController;

class IndexController extends BaseController
{
    public function getAction(): string
    {
        return '<h3>Welcome use X-Phalcon api</h3>';
    }
}