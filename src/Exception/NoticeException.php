<?php

/**
 * 通用 Notice 异常
 */

namespace Varobj\XP\Exception;

class NoticeException extends BaseException
{
    protected $biz_code = 606;
    protected $message = '[custom exception]';
    protected $exception_type = 'warning';
}