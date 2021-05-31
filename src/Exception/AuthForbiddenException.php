<?php

namespace Varobj\XP\Exception;

class AuthForbiddenException extends BaseException
{
    public $biz_code = 602;
    public $message = '[auth forbidden]';
}