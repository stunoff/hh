<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;

#[Route(path: "/user-panel")]
class UserPanelController extends AbstractController
{
    #[Route(path: "/", name: "user_panel_index", methods: ["GET"])]
    public function show(Security $security): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $security->getUser();
        return $this->render('user_panel/show.html.twig', [
            'user' => $user
        ]);
    }

}
