<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\SystemOracleService;

#[Route('/admin')]
class SystemController extends AbstractController
{
    public function __construct(private SystemOracleService $systemOracleService) {}

    #[Route('/system', name: 'admin_system', methods: ['GET'])]
    public function system(SessionInterface $session, Request $request): Response
    {
        // Vérification de l'authentification
        if (!$session->has('user_id')) {
            return $this->redirectToRoute('login');
        }

        $action = $request->query->get('action', '');
        $message = null;
        $error = null;
        $locks = [];

        if ($action === 'list') {
            $locks = $this->systemOracleService->getLockedTables();
            if (!$locks) { $message = 'Aucun verrou détecté.'; }
        }

        return $this->render('admin/system.html.twig', [
            'locks' => $locks,
            'message' => $message,
            'error' => $error
        ]);
    }

    #[Route('/system/kill', name: 'admin_system_kill', methods: ['POST'])]
    public function kill(SessionInterface $session, Request $request): Response
    {
        if (!$session->has('user_id')) {
            return $this->redirectToRoute('login');
        }

        $sid = (int)$request->request->get('sid', 0);
        $serial = (int)$request->request->get('serial', 0);

        if ($sid && $serial) {
            $ok = $this->systemOracleService->killSession($sid, $serial);
            $this->addFlash($ok ? 'success' : 'danger', $ok ? 'Session tuée.' : 'Échec de la suppression');
        }
        return $this->redirectToRoute('admin_system', ['action' => 'list']);
    }

    #[Route('/system/kill-all', name: 'admin_system_kill_all', methods: ['POST'])]
    public function killAll(SessionInterface $session): Response
    {
        if (!$session->has('user_id')) {
            return $this->redirectToRoute('login');
        }
        $results = $this->systemOracleService->killAllLockedSessions();
        $nbSuccess = count(array_filter($results, fn($r) => $r['success'] ?? false));
        $this->addFlash('info', $nbSuccess . ' session(s) supprimée(s).');
        return $this->redirectToRoute('admin_system', ['action' => 'list']);
    }
}
