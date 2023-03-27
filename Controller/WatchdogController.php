<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Watchdog;
use App\Repository\UserRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class WatchdogController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
    ) {
    }

    #[Route('/watchdog/{id}/remove', name: 'watchdog_remove', methods: ['GET'])]
    public function remove(Watchdog $watchdog)
    {
        if ($watchdog->getCreatedUser() == $this->getUser()) {
            $this->em->remove($watchdog);
            $this->em->flush();
            return new JsonResponse(['status' => 'success']);
        }
    }

    #[Route(path: "/watchdog_set", name: "watchdog_set")]
    public function set(Request $request): Response
    {
        if (($userToNotifyId = $request->get('notify_user')) === null) {
            $users = $this->userRepository->findBy([], [
                'last_name'  => 'ASC',
                'first_name' => 'ASC',
            ]);
            return $this->render('watchdog/set_select_user_to_notify.html.twig', [
                'request' => $request,
                'users' => $users,
            ]);
        } else {
            if ($userToNotifyId && $userToNotifyId != $this->getUser()->getId()) {
                $notifyUser = $this->userRepository->find($userToNotifyId);
            } else {
                $notifyUser = null;
            }
            $this->denyAccessUnlessGranted('ROLE_USER');
            $watchdog = new Watchdog();
            $existingWatchdog = $this->em->getRepository(Watchdog::class)->findBy([
                'individual_id' => $request->get('individual_id'),
                'type' => $request->get('type'),
                'created_user' => $this->getUser(),
                'notify_user' => $notifyUser
            ]);
            if (!$existingWatchdog) { // if such watchdog does not exist
                $watchdog->setIndividualId($request->get('individual_id'))
                    ->setCreatedTs(new DateTime())
                    ->setCreatedUser($this->getUser())
                    ->setType($request->get('type'));
                if ($notifyUser) {
                    $watchdog->setNotifyUser($notifyUser);
                }
                $this->em->persist($watchdog);
                $this->em->flush();
            }
            return $this->render('watchdog/set.html.twig', [
                'notify_user' => $notifyUser,
            ]);
        }
    }
}
