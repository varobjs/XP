<?php
/**
 * 通用错误异常
 */

namespace Varobj\XP\Exception;

class ErrorException extends BaseException
{
    public $biz_code = 604;
    public $message = '[custom error]';
}