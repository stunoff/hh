<?php

namespace App\Controller;

use App\Entity\ActivityLogRecord;
use App\Entity\DataChangelog;
use App\Entity\UserNotification;
use App\Exception\NotificationHasAlreadyMarkedException;
use App\Form\ImportType;
use App\Repository\DataChangelogRepository;
use App\Repository\UserNotificationRepository;
use App\Serializer\Normalizer\UserNotificationSummaryNormalizer;
use App\Service\ActivityLogService;
use App\Service\FileStorageService;
use App\Service\SyncService;
use App\Service\TemplateManagerService;
use App\Service\UserNotificationService;
use App\Widget\PrintPageWidget;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;


#[Route(path: "/user_notification")]
class UserNotificationController extends AbstractController
{
    public function __construct(
        private DataChangelogRepository $changelogRepository,
        private EntityManagerInterface $em,
        private UserNotificationRepository $userNotificationRepository,
        private UserNotificationService $userNotificationService
    ) {
    }

    #[Route(path: "/", name: "user_notification_index", methods: ["GET", "POST"])]
    public function index(
        PrintPageWidget $printPage,
        TemplateManagerService $templateManager,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $personalListConfig = [
            'recordsPerPage' => $this->get('session')->get('user.user_notifications_config_list_records_per_page')
                ?? $this->getParameter('user.user_notifications_config_list_records_per_page'),
        ];

        $notifications = $this->userNotificationService->getUserNotifications($this->getUser()->getId());
        $tplParams = [
            'notifications' => $notifications,
            'personalConfig' => $personalListConfig,
        ];
        if ($printPage->shouldHandleRequest()) {
            $printPage->filterRequestedIds($tplParams['notifications'], 'id');
            if ($printPage->getRequestedIsPreview()) {
                return $templateManager->renderTemplate(
                    $printPage->getRequestedTemplateId(),
                    $tplParams
                );
            } else {
                return $templateManager->exportFile(
                    $printPage->getRequestedTemplateId(),
                    $tplParams
                );
            }
        }
        return $this->render('user_notification/index.html.twig', $tplParams);
    }

    #[Route(path: "/get_unread_count", name: "user_notification_unread_count", methods: ["GET"])]
    public function getUnreadCount(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $notificationsCount = $this->userNotificationService->getUnreadCount($this->getUser()->getId());
        return $this->json($notificationsCount);
    }

    #[Route(path: "/delay", name: "user_notifications_delay", methods: ["GET", "POST"])]
    public function delayNotifications(Request $request): Response
    {
        $ids = $request->get('ids');
        $notifications = $this->userNotificationRepository->findBy(['id' => $ids]);
        foreach ($notifications as $notification) {
            if ($notification->getDstUser()->getId() == $this->getUser()->getId()) {
                $oldObject = clone $notification;
                $notification->setDelayedUntil(
                    (new \DateTime())->setTimestamp(strtotime("+{$request->get('delayMinutes')} minutes"))
                );
                $this->changelogRepository->writeUpdate(
                    DataChangelog::Type_PersonalNotification,
                    $oldObject,
                    $notification
                );
                $this->em->persist($notification);
                $this->em->flush();
            }
        }
        return new Response();
    }

    #[Route(path: "/view/{id}", name: "user_notification_view", methods: ["GET"])]
    public function view(Request $request, UserNotification $userNotification): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        if ($userNotification->getDstUser()->getId() != $this->getUser()->getId()) {
            throw new AccessDeniedException();
        }
        return $this->render('user_notification/notification.html.twig', [
            'notification' => $userNotification,
            'request' => $request,
            'time' => time()
        ]);
    }

    #[Route(
        path: "/get_unread_user_notifications_summary",
        name: "get_unread_user_notifications_summary",
        methods: ["GET"]
    )]
    public function getUnreadSummary(): Response
    {
        if (!$this->isGranted('ROLE_USER')) {
            $response = new Response();
            $response->setStatusCode(403);
            return $response;
        }
        $this->denyAccessUnlessGranted('ROLE_USER');

        $notifications = $this->userNotificationService->getUnreadNotifications($this->getUser()->getId());

        return $this->json($notifications, context: [
            UserNotificationSummaryNormalizer::SUMMARY_CONTEXT,
            'circular_reference_handler' => function ($object) {
                return $object->getId();
            }
        ]);
    }

    #[Route(path: "/mark_read/{id}", name: "user_notification_mark_read", methods: "GET")]
    public function markRead(Request $request, UserNotification $userNotification): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        try {
            $this->userNotificationService->markAsRead($this->getUser()->getId(), $userNotification);
        } catch (NotificationHasAlreadyMarkedException $alreadyMarkedException) {
            throw new ConflictHttpException('Отметка о прочтении уже установлена');
        } catch (\Throwable $e) {
            throw new ServiceUnavailableHttpException('Невозможно установить отметку о прочтении');
        }

        $redirect = $request->get('redirect_url');
        if (!empty($redirect)) {
            return $this->redirect($redirect);
        }

        return $this->json([]);
    }

    #[Route(path: "/mark_all_read", name: "user_notification_mark_all_read", methods: ["GET"])]
    public function markAllRead(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        try {
            $this->userNotificationService->markAllAsRead($this->getUser()->getId());
        } catch (\Throwable $e) {
            throw new ServiceUnavailableHttpException('Невозможно установить отметки о прочтении');
        }

        $redirect = $request->get('redirect_url');
        if (!empty($redirect)) {
            return $this->redirect($redirect);
        }

        return $this->json([]);
    }

    #[Route(path: "/user_notification_import", name: "user_notification_import")]
    public function import(Request $request, SyncService $sync, FileStorageService $filestor): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $form = $this->createForm(ImportType::class)->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $filename = $filestor->moveUplaodedFile($form->get('file')->getData(), 'import', "notifications");

            $data = $sync->loadFromFile($filestor->getFileBasepath('import', 'notifications') . $filename);

            $rows = [];
            $cnt = 0;
            foreach ($data as $row) {
                $row = [
                    'id' => $row['Идентификатор'],
                    'dst_user' => $row['id пользователя'],
                    'created_ts' => $row['Время'],
                    'ack_ts' => $row['Время прочтения'],
                    'delayed_until' => $row['Отложено до'],
                    'message' => $row['Сообщение'],
                    'type' => $row['id типа']
                ];
                $cnt += $sync->writeObjectIfNotExists($row, UserNotification::class);
            }

            return $this->render(
                'user_notification/confirm.html.twig',
                [
                    'count' => $cnt,
                ]
            );
        }

        return $this->render(
            'user_notification/import.html.twig',
            [
                'form' => $form->createView(),
            ]
        );
    }
}
