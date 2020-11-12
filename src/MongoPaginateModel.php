<?php

namespace Varobj\XP;

use MongoDB\Collection;

/**
 * usage:
 * $model = new MonogoPaginateModel([
 *      'model' => DelayJobLog::class,
 *      'filter' => [],
 *      'limit' => 10,
 *      'page' => 0
 * ]);
 *
 * $model->getCurrent();
 * $model->getTotalItems();
 * $model->getItems();
 *
 * $model->toArray();
 *
 * Class MongoPaginateModel
 * @package Varobj\XP
 */
class MongoPaginateModel
{
    /**
     * @var Collection $model
     */
    protected $model;

    protected $filter;

    protected $page_size;

    protected $page;

    protected $options = [];

    public function __construct(array $params)
    {
        $this->model = call_user_func([$params['model'], 'new']);
        $this->filter = $params['filter'];
        $this->page_size = $params['page_size'];
        $this->page = $params['page'];
        if (!empty($params['options']) and is_array($params['options'])) {
            $this->options = $params['options'];
        }
        $this->options['limit'] = $this->page_size;
        $this->options['skip'] = ($this->page - 1) * $this->page_size;
    }

    public function getTotalNumber(): int
    {
        return $this->model->countDocuments($this->filter);
    }

    public function getList()
    {
        $data = $this->model->find(
            $this->filter,
            $this->options
        )->toArray();
        return json_decode(json_encode($data), true);
    }

    public function getCurrentPage()
    {
        return $this->page;
    }

    public function toArray(): array
    {
        return [
            'page_info' => [
                'page' => $this->getCurrentPage(),
                'page_size' => $this->page_size,
                'total_number' => $this->getTotalNumber(),
                'total_page' => ceil($this->getTotalNumber() / $this->page_size),
            ],
            'list' => $this->getList()
        ];
    }
}