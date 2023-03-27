<?php

namespace App\CriteriaFactory;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Common\Collections\Criteria;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;

class RemoteSystemCriteriaFactory
{
    private array $fieldMap = [
        'id' => 't.id',
        'create_date' => 't.create_date',
        'name' => 't.last_name',
    ];
    public function __construct(private Security $security) {

    }
    public function createCriteriaByRequest(Request $request, array $ids = []): Criteria
    {
        $criteria = Criteria::create();
        $offset = $request->query->getInt('offset');
        $criteria->setFirstResult($offset);
        $limit  = $request->query->getInt('limit', 20);
        $criteria->setMaxResults($limit);
        if (count($ids)) {
            $criteria->andWhere(Criteria::expr()->in('t.id', $ids));
        }
        
        /** @var DateTimeImmutable[] $result */
        $sort = $request->query->get('sort');
        if ($sort) {
            $sort = explode(':', $sort);
            $field = $sort[0];
            $direct = $sort[1];
            $ormField = $this->fieldMap[$field] ?? null;
            if ($ormField && in_array($direct, ['ASC', 'DESC'])) {
                $criteria->orderBy([
                    $ormField => $direct
                ]);
            }
        }

        $search = $request->query->get('search');
        $expr = Criteria::expr();
        if ($search) {
            $criteria->andWhere(
                $expr->contains("CONCAT(u.last_name, ' ', u.first_name, ' ', u.patronymic)", $search)
            );
        }

        return $criteria;
    }

    /**
     * @param string $value
     * @return DateTimeImmutable[]
     */
    private static function parseDateRange(string $value): array
    {
        $result = [null, null];
        foreach (explode('..', $value, 2) as $index => $item) {
            try {
                $result[$index] = self::parseDate($item)->setTime($index * 24, 0);
            } catch (InvalidArgumentException) {}
        }
        return $result;
    }

    private static function parseDate(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if ($date) {
            return $date;
        }
        throw new InvalidArgumentException();
    }
}
