<?php

namespace Varobj\XP\Exception;

class DatabaseException extends BaseException
{
    public $biz_code = 603;
    public $message = '[db error]';
}