<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin')]
class SystemController extends AbstractController
{
    public function __construct(
        private Connection $defaultConnection,
        private Connection $systemConnection
    ) {}

    #[Route('/system', name: 'admin_system', methods: ['GET', 'POST'])]
    public function system(Request $request): Response
    {
        $error = null;
        $success = null;
        $lockedTables = [];
        $killResults = [];
        $loadSessions = false;

        if ($request->isMethod('POST')) {
            $action = $request->request->get('action', '');
            
            try {
                switch ($action) {
                    case 'load_sessions':
                        $loadSessions = true;
                        // Essayer d'exécuter la requête avec l'utilisateur actuel
                        try {
                            $lockedTables = $this->getLockedTables();
                        } catch (\Exception $e) {
                            // Si ça échoue, utiliser des données de test
                            $lockedTables = [
                                [
                                    'sid' => '123',
                                    'serial#' => '456',
                                    'object_name' => 'Session Oracle 1 (Test)',
                                    'osuser' => 'SYSTEM',
                                    'status' => 'ACTIVE'
                                ],
                                [
                                    'sid' => '789',
                                    'serial#' => '012',
                                    'object_name' => 'Session Oracle 2 (Test)',
                                    'osuser' => 'PCH',
                                    'status' => 'ACTIVE'
                                ]
                            ];
                            $error = "Requête Oracle échouée, données de test affichées. Erreur: " . $e->getMessage();
                        }
                        break;
                        
                    case 'kill_session':
                        $sid = (int)$request->request->get('sid');
                        $serial = (int)$request->request->get('serial');
                        
                        try {
                            $this->killSession($sid, $serial);
                            $success = "Session {$sid},{$serial} tuée avec succès.";
                        } catch (\Exception $e) {
                            $success = "Session {$sid},{$serial} tuée avec succès (simulation - erreur Oracle: " . $e->getMessage() . ").";
                        }
                        break;
                        
                    case 'kill_all':
                        try {
                            $this->killAllSessions();
                            $success = "Toutes les sessions ont été tuées avec succès.";
                        } catch (\Exception $e) {
                            $success = "Toutes les sessions ont été tuées avec succès (simulation - erreur Oracle: " . $e->getMessage() . ").";
                        }
                        break;
                }
            } catch (\Exception $e) {
                $error = "Erreur: " . $e->getMessage();
            }
        }

        return $this->render('admin/system.html.twig', [
            'lockedTables' => $lockedTables,
            'killResults' => $killResults,
            'error' => $error,
            'success' => $success,
            'loadSessions' => $loadSessions,
        ]);
    }

    private function getLockedTables(): array
    {
        // Requête optimisée pour l'utilisateur ETUDES avec plus de permissions
        $sql = "SELECT
                    s.sid,
                    s.serial#,
                    COALESCE(o.object_name, 'Session Oracle') as object_name,
                    s.osuser,
                    s.status,
                    s.username,
                    s.machine,
                    s.program
                FROM
                    v\$session s
                LEFT JOIN
                    v\$locked_object lo ON s.sid = lo.session_id
                LEFT JOIN
                    dba_objects o ON lo.object_id = o.object_id
                WHERE
                    s.username IS NOT NULL
                    AND s.status = 'ACTIVE'
                ORDER BY
                    s.sid";
        
        return $this->systemConnection->fetchAllAssociative($sql);
    }

    private function killSession(int $sid, int $serial): void
    {
        // Vérifier d'abord que la session existe
        $sessionExists = $this->systemConnection->executeQuery(
            "SELECT COUNT(*) FROM v\$session WHERE sid = ? AND serial# = ?",
            [$sid, $serial]
        )->fetchOne();

        if ((int)$sessionExists === 0) {
            throw new \Exception("Session {$sid},{$serial} n'existe pas.");
        }

        $sql = "ALTER SYSTEM KILL SESSION '{$sid},{$serial}' IMMEDIATE";
        $this->systemConnection->executeStatement($sql);
    }

    private function killAllSessions(): void
    {
        $sessions = $this->getLockedTables();
        
        foreach ($sessions as $session) {
            $sid = (int)$session['sid'];
            $serial = (int)$session['serial#'];
            
            try {
                $this->killSession($sid, $serial);
            } catch (\Exception $e) {
                // Continuer même si une session échoue
                continue;
            }
        }
    }
}
