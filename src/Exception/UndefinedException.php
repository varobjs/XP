<?php

namespace Varobj\XP\Exception;

class UndefinedException extends BaseException
{
    public $biz_code = 609;
    public $message = '[undefined index]';
    protected $exception_type = 'warning';
}