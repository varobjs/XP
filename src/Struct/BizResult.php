<?php

/**
 * Biz 业务代码-通用返回结构对象
 */

namespace Varobj\XP\Struct;

/**
 * @OA\Schema(type="object", @OA\Xml(name="BizResult"))
 */
class BizResult
{
    /**
     * -1 初始化
     * 0 成功
     * @OA\Property(format="int64")
     * @var int
     */
    protected $code = -1;

    /**
     * 成功 or 失败的具体信息
     * @OA\Property(format="string")
     * @var string
     */
    protected $msg = '';

    /**
     * 需要传递的数据结构
     * @OA\Property(
     *     @OA\Items()
     * )
     *
     * @var array
     */
    protected $data = [];

    /**
     * 错误类型
     * error | warning
     * @var string
     */
    protected $error_type = 'error';

    public function isSuccess(): bool
    {
        return $this->code === 0;
    }

    public function setCode(int $code): BizResult
    {
        $this->code = $code;
        return $this;
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function isWarning(): void
    {
        $this->error_type = 'warning';
    }

    public function errorType(): string
    {
        return $this->error_type;
    }

    public function setMsg(string $msg): BizResult
    {
        $this->msg = $msg;
        return $this;
    }

    public function getMsg(): string
    {
        return $this->msg;
    }

    public function setData(array $data): BizResult
    {
        $this->data = $data;
        return $this;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function __toString(): string
    {
        $str = json_encode(
            [
                'code' => $this->code,
                'type' => $this->error_type,
                'msg' => $this->msg,
                'data' => $this->data
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        if (is_string($str)) {
            return $str;
        }
        return '';
    }
}