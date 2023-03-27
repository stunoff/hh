<?php

namespace App\Service\Client\Response;

use App\Response\DataTablesSerializableInterface;

class OpenSearchResponse implements DataTablesSerializableInterface
{
    private int $count = 0;
    private int $total = 0;
    private array $data = [];

    /**
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->parse($data);
    }

    /**
     * @param int $count
     */
    public function setCount(int $count)
    {
        $this->count = $count;
    }

    /**
     * @param int $total
     */
    public function setTotal(int $total)
    {
        $this->total = $total;
    }

    /**
     * @param array $data
     */
    public function setData(array $data)
    {
        $this->data = $data;
    }

    /**
     * @return int
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * @return int
     */
    public function getTotal(): int
    {
        return $this->total;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    private function parse(array $data)
    {
        $payload = $data['hits'];

        $this->setTotal($payload['total']['value']);
        $this->setCount(count($payload['hits']));
        $data = [];
        foreach ($payload['hits'] as $row) {
            $data[] = $row['_source'];
        }
        $this->setData($data);
    }
}