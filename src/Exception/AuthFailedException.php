<?php

namespace Varobj\XP\Exception;

class AuthFailedException extends BaseException
{
    public $biz_code = 601;
    public $message = '[auth error]';
}