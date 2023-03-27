<?php

namespace App\CriteriaFactory;

use DateTimeImmutable;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\HttpFoundation\Request;

class UserCriteriaFactory
{
    private array $fieldMap = [
        'id' => ['u.id'],
        'username' => ['u.username'],
        'status' => ['s.orderind'],
        'rank' => ['r.orderind'],
        'position' => ['u.position'],
        'node' => ['n.orderind'],
        'accesslvl' => ['l.orderind'],
        'fio' => ['u.last_name', 'u.first_name', 'u.patronymic'],
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
                    $expr->contains('n.description', $search),
                    $expr->contains('u.username', $search),
                    $expr->contains('u.first_name', $search),
                    $expr->contains('u.last_name', $search),
                    $expr->contains('u.patronymic', $search),
                )
            );
        }

        if ($id = $request->query->get('id')) {
            $criteria->andWhere(
                $expr->eq('u.id', $id)
            );
        }
        if ($username = $request->query->get('username')) {
            $criteria->andWhere(
                $expr->eq('u.username', $username)
            );
        }

        if ($status = $request->query->get('status')) {
            $criteria->andWhere(
                $expr->eq('u.status', $status)
            );
        }
        if ($node = $request->query->get('node')) {
            $criteria->andWhere(
                $expr->eq('u.node', $node)
            );
        }
        if ($rank = $request->query->get('rank')) {
            $criteria->andWhere(
                $expr->eq('u.rank', $rank)
            );
        }
        if ($fullName = $request->query->get('full_name')) {
            $criteria->andWhere(
                $expr->contains("CONCAT(u.last_name, ' ', u.first_name, ' ', u.patronymic)", $fullName)
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
