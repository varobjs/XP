<?php

/**
 * 参数异常
 */

namespace Varobj\XP\Exception;

class ParamsException extends BaseException
{
    public $biz_code = 607;
    public $message = '[invalid request]';
    protected $exception_type = 'warning';
}