<?php

namespace Varobj\XP;

use Phalcon\Di\DiInterface;
use Phalcon\Di\FactoryDefault;

class BaseBiz
{
    use Instance;

    protected $di;

    public function __construct(DiInterface $di = null)
    {
        $this->di = $di;
        !$this->di and $this->di = FactoryDefault::getDefault();
    }
}