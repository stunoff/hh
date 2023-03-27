<?php

namespace App\Service;

use App\Entity\ActivityLogRecord;
use App\Entity\DataChangelog;
use App\Entity\User;
use App\Repository\DataChangelogRepository;
use DateInterval;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;

class ActivityLogService
{
    public function __construct(
        private Security $security,
        private NodeService $nodeService,
        private EntityManagerInterface $em,
        private DataChangelogRepository $changelogRepository,
        private ActivityAuditService $auditService,
    ) {
    }

    public function addRecord($user, $type, $data = null)
    {
        $record = new ActivityLogRecord();
        if (is_string($user)) {
            $user = $this->em->getRepository(User::class)->find($user);
        }
        $record->setActiveUser($user);
        $record->setActionType($type);
        $record->setData($data);
        $record->setNode($this->nodeService->getCurrentNode());
        $record->setTime(new DateTime());

        // fake update data with 10% chance
        $oldObject = null;
        if (rand(0, 100) < 10) {
            $oldObject = clone $record;
            $oldObject->setTime((new DateTime())->add(new DateInterval('P1M')));
        }

        // add record to replication table
        $this->changelogRepository->writeUpdate(DataChangelog::Type_UserActivity, $oldObject, $record);

        $this->em->persist($record);
        $this->em->flush($record);

        $this->auditService->log($record);
    }

    public function addRecordForCurrentUser($type, $data = null)
    {
        $this->addRecord($this->security->getUser(), $type, $data);
    }

}
