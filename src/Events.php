<?php

namespace Varobj\XP;

use Phalcon\Annotations\Annotation;
use Phalcon\Db\Adapter\Pdo\Mysql;
use Phalcon\Di\DiInterface;
use Phalcon\Events\Event;
use Phalcon\Mvc\Micro\MiddlewareInterface;

class Events
{
    public $sql_divisor = 6000;

    public $sql_probability = 3000;

    protected $di;

    public function __construct(DiInterface $di)
    {
        $this->di = $di;
    }

    public function beforeHandleRoute(Event $event, Application $application, $data): bool
    {
        $application->response->setHeader(
            'Access-Control-Allow-Origin',
            is_dev() ? '*' : env('FFE_HOST')
        )->setHeader('Access-Control-Allow-Credentials', 'true');

        if ($application->request->getMethod() === 'OPTIONS') {
            $application->response->setHeader(
                'Access-Control-Allow-Methods',
                'OPTIONS,GET,POST,PUT,DELETE'
            )->setHeader(
                'Access-Control-Allow-Headers',
                'Content-Type,Access-Token'
            )->send();
            return false;
        }
        return true;
    }

    /**
     * 根据 annotations 解析中间件
     * 规则 before_ 开头的
     * @param Event $event
     * @param Application $application
     * @return bool
     */
    public function beforeExecuteRoute(Event $event, Application $application): bool
    {
        $annotations = $application->annotations->get($application->getClass());
        $classAnnotations = $annotations->getClassAnnotations();
        if ($classAnnotations) {
            $a_class = $classAnnotations->getAnnotations();
            $this->getMiddlewareAndExecute($application, $a_class, 'before', 'before');
        }

        $a_methods = $annotations->getMethodsAnnotations();
        if (isset($a_methods[$application->getMethod()])) {
            $a_method = $a_methods[$application->getMethod()]->getAnnotations();
            $this->getMiddlewareAndExecute($application, $a_method, 'before', 'before');
        }

        if ($classAnnotations) {
            $a_class = $classAnnotations->getAnnotations();
            $this->getMiddlewareAndExecute($application, $a_class, 'all', 'before');
        }
        if (isset($a_methods[$application->getMethod()])) {
            $a_method = $a_methods[$application->getMethod()]->getAnnotations();
            $this->getMiddlewareAndExecute($application, $a_method, 'all', 'before');
        }
        return true;
    }

    public function afterHandleRoute(Event $event, Application $application, $returnValue): bool
    {
        $annotations = $application->annotations->get($application->getClass());
        $classAnnotations = $annotations->getClassAnnotations();
        if ($classAnnotations) {
            $a_class = $classAnnotations->getAnnotations();
            $this->getMiddlewareAndExecute($application, $a_class, 'all', 'after', $returnValue);
        }

        $a_methods = $annotations->getMethodsAnnotations();
        if (isset($a_methods[$application->getMethod()])) {
            $a_method = $a_methods[$application->getMethod()]->getAnnotations();
            $this->getMiddlewareAndExecute($application, $a_method, 'all', 'after', $returnValue);
        }

        if ($classAnnotations) {
            $a_class = $classAnnotations->getAnnotations();
            $this->getMiddlewareAndExecute($application, $a_class, 'after', 'after');
        }
        if (isset($a_methods[$application->getMethod()])) {
            $a_method = $a_methods[$application->getMethod()]->getAnnotations();
            $this->getMiddlewareAndExecute($application, $a_method, 'after', 'after');
        }
        return true;
    }

    private function getMiddlewareAndExecute(
        Application $application,
        array $annotations,
        string $type = 'before',
        string $execute_type = 'before',
        $external_data = null
    ): bool {
        $middles = array_filter(
            $annotations,
            function ($v) use ($type) {
                /** @var Annotation $v */
                return strpos($v->getName(), $type . '_') === 0;
            }
        );
        foreach ($middles as $middle) {
            /** @var Annotation $middle */
            $className = ltrim(strstr($middle->getName(), '_'), '_');
            // 只支持单层命名空间
            $className = 'Varobj\XP\\Middleware\\' . $className;
            if (class_exists($className)) {
                $arguments = $middle->getArguments();
                $arguments['execute_type'] = $execute_type;
                $arguments['external_date'] = $external_data;
                $classInstance = new $className($arguments);
                if ($classInstance instanceof MiddlewareInterface) {
                    // 返回 false, 则中断
                    $ret = $classInstance->call($application);
                    if ($ret === false) {
                        return false;
                    }
                } else {
                    $application->logger->notice(
                        'middleware [' . $middle->getName() .
                        '] should impl Phalcon\Mvc\Micro\MiddlewareInterface'
                    );
                }
            }
        }
        return true;
    }

    public function beforeQuery(Event $event, Mysql $mysql): void
    {
        $host = $mysql->getDescriptor()['host'];
        $_last_host_id = strrev(strstr(strrev($host), '.', true));
        $sql = $mysql->getRealSQLStatement();

        $sql = str_replace(["\r\n", "\r", "\n"], [' ', ' ', ' '], $sql);
        $sql = preg_replace('/\s\s*/', ' ', $sql);
        global $db_query_times;
        $db_query_times[$_last_host_id . md5($sql)] = microtime(true);
    }

    public function afterQuery(Event $event, Mysql $mysql): void
    {
        /** @var XLogger $logger */
        $logger = $this->di->getShared('logger');

        $sql = $mysql->getSQLStatement();
        $sql = str_replace(["\r\n", "\r", "\n"], [' ', ' ', ' '], $sql);
        $sql = preg_replace('/\s\s*/', ' ', $sql);
        $host = $mysql->getDescriptor()['host'];
        $_last_host_id = strrev(strstr(strrev($host), '.', true));

        $exe_times = 0;
        global $db_query_times;
        if (!empty($db_query_times[$_last_host_id . md5($sql)])) {
            $exe_times = microtime(true) - $db_query_times[$_last_host_id . md5($sql)];
            unset($db_query_times[$_last_host_id . md5($sql)]);
        }

        if ($exe_times > 0 && ($exe_times > 0.4 || $this->isSQLLoger($sql))) {
            // 保存 db scheme 的 md5 和 rawSql
            $md5 = md5($sql);
            $vars = $mysql->getSqlVariables() ?: [];
            // 不能处理 ? 和 :key 混用的情况
            $type = strpos($sql, '?') !== false;
            foreach ($vars as $key => $var) {
                if (is_array($var)) {
                    $i = 0;
                    $c = count($var) - 1;
                    foreach ($var as $_var) {
                        is_string($_var) and $_var = "'$_var'";
                        if (strpos($_var, '?') !== false) {
                            $_var = str_replace('?', '__LS.YC__', $_var);
                        }
                        if ($type) {
                            $sql = preg_replace('/\?/', $_var, $sql, 1);
                        } elseif ($c === $i) {
                            $sql = str_replace(':' . $key . $i, $_var, $sql);
                        } else {
                            $sql = str_replace(':' . $key . $i . ',', $_var . ',', $sql);
                        }
                        $i++;
                    }
                } else {
                    is_string($var) and $var = "'$var'";
                    if (strpos($var, '?') !== false) {
                        $var = str_replace('?', '__LS.YC__', $var);
                    }
                    if ($type) {
                        $sql = preg_replace('/\?/', $var, $sql, 1);
                    } else {
                        $sql = str_replace(':' . $key, $var, $sql);
                    }
                }
            }
            $sql = str_replace('__LS.YC__', '?', $sql);
            $logger->other(
                $md5 . ' | ' . $sql,
                'INFO',
                'sql_' . $_last_host_id . '.log',
                sprintf(
                    "[%ss]",
                    sprintf(
                        '%.3f',
                        $exe_times
                    )
                ),
                -2
            );
        }
    }

    /**
     * 线上模式使用，判断当前条件是否记录日志
     * 基于 Redis
     * 没个 sql mode 每天最少记录一次，其他情况
     * @param string $sql
     * @return bool
     */
    protected function isSQLLoger(string $sql): bool
    {
        if (!is_prod()) {
            return true;
        }
        try {
            $predis = XRedis::getInstance()->predis;
            $_md5 = md5($sql);
            $exist = $predis->hExists('SQL:' . date('Ymd'), $_md5);
            $predis->hIncrBy('SQL:' . date('Ymd'), $_md5, 1);
            return !$exist;
        } catch (\Throwable $e) {
            return random_int($this->sql_probability, $this->sql_divisor) === 3306;
        }
    }
}