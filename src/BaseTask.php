<?php

namespace Varobj\XP;

use Phalcon\Cli\Console;

class BaseTask extends Console
{
    public $help_text;
    use CommonJobTrait;
}