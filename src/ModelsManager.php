<?php

namespace Varobj\XP;

use Phalcon\Db\Adapter\AdapterInterface;
use Phalcon\Mvc\Model\Manager;
use Phalcon\Mvc\ModelInterface;
use Varobj\XP\Exception\UsageErrorException;

class ModelsManager extends Manager
{
    public function getReadConnection(ModelInterface $model): AdapterInterface
    {
        $service = $model->getReadConnectionService();
        // 默认 db 或者 model 自定义的 service, 如果不存在，使用 默认 db_read
        if ($this->getDI()->has($service)) {
            return $this->getDI()->get($service);
        }

        if (!$this->getDI()->has('db_read')) {
            throw new UsageErrorException('请设置默认的从库服务「db_read」');
        }
        return $this->getDI()->get('db_read');
    }

    public function getWriteConnection(ModelInterface $model): AdapterInterface
    {
        $service = $model->getWriteConnectionService();
        // 默认 db 或者 model 自定义的 service, 如果不存在，使用 默认 db_write
        if ($this->getDI()->has($service)) {
            return $this->getDI()->get($service);
        }

        if (!$this->getDI()->has('db_write')) {
            throw new UsageErrorException('请设置默认的主库服务「db_write」');
        }
        return $this->getDI()->get('db_write');
    }
}
