<?php

namespace App\Service\Client;

use App\Service\Factory\OpensearchQueryFactory;
use OpenSearch\Client;

/**
 * @Deprecated Необходимо использовать App\Service\OpenSearchService
 */
class OpenSearchClient
{
    /** @var string Название индекса OpenSearch */
    protected string $index_name;

    public function __construct(
        protected Client $client,
        protected OpensearchQueryFactory $queryFactory,
    ) {}

    /**
     * @return string
     */
    public function getIndexName(): string
    {
        return $this->index_name;
    }

    /**
     * @param string $index_name
     */
    public function setIndexName(string $index_name)
    {
        $this->index_name = $index_name;
    }

    public function fetchAll(
        int $limit = 1000,
        int $start = 0,
        array $search = [],
        array $order = ['time' => ['order' => 'desc']]
    ): array
    {
        $body = [
            'size' => $limit,
            'from' => $start,
            'sort' => $order,
            'track_total_hits' => true,
        ];

        $query = $this->queryFactory->buildQuery($search, $this->getFieldsMapping());
        if (!empty($query)) {
            $body['query'] = $query;
        }

        return $this->client->search([
            'index' => $this->getIndexName(),
            'body' => $body,
        ]);
    }


    /**
     * @return bool
     */
    public function indexExists(): bool
    {
        return $this->client->indices()->exists(['index' => $this->getIndexName()]);
    }

    /**
     * @return void
     */
    public function dropIndex()
    {
        $this->client->indices()->delete(['index' => $this->getIndexName()]);
    }

    /**
     * @return void
     */
    public function createIndex()
    {
        $this->client->indices()->create(['index' => $this->getIndexName()]);
    }

    public function getFieldsMapping(): array
    {
        return [];
    }
}