<?php

namespace Varobj\XP\Exception;

use LogicException;
use Throwable;

use function is_array;
use function is_object;

abstract class BaseException extends LogicException
{
    protected $biz_code = 500; // 返回给用户的 error_code
    protected $message;   // 返回给用户的错误
    protected $debugError = [];// debug信息
    protected $ignoreLog = false;
    protected $exception_type = 'error';

    /**
     * @param string $message 业务错误信息 (用户可见)
     * @param int $bizCode 业务错误code (用户可见, 一旦定义不要变更)
     * @param Throwable|null $e
     */
    final public function __construct(string $message = '', int $bizCode = -1, Throwable $e = null)
    {
        $this->message = $message ?: $this->message;
        $bizCode !== -1 and $this->biz_code = $bizCode;
        parent::__construct($this->message, $this->biz_code, $e);
    }

    // 如果 true 日志会记录在 error*.log 否则 warn*.log
    public function isError(): bool
    {
        return $this->exception_type === 'error';
    }

    public function setWarning(): void
    {
        $this->exception_type = 'warning';
    }

    public function getDebug(): array
    {
        return $this->debugError;
    }

    /**
     * 错误代码
     * 也可以直接使用 getCode()
     * @return int
     */
    public function getBizCode(): int
    {
        return $this->biz_code;
    }

    public function setIgnore(bool $ignore = true): BaseException
    {
        $this->ignoreLog = $ignore;
        return $this;
    }

    public function getIgnore(): bool
    {
        return $this->ignoreLog;
    }

    /**
     * 额外添加的debug信息，只会在测试环境通过接口返回，都会保存日志，供额外快速分析错误使用
     * @param mixed $debugMessage
     * @return $this
     */
    public function debug($debugMessage): self
    {
        if (is_object($debugMessage)) {
            $this->debugError = get_object_vars($debugMessage);
        } elseif (is_array($debugMessage)) {
            $this->debugError = array_map(
                static function ($value) {
                    return is_object($value) ? get_object_vars($value) : $value;
                },
                $debugMessage
            );
        } elseif (is_scalar($debugMessage)) {
            $this->debugError = [$debugMessage];
        }

        return $this;
    }
}