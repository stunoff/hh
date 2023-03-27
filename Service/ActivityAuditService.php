<?php

namespace App\Service;

use App\Entity\ActivityLogRecord;
use App\Response\DataTablesSerializableInterface;
use App\Service\Client\OpenSearchClient;
use App\Service\Client\Response\OpenSearchResponse;
use App\Service\Factory\OpensearchQueryFactory;
use JMS\Serializer\SerializerInterface;

class ActivityAuditService extends OpenSearchClient
{
    private SerializerInterface $serializer;

    public function setSerializer(SerializerInterface $serializer): void
    {
        $this->serializer = $serializer;
    }

    public function getFieldsMapping(): array
    {
        return [
            'time' => OpensearchQueryFactory::TYPE_RANGE,
            'data' => OpensearchQueryFactory::TYPE_OBJECT,
            'user_name' => OpensearchQueryFactory::TYPE_STRING,
            'action_type' => OpensearchQueryFactory::TYPE_STRING,
        ];
    }

    public function log(ActivityLogRecord $record)
    {
        $this->client->create([
            'index' => $this->getIndexName(),
            'id' => $record->getId(),
            'body' => $this->serializer->serialize($record, 'json')
        ]);
    }

    public function retrieve(
        int $limit = 1000,
        int $start = 0,
        array $searchBy = [],
        array $orderBy = [],
    ): DataTablesSerializableInterface
    {
        if ($limit <= 0) {
            $limit = 10;
        }

        $limit = $limit <= 0 ? 10 : $limit;

        $data = $this->fetchAll($limit, $start, $searchBy, $orderBy);

        foreach ($data['hits']['hits'] as &$record) {
            $source = &$record['_source'];
            $record['_source'] = $this->serializer->fromArray($source, ActivityLogRecord::class);

        }

        return new OpenSearchResponse($data);
    }
}