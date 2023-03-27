<?php

namespace App\CriteriaFactory;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Common\Collections\Criteria;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;

class RemoteRequestCriteriaFactory
{
    private array $fieldMap = [
        'id' => 't.id',
        'created_at' => 't.createdAt',
        'status' => 't.Status',
        'serial' => 't.requestSerial',
        'user.name' => 'author.last_name, author.first_name, author.patronymic',
        'theme' => 't.theme',
        'created_at' => 't.created_at',
        'dates_cu' => 't.maxDateSynced',
        'dates_work' => 't.maxDateWork',
        'dates_response' => 't.maxDateResponse',
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

        $flag = $request->get('flag', 0);
        if ($flag === 1) {
            $criteria->andWhere(Criteria::expr()->in('fl.id', [$this->security->getUser()]));
        }
        if ($flag === 2) {
            $criteria->andWhere(Criteria::expr()->notIn('fl.id', $this->security->getUser()->getId()));
        }

        $Status = $request->query->get('status');
        if (!empty($Status)) {
            $criteria->andWhere(Criteria::expr()->eq('Status', $Status));
        }

        $requestSerial = $request->query->get('request_serial');
        if (!empty($requestSerial)) {
            $criteria->andWhere(Criteria::expr()->eq('requestSerial', $requestSerial));
        }

        $reason = $request->query->get('reason');
        if (!empty($reason)) {
            $criteria->andWhere(Criteria::expr()->contains('reason', '%'.$reason.'%'));
        }

        $oriRequirement = $request->query->get('request');
        if (!empty($oriRequirement)) {
            $criteria->andWhere(Criteria::expr()->contains('request', '%'.$oriRequirement.'%'));
        }

        $crimeAddress = $request->query->get('crimeAddress');
        if (!empty($crimeAddress)) {
            $criteria->andWhere(Criteria::expr()->contains('address', '%'.$crimeAddress.'%'));
        }

        $createdAtFrom = $request->query->get('create_date_from');
        if (!empty($createdAtFrom)) {
            $criteria->andWhere(Criteria::expr()->gte('createdAt', $createdAtFrom));
        }

        $createdAtTo = $request->query->get('create_date_to');
        if (!empty($createdAtTo)) {
            $criteria->andWhere(Criteria::expr()->lte('createdAt', $createdAtTo));
        }

        $oriStartDate = $request->query->get('request_date_from');
        if (!empty($oriStartDate)) {
            $criteria->andWhere(Criteria::expr()->gte('requestDateFrom', $oriStartDate));
        }

        $oriEndDate = $request->query->get('request_date_to');
        if (!empty($oriEndDate)) {
            $criteria->andWhere(Criteria::expr()->lte('requestDateTo', $oriEndDate));
        }

        $maxDateSyncedStart = $request->query->get('max_date_synced_from');
        if (!empty($maxDateSyncedStart)) {
            $criteria->andWhere(Criteria::expr()->gte('maxDateSynced', $maxDateSyncedStart));
        }

        $maxDateSyncedEnd = $request->query->get('max_date_synced_to');
        if (!empty($maxDateSyncedEnd)) {
            $criteria->andWhere(Criteria::expr()->lte('maxDateSynced', $maxDateSyncedEnd));
        }

        $maxDateWorkStart = $request->query->get('max_date_work_from');
        if (!empty($maxDateWorkStart)) {
            $criteria->andWhere(Criteria::expr()->gte('maxDateWork', $maxDateWorkStart));
        }

        $maxDateWorkEnd = $request->query->get('max_date_work_to');
        if (!empty($maxDateWorkEnd)) {
            $criteria->andWhere(Criteria::expr()->lte('maxDateWork', $maxDateWorkEnd));
        }

        $maxDateResponseStart = $request->query->get('max_date_response_from');
        if (!empty($maxDateResponseStart)) {
            $criteria->andWhere(Criteria::expr()->gte('maxDateResponse', $maxDateResponseStart));
        }

        $maxDateResponseEnd = $request->query->get('max_date_response_to');
        if (!empty($maxDateResponseEnd)) {
            $criteria->andWhere(Criteria::expr()->lte('maxDateResponse', $maxDateResponseEnd));
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
