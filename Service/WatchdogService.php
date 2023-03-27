<?php

namespace App\Service;

use App\Entity\ActivityLogRecord;
use App\Entity\Api\Individual;
use App\Entity\DictionaryElementSub\UserNotificationSettings;
use App\Entity\User;
use App\Entity\Watchdog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;

class WatchdogService
{
    public function __construct(
        private ActivityLogService $activityLog,
        private Security $security,
        private EntityManagerInterface $em,
        private UserNotificationService $notifications,
        private IndividualsApi $api
    ) {
    }

    /**
     * Called when a user does something (views/edits and etc) with individuals, triggers related watchdogs
     */
    public function triggerByIndividual($individual, int $actionType)
    {
        $this->triggerByIndividualList([$individual], $actionType);
    }

    /**
     * Called when a user does something (views/edits and etc) with one of individuals, triggers related watchdogs
     */
    public function triggerByIndividualList($individualList, int $actionType)
    {
        $activityLogType = [
            Watchdog::Type_RegistryView => ActivityLogRecord::Type_IndividualWatchdogTriggerListView,
            Watchdog::Type_CardView => ActivityLogRecord::Type_IndividualWatchdogTriggerView,
            Watchdog::Type_CardEdit => ActivityLogRecord::Type_IndividualWatchdogTriggerEdit,
            Watchdog::Type_CardPrint => ActivityLogRecord::Type_IndividualWatchdogTriggerPrint,
            Watchdog::Type_CardExport => ActivityLogRecord::Type_IndividualWatchdogTriggerExport,
            Watchdog::Type_CardLink => ActivityLogRecord::Type_IndividualWatchdogTriggerLink,
        ];
        $individualIdList = [];
        foreach ($individualList as $var) {
            if (is_object($var)) {
                $individualIdList[] = $var->getIndividualId();
            } elseif (isset($var['individual_id'])) {
                $individualIdList[] = $var['individual_id'];
            } else {
                $individualIdList[] = $var;
            }
        }
        $user = $this->security->getUser();
        $expr = $this->em->getExpressionBuilder();
        $watchdogs = $this->em->createQueryBuilder()
            ->select('w')
            ->from(Watchdog::class, 'w')
            ->join('w.created_user', 'u')
            ->leftJoin('w.notify_user', 'u2')
            ->where(
                'w.individual_id IN (:individuals)',
                $expr->eq('w.type', ':type'),
                $expr->neq('u', ':user'),
                $expr->orX(
                    $expr->isNull('u2'),
                    $expr->neq('u2', ':user')
                )
            )
            ->setParameter('type', $actionType)
            ->setParameter('individuals', $individualIdList)
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
        $notificationsSent = [];
        /* @var $watchdog Watchdog */
        foreach ($watchdogs as $watchdog) {
            $individualId = $watchdog->getIndividualId();
            /* @var $individualObject Individual */
            $individualObject = $this->api->requestIntoObject('get_individual_basic', [
                'individual_id' => $individualId
            ], Individual::class);
            $actionTypeName = Watchdog::typeName[$actionType];
            if (!isset($notificationsSent[$watchdog->getCreatedUser()->getId() . $individualId])) {
                /** @var User $user */
                $user = $this->security->getUser();
                $this->watchdogNotify($watchdog->getCreatedUser(), $user, $actionTypeName, $individualObject);
                $notificationsSent[$watchdog->getCreatedUser()->getId() . $individualId] = true;
            }
            if ($watchdog->getNotifyUser()) {
                if (!isset($notificationsSent[$watchdog->getNotifyUser()->getId() . $individualId])) {
                    /** @var User $user */
                    $user = $this->security->getUser();
                    $this->watchdogNotify($watchdog->getNotifyUser(), $user, $actionTypeName, $individualObject);
                    $notificationsSent[$watchdog->getNotifyUser()->getId() . $individualId] = true;
                }
            }
            $this->activityLog->addRecordForCurrentUser($activityLogType[$watchdog->getType()], [
                'individual_id' => $individualId,
            ]);
        }
    }

    private function watchdogNotify(User $dstUser, User $user, string $actionTypeName, Individual $individual)
    {
        $this->notifications->notify(
            $dstUser,
            UserNotificationSettings::WATCHDOG,
            sprintf(
                "Сторожевой маячок: пользователь `%s` (%s, %s) совершил действие `%s` с физлицом `%s` (%s)",
                $user->getFullName(),
                $user->getUserIdentifier(),
                $user->getId(),
                $actionTypeName,
                $individual->getFullNameAsString(),
                $individual->getIndividualId()
            )
        );
    }

    /**
     * Возвращает маячки, установленные на физлицо и доступные текущему пользователю для просмотра
     */
    public function getWatchdogs(Individual $individual)
    {
        $expr = $this->em->getExpressionBuilder();
        return $this->em
            ->createQueryBuilder()
            ->select('w')
            ->from(Watchdog::class, 'w')
            ->join('w.created_user', 'u')
            ->leftJoin('u.accesslvl', 'accesslevel')
            ->where(
                $expr->eq('w.individual_id', ':individual_id'),
                $expr->orX(
                    $expr->eq(':is_admin', 'true'),
                    $expr->eq('u', ':user'),
                    $expr->in('accesslevel', ':accesslevels')
                )
            )
            ->setParameter('is_admin', intval($this->security->isGranted('ROLE_ADMIN')))
            ->setParameter('individual_id', $individual->getIndividualId())
            ->setParameter('user', $this->security->getUser())
            ->setParameter('accesslevels', $this->security->getUser()->getLowerAccessLevelIds())
            ->getQuery()
            ->getResult();
    }

}
