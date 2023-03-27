<?php

namespace App\CriteriaFactory;

use DateTimeImmutable;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\HttpFoundation\Request;

class DictionaryElementCriteriaFactory
{
    private array $fieldMap = [
        'orderind' => ['d.orderind'],
    ];

    public function createCriteriaByRequest(Request $request): Criteria
    {
        $criteria = Criteria::create();
        $offset = $request->query->getInt('offset');
        $criteria->setFirstResult($offset);
        $limit  = $request->query->getInt('limit', 20);
        $criteria->setMaxResults($limit);
        $search = $request->query->get('search');
        $expr = Criteria::expr();
        if ($search) {
            $criteria->andWhere(
                $expr->orX(
                    $expr->contains('d.description', $search)
                )
            );
        }
        if ($id = $request->query->get('id')) {
            $criteria->andWhere(
                $expr->eq('d.id', $id)
            );
        }

        /** @var DateTimeImmutable[] $result */
        $sort = $request->query->get('sort');
        if ($sort) {
            $sort = explode(':', $sort);
            $field = $sort[0];
            $direct = $sort[1];
            $ormFields = $this->fieldMap[$field] ?? null;
            if ($ormFields && in_array($direct, ['ASC', 'DESC'])) {
                $orderings = [];
                foreach ($ormFields as $ormField) {
                    $orderings[$ormField] = $direct;
                }
                $criteria->orderBy($orderings);
            }
        }
        return $criteria;
    }
}
