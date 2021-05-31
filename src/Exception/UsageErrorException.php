<?php

namespace Varobj\XP\Exception;

class UsageErrorException extends BaseException
{
    public $biz_code = 700;
    public $message = '[usage error]';
    protected $exception_type = 'warning';
}