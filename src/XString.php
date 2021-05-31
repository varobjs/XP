<?php

namespace Varobj\XP;

use Varobj\XP\Exception\UsageErrorException;

class XString
{
    /**
     * 用来测试性能，(格式化到分钟) 使用方法
     * StringTool::startElapsed();
     * ...
     * echo StringTool::elapsedTime();
     */
    protected static $start_elapsed_time = [];
    protected static $request_id = '';

    public static function startElapsed($ms = ''): bool
    {
        $_t = $ms ?: microtime(true);
        self::$start_elapsed_time[] = $_t;
        return true;
    }

    /**
     * 返回时间
     * 当 $retIsArr = true 返回
     * ```
     * [
     *  't_ms' => 0.00, // 总毫秒数
     * 't_s' => 0.00, // 总秒数
     * 't_m' => 0.00, // 总分钟
     * 'ms' => 0.00, // 不足秒的毫秒数
     * 's' => 0, // 不足分钟的秒数
     * 'm' => 0, // 整数分钟数
     * 'desc' => 'xx 分 xy 秒 xz 毫秒', // 描述
     * ]
     * ```
     * 否则返回 ` xx 分 xy 秒 xz 毫秒 `
     * @param bool $retIsArr
     * @return array|string
     */
    public static function elapsedTime(bool $retIsArr = false)
    {
        $ret = [
            't_m' => '0.00', // 总分钟
            't_s' => '0.00', // 总秒数
            't_ms' => '0.00', // 总毫秒数
            'ms' => '0.00', // 不足秒的毫秒数
            'm' => 0, // 整数分钟数,
            's' => 0, // 不足分钟的秒数
            'desc' => '', // 描述
        ];
        $_t = array_pop(self::$start_elapsed_time);
        $t = microtime(true) - $_t;

        $ret['t_ms'] = sprintf('%.2f', $t * 1000);
        $ret['t_s'] = sprintf('%.2f', $t);
        $ret['t_m'] = sprintf('%.2f', $t / 60);
        if ($t > 60) {
            $ret['m'] = floor($t / 60);
            $t -= ($ret['m'] * 60);
        }
        $ret['s'] = (int)$t;
        $str = $ret['m'] > 0 ? ($ret['m'] . ' min,') : '';
        $str .= ' ' . sprintf('%.3f', $t) . ' sec';
        $ret['desc'] = trim($str);
        if ($retIsArr) {
            return $ret;
        }

        return $ret['desc'];
    }

    /**
     * 数组 $value 如果存在返回；如果不存在 $key， 或者值为空('')，返回 $default
     * @param array $value
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function default(array $value, string $key, $default)
    {
        return ($value[$key] ?? $default) ?: $default;
    }

    /**
     * 批量插入数据库工具方法，把二维数组拼接成可执行的 SQL
     * $data = [
     *  ['name' => 'php', 'start' => '2013', 'id' => 1],
     *  ['name' => 'js', 'start' => '2014', 'id' => 2],
     *  ['name' => 'mysql', 'start' => '2014', 'id' => 3]
     * ];
     * $table = 'code_life';
     * $exclude_keys = ['id'];
     *
     * $batch = XString::batchInsert($table, $data, $exclude_keys, 10);
     *
     * $connection = $this->di->getShared('db');
     * foreach ($batch as $item) {
     *    $connection->execute($item['sql'], $item['bind']);
     * }
     *
     * @param string $table
     * @param array $data
     * @param array $exclude_keys
     * @param int $batchSize
     * @return array
     */
    public static function batchInsert(
        string $table,
        array $data,
        array $exclude_keys = [],
        int $batchSize = 100
    ): array {
        $sql = 'insert into `' . $table . '`(';
        $data0 = $data[0] ?? [];
        if (empty($data0) || !is_array($data0)) {
            throw new UsageErrorException('data必须为二维数据');
        }
        foreach ($data0 as $key => $value) {
            if (in_array($key, $exclude_keys, true)) {
                continue;
            }
            $sql .= '`' . $key . '`,';
        }
        $sql = trim($sql, ',') . ')';

        $data = count($data) > $batchSize ? array_chunk($data, $batchSize) : [$data];
        $ret = [];
        foreach ($data as $datum) {
            $_ret = [
                'sql' => $sql . ' values',
                'bind' => []
            ];
            $_ret['bind'] = [];
            foreach ($datum as $item) {
                $_ret['sql'] .= '(';
                foreach ($item as $k => $v) {
                    if (in_array($k, $exclude_keys, true)) {
                        continue;
                    }
                    if ($v === null) {
                        $_ret['sql'] .= 'null,';
                    } else {
                        $_ret['sql'] .= '?,';
                        $_ret['bind'][] = $v;
                    }
                }
                $_ret['sql'] = trim($_ret['sql'], ',') . '),';
            }
            $_ret['sql'] = trim($_ret['sql'], ',');
            $ret[] = $_ret;
        }

        return $ret;
    }

    /**
     * 多进程获取每毫秒数的唯一ID（每次累加）
     * 经测试，1h1g 服务器，每毫秒生成平均大约 50-60 个，也就是每秒能生成5、6万个ID
     * @param int $timestamp 毫秒
     * @param bool $throwException
     * @return int
     */
    public static function getMuxUid(int $timestamp, bool $throwException = false): int
    {
        if (!extension_loaded('sysvsem') || !extension_loaded('sysvshm')) {
            if ($throwException) {
                throw new UsageErrorException('缺少扩展「sysvsem」or「sysvshm」');
            }
            return 0;
        }
        $key = ftok(__FILE__, 'p');
        // 先获取 100kb 的共享内存段
        $shmKey = shm_attach($key, 102400);
        // 保存当前累计计数，方便统计使用多少次，然后删除共享内存，防止内存满了
        shm_put_var($shmKey, 1, (@shm_get_var($shmKey, 1) ?: 0) + 1);
        // 获取信号量ID
        $semKey = sem_get($key);
        // 加锁：请求信号量ID，其他进程占用，默认会阻塞
        if (sem_acquire($semKey)) {
            // 当前毫秒的key 是否有值，否则为 1
            if (shm_has_var($shmKey, $timestamp)) {
                $id = shm_get_var($shmKey, $timestamp);
                $id++;
            } else {
                $id = 1;
            }
            // 更新当前毫秒的key
            shm_put_var($shmKey, $timestamp, $id);
            if (shm_get_var($shmKey, 1) > 1000) {
                // 大于1000次就删除共享内存
                shm_remove($shmKey);
            } else {
                shm_detach($shmKey);
            }
            return $id;
        }
        return 0;
    }
}