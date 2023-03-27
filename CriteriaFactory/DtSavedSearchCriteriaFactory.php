<?php

namespace App\CriteriaFactory;

use DateTimeImmutable;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\HttpFoundation\Request;

class DtSavedSearchCriteriaFactory
{
    private array $fieldMap = [
        'date' => ['u.createdAt'],
        'name' => ['u.name'],
        'domain' => ['s.domain'],
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
                    $expr->contains('u.name', $search),
                )
            );
        }
        if ($domain = $request->query->get('domain')) {
            $criteria->andWhere(
                $expr->eq('u.domain', $domain)
            );
        }
        if ($date = $request->query->get('date')) {
            $criteria->andWhere(
                $expr->eq('u.createdAt', $date)
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
