<?php

namespace Varobj\XP;

use Varobj\XP\Exception\ErrorException;
use Varobj\XP\Exception\UsageErrorException;

/**
 * Class XSonic
 *
 * 使用方式：
 *
 * search 模式
 *
 * $sonic = XSonic::getInstance()->find();
 *
 * 支持方法：
 *
 * $sonic->search($collection, $buket, $terms);
 * $sonic->search($collection, $buket, $terms, $limit, $offset);
 *
 * ingest 模式
 *
 * $sonic = XSonic::getInstance()->insert();
 *
 * 支持方法：
 *
 * $sonic->push($collection, $buket, $object, $text);
 * $sonic->push($collection, $buket, $object, $text, $lang);
 *
 * @package Varobj\XP
 */
class XSonic
{
    use Instance;

    protected $socket;
    protected $password;
    protected $mode;
    protected $buffer = 8192;

    public function __construct(array $params = [])
    {
        $host = $params['host'] ?? 'localhost';
        $port = $params['port'] ?? 1491;
        $connTimeout = $params['timeout'] ?? 3;
        $persistent = $params['persistent'] ?? false;
        $this->password = $params['password'] ?? 'SecretPassword';
        $readTimeout = $params['readTimeout'] ?? 0;

        $this->socket = @stream_socket_client(
            "tcp://{$host}:{$port}",
            $errno,
            $msg,
            $connTimeout,
            $persistent ? STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT : STREAM_CLIENT_CONNECT
        );
        if (!$this->socket || $errno || $msg) {
            throw new UsageErrorException('[sonic connect error] ' . $msg);
        }
        if ($readTimeout > 0) {
            stream_set_timeout($this->socket, $readTimeout);
        }
        $ret = $this->read();
        if (strpos($ret, 'CONNECTED') !== 0) {
            throw new UsageErrorException('[sonic connect error] connect error; ' . $ret);
        }
        _verbose($ret);
    }

    protected function write(string $command): void
    {
        // 转义特殊符号：\
        $command = str_replace(
            [
                "\\",
                "\r",
                "\r\n",
                "\n"
            ],
            '',
            $command
        );
        $_len = fwrite($this->socket, $command . "\n");
        if ($_len === false) {
            throw new UsageErrorException('[sonic error] write socket error; ' . $command);
        }
    }

    protected function read(): string
    {
        $str = stream_get_line($this->socket, $this->buffer, "\r\n");
        if ($str === false) {
            throw new UsageErrorException('[sonic error] read response error');
        }

        return trim($str);
    }

    /**
     * START <mode> <password>
     * <mode> search
     * @return $this
     */
    public function find(): XSonic
    {
        $this->mode = 'search';
        $command = 'START search ' . $this->password;
        $this->write($command);
        $result = $this->read();
        if (strpos($result, 'STARTED search') !== 0) {
            throw new ErrorException('[sonic error] start search mode error; ' . $result);
        }
        _verbose($result);
        preg_match('/.*buffer\((\d+)\).*/', $result, $_ret);
        $this->buffer = (int)($_ret[1] ?? 0);
        return $this;
    }

    /**
     * START <mode> <password>
     * <mode> ingest
     * @return $this
     */
    public function insert(): XSonic
    {
        $this->mode = 'ingest';
        $command = 'START ingest ' . $this->password;
        $this->write($command);
        $result = $this->read();
        if (strpos($result, 'STARTED ingest') !== 0) {
            throw new ErrorException('[sonic error] start ingest mode error; ' . $result);
        }
        _verbose($result);
        return $this;
    }

    /**
     * START <mode> <password>
     * <mode> control
     * @return $this
     */
    public function manager(): XSonic
    {
        $this->mode = 'control';
        $command = 'START control ' . $this->password;
        $this->write($command);
        $result = $this->read();
        if (strpos($result, 'STARTED control') !== 0) {
            throw new ErrorException('[sonic error] start control mode error; ' . $result);
        }
        _verbose($result);
        return $this;
    }

    public function suggest(
        string $collection,
        string $bucket,
        string $term
    ): array {
        if ($this->mode !== 'search') {
            throw new ErrorException('[sonic error] must start search mode');
        }
        $command = 'SUGGEST ' . trim($collection) . ' ' . trim($bucket) . ' "' . trim($term) . '"';

        $this->write($command);
        $result = $this->read();
        if (strpos($result, 'PENDING') !== 0) {
            throw new ErrorException('[sonic error] suggest error1; ' . $command . '; ' . $result);
        }
        $result = $this->read();
        if (strpos($result, 'EVENT SUGGEST') !== 0) {
            throw new ErrorException('[sonic error] suggest error2; ' . $command . '; ' . $result);
        }
        $result = explode(' ', $result);
        array_shift($result);
        array_shift($result);
        $eventId = array_shift($result);
        return [
            'event_id' => $eventId,
            'list' => $result
        ];
    }

    /**
     * QUERY <collection> <bucket> "<terms>" [LIMIT(<count>)]? [OFFSET(<count>)]?
     * @param string $collection
     * @param string $bucket
     * @param string $terms
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function query(
        string $collection,
        string $bucket,
        string $terms,
        int $limit = 0,
        int $offset = 0
    ): array {
        if ($this->mode !== 'search') {
            throw new ErrorException('[sonic error] must start search mode');
        }
        $command = 'QUERY ' . trim($collection) . ' ' . trim($bucket) . ' "' . trim($terms) . '"';
        if ($limit) {
            $command .= ' LIMIT(' . $limit . ') OFFSET(' . $offset . ')';
        }
        $this->write($command);
        $result = $this->read();
        if (strpos($result, 'PENDING') !== 0) {
            throw new ErrorException('[sonic error] query error1; ' . $command . '; ' . $result);
        }
        $result = $this->read();
        if (strpos($result, 'EVENT QUERY') !== 0) {
            throw new ErrorException('[sonic error] query error2; ' . $command . '; ' . $result);
        }
        $result = explode(' ', $result);
        array_shift($result);
        array_shift($result);
        $eventId = array_shift($result);
        return [
            'event_id' => $eventId,
            'list' => $result
        ];
    }

    /**
     * PUSH <collection> <bucket> <object> "<text>" [LANG(<locale>)]?
     * 语言支持:
     * 英语      (eng)
     * 普通话   （cmn）   ---> 中文（zh、zho 都不支持）
     *
     * @param string $collection
     * @param string $bucket
     * @param string $object
     * @param string $text
     * @param string $lang
     */
    public function push(
        string $collection,
        string $bucket,
        string $object,
        string $text,
        string $lang = ''
    ): void {
        if ($this->mode !== 'ingest') {
            throw new ErrorException('[sonic error] must ingest control mode');
        }
        $command = 'PUSH ' . trim($collection) . ' ' . trim($bucket) . ' ' . trim($object);
        $command .= ' "' . $text . '"';
        if ($lang) {
            $command .= ' LANG(' . $lang . ')';
        }
        $this->write($command);
        $ret = $this->read();
        if ($ret !== 'OK') {
            throw new ErrorException('[sonic error] push text error; ' . $ret . '; ' . $command);
        }
    }

    public function info(): array
    {
        if ($this->mode !== 'control') {
            throw new ErrorException('[sonic error] must start control mode');
        }
        $command = 'info';
        $this->write($command);
        $result = $this->read();
        if (strpos($result, 'RESULT') !== 0) {
            throw new ErrorException('[sonic error] control info error; ' . $result);
        }

        $result = explode(' ', trim(substr($result, 7)));
        $ret = [];
        array_map(
            function ($v) use (&$ret) {
                $v = explode('(', rtrim($v, ')'));
                if (!empty($v[0]) && isset($v[1])) {
                    $ret[$v[0]] = $v[1];
                }
            },
            $result
        );
        return $ret;
    }

    public function close(): void
    {
        if ($this->socket) {
            fwrite($this->socket, 'quit');
            stream_socket_shutdown($this->socket, STREAM_SHUT_WR);
            $this->socket = null;
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}