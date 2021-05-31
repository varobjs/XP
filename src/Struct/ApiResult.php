<?php

namespace Varobj\XP\Struct;

use Varobj\XP\Exception\UsageErrorException;
use Varobj\XP\Service\UserService;

/**
 * @OA\Schema(
 *     description="接口返回结构",
 *     title="ApiResult"
 * )
 */
class ApiResult
{
    /**
     * 接口状态值
     * @OA\Property(
     *     format="string",
     *     enum={"success", "error", "warning"}
     * )
     * @var string
     */
    protected $status = 'success';

    /**
     * 接口错误码，0 表示没有错误
     * @OA\Property(format="int64")
     * @var int
     */
    protected $code = 0;

    /**
     * 错误时的描述信息
     * @OA\Property(format="string")
     * @var string
     */
    protected $message = '';

    /**
     * 需要传递的数据结构
     * @OA\Property(format="mixed")
     */
    protected $data;

    /**
     * 唯一请求ID
     * @OA\Property(format="string")
     * @var string
     */
    protected $request_id = '';

    public function __construct(string $status, int $code)
    {
        $this->status = $status;
        $this->code = $code;

        // 获取生命周期的唯一请求ID
        $this->request_id = UserService::getRequestID();

        if (!in_array($status, ['success', 'error', 'warning'], true)) {
            throw new UsageErrorException('status状态值错误:' . $status);
        }
    }

    public function toArray(): array
    {
        $res = [
            'status' => $this->status,
            'code' => $this->code,
        ];
        $res['message'] = $this->message;
        $res['data'] = $this->data;
        $res['request_id'] = $this->request_id;
        return $res;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @return int
     */
    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    public function getData()
    {
        return $this->data;
    }

    /**
     * @param string $status
     */
    public function setStatus(string $status): void
    {
        $this->status = $status;

        if (!in_array($status, ['success', 'error', 'warning'], true)) {
            throw new UsageErrorException('status状态值错误:' . $status);
        }
    }

    /**
     * @param int $code
     * @return ApiResult
     */
    public function setCode(int $code): ApiResult
    {
        $this->code = $code;
        return $this;
    }

    /**
     * @param string $message
     * @return ApiResult
     */
    public function setMessage(string $message): ApiResult
    {
        $this->message = $message;
        return $this;
    }

    /**
     * @param mixed $data
     * @return ApiResult
     */
    public function setData($data): ApiResult
    {
        $this->data = $data;
        return $this;
    }
}