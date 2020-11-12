<?php

/**
 * 配置定义错误
 */

namespace Varobj\XP\Exception;

class SystemConfigException extends BaseException
{
    public $biz_code = 608;
    public $message = '[config error]';
}