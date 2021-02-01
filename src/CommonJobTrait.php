<?php

namespace Varobj\XP;

use Exception;
use Varobj\XP\Exception\ErrorException;
use Varobj\XP\Service\UserService;
use Varobj\XP\Struct\BizResult;

trait CommonJobTrait
{
    /**
     * @var XLogger $log
     */
    protected $log;
    protected $log_file = '';
    protected $verbose = false;
    protected $verbose_plus = false;
    // debug 模式会记录真实的内存使用情况（关闭GC）
    protected $debug = false;
    // 唯一请求ID，默认从 Application::getReqId 继承
    protected $req_id = '';
    // 当前进程编号，从 1 ~ ${process}，0 为 master 进程
    protected $process = 0;
    // --mp 参数指定的进程数，只能是 1 ~ getProcesses() 之间
    protected $mp = 0;
    // 当前运行模式是否是多进程运行
    public $is_mp = false;
    // 全局控制最大容许进程数量
    protected $max_allow_process = 20;
    // 进程ID
    protected $signal = [];
    // 进程加锁名称，可根据参数自定义
    public $lockName;
    // 单次执行最大时间，也是 lock 的默认过期时间(秒)
    public $lockTime = 600;
    protected $lockValue;
    // 当前脚本执行的错误信息；-1  初始化，0 没有错误， 1 有错误
    protected $error = -1;
    protected $error_msg = '';

    protected $common_options = <<<EOL
-----------------------------------------

Usage: 

    \33[1;38;2;255;0;255m php job.php xxx [options]\33[0m

-----------------------------------------
[options] like "-k1=v1 -k2=v2"
-----------------------------------------

Common options:
    -v|--v          verbose
    -d|--d          debug mode, will disable gc , output memory usage, etc..
    -gc|--gc        gc by handle
    -mt|--mt[=n]    multi threads, --mt=nums  (nums > 1) special thread numbers
    -h|--h          help

> eg: php job.php TestJob -k1=v1 -k2=v2

:)👌
-----------------------------------------
EOL;

    /**
     * CommonJobTrait constructor.
     * @throws Exception
     */
    final public function initialize(): void
    {
        if (!extension_loaded('pcntl')) {
            throw new ErrorException(__METHOD__ . ' need ‘pcntl’ extension');
        }
        pcntl_signal(SIGINT, [$this, 'signal_quit_handler'], false);
        global $jobParams;
        $this->verbose_plus = isset($jobParams['vv']);
        if ($this->verbose_plus) {
            $this->verbose = true;
        } else {
            $this->verbose = isset($jobParams['v']);
        }
        global $_VERBOSE;
        $_VERBOSE = $this->verbose;
        $this->debug = isset($jobParams['d']);
        if ($this->debug) {
            gc_disable();
        }
        $this->log = XLog();
        $this->req_id = XString::default($jobParams, 'req_id', UserService::getRequestID());
        if (isset($jobParams['h'])) {
            $help = $this->helpText() ?: '脚本说明';
            $help .= PHP_EOL . $this->common_options . PHP_EOL;
            die($help);
        }
        // 默认的 lockName 为脚本 class 名称
        null === $this->lockName and $this->lockName = str_replace('\\', '_', static::class);

        if (is_callable([$this, 'init'])) {
            $this->init($jobParams);
        }

        $support_multi_process = isset($jobParams['mp']);
        if ($support_multi_process) {
            if ($this->getProcesses() > $this->max_allow_process) {
                $this->echoAndLog('进程数不能超过 ' . $this->max_allow_process, 'error');
                exit;
            }
            if ($jobParams['mp'] !== '') {
                $this->mp = (int)$jobParams['mp'];
                if ($this->mp < 1 || $this->mp > $this->getProcesses()) {
                    $this->echoAndLog('--mp 指定进程数必须在 1 ~ ' . $this->getProcesses() . ' 之间');
                    exit;
                }
            }
        } elseif ($this->getProcesses() > 1) {
            $this->echoAndLog(
                '请开启多进程运行[--mp], 当前最多支持 ' . $this->getProcesses() . ' 进程',
                'error'
            );
            exit;
        }

        // 统一加 redis 锁
        if ($support_multi_process && $this->getProcesses() > 1) {
            // 开启多进程模式
            $this->is_mp = true;
            $this->reConnectionMySQL(['db', 'db_read', 'db_write']);
            $this->multiProcesses();
        } else {
            $this->lock($this->lockName);
        }
    }

    public function reConnectionMySQL(array $dbs = []): void
    {
        foreach ($dbs as $db) {
            if ($this->di->has($db) && $this->di->getService($db)->isShared()) {
                $db_service = $this->di->getService($db);
                $this->di->remove($db);
                $this->di->setShared($db, $db_service->getDefinition());
            }
        }
        if ($this->di->has('db_read') && $this->di->getService('db_read')->isShared()) {
            $db = $this->di->getService('db_read');
            $this->di->remove('db_read');
            $this->di->setShared('db_read', $db->getDefinition());
        }
        if ($this->di->has('db_write') && $this->di->getService('db_write')->isShared()) {
            $db = $this->di->getService('db_write');
            $this->di->remove('db_write');
            $this->di->setShared('db_write', $db->getDefinition());
        }
    }

    public function __destruct()
    {
        $this->_unlock();
        if ($this->error === 0) {
            $this->echoAndLog('成功', 'success', 1, '执行结果: ');
        } elseif ($this->error === 1) {
            $this->echoAndLog('失败', 'error', 1, '执行结果: ');
            $this->echoAndLog('失败信息: ');
            $this->echoAndLog($this->error_msg, 'error');
        }
    }

    public function signal_quit_handler(int $signo = 0, $siginfo = null): void
    {
        if ($signo === 250) {
            return;
        }
        _verbose('signo=' . $signo . ', siginfo=' . json_encode($siginfo));
        $this->_unlock();
        $this->echoAndLog('接收到退出信号 signo=' . $signo . '，退出成功, pid=' . getmypid(), 'success');
        exit(250);
    }

    public function _unlock(): void
    {
        if ($this->lockValue) {
            if ($this->process) {
                $this->unlock($this->lockName . ':process:' . $this->process);
            } else {
                $this->unlock($this->lockName);
            }
        }
    }

    /**
     * @param string $lockName
     * @throws \Phalcon\Storage\Exception
     */
    public function lock(string $lockName): void
    {
        if (!$lockName) {
            return;
        }
        $redis = XRedis::getInstance(null, true);
        $this->lockValue = $redis->lock($lockName, $this->lockTime);
        if (false === $this->lockValue) {
            $this->echoAndLog(
                sprintf(
                    '无法添加进程锁[%s]%s',
                    $lockName,
                    $redis->redis->has($redis->getOriginKey($lockName))
                        ? 'res: 锁已经存在，ttl=' . $redis->predis->ttl($redis->getOriginKey($lockName))
                        : 'res: 原因未知'
                ),
                'error'
            );
            exit;
        }
    }

    public function unlock(string $lockName): void
    {
        $redis = XRedis::getInstance(null, true);
        $re = $redis->unLock($lockName, $this->lockValue);
        $this->lockValue = '';
        if (!$re) {
            $this->echoAndLog('开锁失败, lock_name=' . $lockName, 'error');
        }
    }

    /**
     * 子类重写，配置动态需要开启的进程个数
     * 当大于 1 时，必须通过 -mp 开启多进程支持才能运行
     * @return int
     */
    public function getProcesses(): int
    {
        return 0;
    }

    /**
     * 设置输出的日志路径，默认指定 xx/job/xx.log
     * @param string $re_conf_file
     * @return string
     */
    public function getLogFile(string $re_conf_file = ''): string
    {
        !empty($re_conf_file) and $this->log_file = $re_conf_file;
        if (!$this->log_file) {
            $tmp = strtolower(str_replace(['\\', '\-'], ['_', '_'], static::class));
            $this->log_file = rtrim($this->log->getLogPath(), '/') . '/' .
                $tmp . '_' . date('Ymd') . '.log';
        }
        return $this->log_file;
    }

    /**
     * 输出到终端（需要加上 -v），并记录响应的日志
     * @param string $message
     * @param string $type
     * @param int $position
     * @param string $_prefix
     * @return true
     */
    public function echoAndLog(
        string $message,
        string $type = 'info',
        int $position = 1,
        string $_prefix = ''
    ): bool {
        if (!$this->log) {
            return false;
        }
        $prefix = '[DT:' . date('H/i/s') . '][REQ:' . $this->req_id . ']';
        if ($this->getProcesses() > 1) {
            $prefix .= '[PID:' . getmypid() . '][MP:' . $this->process . '/' .
                $this->getProcesses() . ']';
        }
        if ($this->debug) {
            $prefix .= '[ME:';
            $mb = floor(memory_get_peak_usage() / 1024 / 1024);
            if ($mb > 0) {
                $prefix .= $mb . 'mb]';
            } else {
                $prefix .= floor(memory_get_peak_usage() / 1024) . 'kb]';
            }
            if (memory_get_usage() > 80 * 1024 * 1024) {
                gc_collect_cycles();
            }
        }
        if ($this->verbose) {
            $green = "\33[1;38;2;33;223;23m";
            $red = "\33[1;38;2;255;0;0m";
            $yellow = "\33[1;38;2;255;227;132m";
            $clear = "\33[0m";
            if ($type === 'error') {
                echo $prefix . ' ' . $_prefix . $red . $message . $clear . PHP_EOL;
            } elseif ($type === 'success') {
                echo $prefix . ' ' . $_prefix . $green . $message . $clear . PHP_EOL;
            } elseif ($type === 'warning') {
                echo $prefix . ' ' . $_prefix . $yellow . $message . $clear . PHP_EOL;
            } else {
                echo $prefix . ' ' . $_prefix . $message . PHP_EOL;
            }
        }
        $message = $prefix . '[UTY:' . $type . '] ' . $_prefix . $message;
        $this->log->other($message, 'DEBUG', $this->getLogFile(), '', $position);
        return true;
    }

    /**
     * 多进程支持
     * @throws Exception
     */
    public function multiProcesses(): void
    {
        if ($this->debug && $this->verbose) {
            $this->echoAndLog(
                '---> 开始 ' . $this->getProcesses() . '/' . ($this->mp ?: '-') . ' 进程<---'
            );
        }
        $st = microtime(true);
        for ($i = 0; $i < $this->getProcesses(); $i++) {
            $childPid = pcntl_fork();
            if ($childPid === 0) {
                // 子进程
                if ($this->mp > 0) {
                    $this->process = $this->mp;
                } else {
                    $this->process = $i + 1;
                }
                // 多进程锁
                $this->lock($this->lockName . ':process:' . $this->process);
                return;
            }

            if ($childPid < 0) {
                die('cannot fork thread');
            }
            $this->signal[$childPid] = $this->process;
            if ($this->mp > 0) {
                break;
            }
        }
        while (!empty($this->signal)) {
            $pid = pcntl_wait($status);
            if ($pid > 0) {
                unset($this->signal[$pid]);
            }
            usleep(80000);
        }
        XString::startElapsed($st);
        $this->echoAndLog('---> ' . XString::elapsedTime() . ' <---');
        // 退出汇总函数
        if (is_callable([$this, 'done'])) {
            $this->done();
        }
        exit;
    }

    /**
     * 处理 BizResult 对象，转换成 cli 方便输出的数组
     * @param BizResult $bizResult
     * @return array
     */
    public function parseBizResult(BizResult $bizResult): array
    {
        $_code = $bizResult->getCode();
        $_type = $_msg = '';

        $_code < 0 and $_type = 'warning' and $_msg = '初始化';
        $_code === 0 and $_type = 'success' and $_msg = $bizResult->getMsg() ?: '成功';
        $_code < 1000 and $_code > 0 and $_type = 'warning'
        and $_msg = $bizResult->getMsg() ?: '警告';
        $_code >= 1000 and $_type = 'error' and $_msg = $bizResult->getMsg() ?: '错误';

        return [
            'type' => $_type,
            'msg' => $_msg,
        ];
    }

    /**
     * 从 params 参数中提取日期参数，并判断日期的正确性
     * @param array $params
     * @param string $field
     * @param string $date_format
     * @param string $field_name
     * @param null $default
     * @return string
     */
    public function parseDate(
        array $params,
        string $field,
        string $date_format = 'Y-m-d',
        string $field_name = '',
        $default = null
    ): string {
        if (empty($params[$field])) {
            if (null !== $default) {
                return $default;
            }
            $this->error = 1;
            $this->error_msg = '请输入日期' . ($field_name ? '(' . $field_name . ')' : '');
            $this->echoAndLog($this->error_msg, 'warning');
            exit;
        }
        $date = date($date_format, strtotime($params[$field]));

        // 判断太早的日期
        if ($date <= date($date_format, strtotime('-2 year'))
            || $date > date($date_format, strtotime('+1 month'))) {
            $this->error = 1;
            $this->error_msg = '日期太早或者太晚，请确认是否输入正确「' . $date . '」';
            $this->echoAndLog($this->error_msg, 'warning');
            exit;
        }

        return $date;
    }

    public function helpText(): string
    {
        return '';
    }
}