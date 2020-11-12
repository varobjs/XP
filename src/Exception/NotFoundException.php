<?php

namespace Varobj\XP\Exception;

class NotFoundException extends BaseException
{
    public $biz_code = 605;
    public $message = '[not found]';
    protected $exception_type = 'warning';
}