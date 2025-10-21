<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin')]
class SystemController extends AbstractController
{
    #[Route('/system', name: 'admin_system', methods: ['GET'])]
    public function system(SessionInterface $session): Response
    {
        // Vérification de l'authentification
        if (!$session->has('user_id')) {
            return $this->redirectToRoute('login');
        }

        return $this->render('admin/system.html.twig');
    }
}
