<?php

namespace App\Controller;

use App\Entity\EditionBureautique;
use App\Service\AdminDataService;
use App\Service\EditionBureautiqueOracleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

#[Route('/admin')]
class AdminController extends AbstractController
{
    public function __construct(
        private AdminDataService $adminDataService,
        private EditionBureautiqueOracleService $oracleService
    ) {}

    #[Route('', name: 'admin_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        // Liste des entités disponibles
        $availableEntities = [
            'EditionBureautique' => [
                'class' => EditionBureautique::class,
                'label' => 'Éditions Bureautiques',
                'icon' => '📄'
            ]
        ];

        return $this->render('admin/dashboard.html.twig', [
            'availableEntities' => $availableEntities
        ]);
    }

    #[Route('/entity/{entityName}', name: 'admin_entity_view', methods: ['GET'])]
    public function viewEntity(string $entityName, Request $request): Response
    {
        if (strtolower($entityName) === 'editionbureautique' || strtolower($entityName) === 'edition-bureautique') {
            // Récupérer les données Oracle
            $data = $this->oracleService->fetchEditions();
            // Adapter les métadonnées pour la vue
            $metadata = [
                'entityName' => 'EditionBureautique',
                'tableName' => 'Oracle',
                'columns' => [
                    ['name' => 'NOM_BI', 'label' => 'Code BI', 'type' => 'string'],
                    ['name' => 'DOCUMENT_TYPE', 'label' => 'Type de document', 'type' => 'string'],
                    ['name' => 'DESCRIPTION_BI', 'label' => 'Description BI', 'type' => 'string'],
                    ['name' => 'NOM_DOCUMENT', 'label' => 'Nom du document', 'type' => 'string'],
                ]
            ];
            // Pagination simple (tout d'un coup)
            $pagination = [
                'page' => 1,
                'total' => count($data),
                'limit' => count($data),
                'totalPages' => 1
            ];
            return $this->render('admin/entity_view.html.twig', [
                'entityName' => $entityName,
                'metadata' => $metadata,
                'data' => $data,
                'pagination' => $pagination,
                'search' => ''
            ]);
        }

        if (strtolower($entityName) === 'utilisateur' || strtolower($entityName) === 'utilisateurs') {
            // Données simulées (à remplacer par ta requête plus tard)
            $data = [
                [
                    'NOM' => 'Dupont',
                    'PRENOM' => 'Jean',
                    'EMAIL' => 'jean.dupont@example.com',
                    'POSTE' => 'Administrateur',
                    'MODULE' => 'Facture, Logement',
                ],
                [
                    'NOM' => 'Martin',
                    'PRENOM' => 'Sophie',
                    'EMAIL' => 'sophie.martin@example.com',
                    'POSTE' => 'Utilisateur',
                    'MODULE' => 'Logement',
                ],
            ];
            $metadata = [
                'entityName' => 'Utilisateur',
                'tableName' => 'Utilisateurs',
                'columns' => [
                    ['name' => 'NOM', 'label' => 'Nom', 'type' => 'string'],
                    ['name' => 'PRENOM', 'label' => 'Prénom', 'type' => 'string'],
                    ['name' => 'EMAIL', 'label' => 'Email', 'type' => 'string'],
                    ['name' => 'POSTE', 'label' => 'Poste occupé', 'type' => 'string'],
                    ['name' => 'MODULE', 'label' => 'Module', 'type' => 'string'],
                ]
            ];
            $pagination = [
                'page' => 1,
                'total' => count($data),
                'limit' => count($data),
                'totalPages' => 1
            ];
            return $this->render('admin/entity_view.html.twig', [
                'entityName' => $entityName,
                'metadata' => $metadata,
                'data' => $data,
                'pagination' => $pagination,
                'search' => ''
            ]);
        }

        $entityClass = $this->getEntityClass($entityName);
        
        if (!$entityClass) {
            throw $this->createNotFoundException('Entité non trouvée');
        }

        $page = (int) $request->query->get('page', 1);
        $search = $request->query->get('search', '');
        $limit = (int) $request->query->get('limit', 50);

        $metadata = $this->adminDataService->getEntityMetadata($entityClass);
        
        if ($search) {
            $result = $this->adminDataService->searchData($entityClass, $search, $page, $limit);
        } else {
            $result = $this->adminDataService->getAllData($entityClass, $page, $limit);
        }

        return $this->render('admin/entity_view.html.twig', [
            'entityName' => $entityName,
            'metadata' => $metadata,
            'data' => $result['data'],
            'pagination' => [
                'page' => $result['page'],
                'total' => $result['total'],
                'limit' => $result['limit'],
                'totalPages' => $result['totalPages']
            ],
            'search' => $search
        ]);
    }

    #[Route('/entity/{entityName}/detail/{id}', name: 'admin_entity_detail', methods: ['GET'])]
    public function viewEntityDetail(string $entityName, int $id): Response
    {
        $entityClass = $this->getEntityClass($entityName);
        
        if (!$entityClass) {
            throw $this->createNotFoundException('Entité non trouvée');
        }

        $entity = $this->adminDataService->getEntityById($entityClass, $id);
        
        if (!$entity) {
            throw $this->createNotFoundException('Enregistrement non trouvé');
        }

        return $this->render('admin/entity_detail.html.twig', [
            'entityName' => $entityName,
            'entity' => $entity,
            'metadata' => $this->adminDataService->getEntityMetadata($entityClass)
        ]);
    }

    #[Route('/entity/{entityName}/search', name: 'admin_entity_search', methods: ['GET'])]
    public function searchEntity(string $entityName, Request $request): JsonResponse
    {
        $entityClass = $this->getEntityClass($entityName);
        
        if (!$entityClass) {
            return new JsonResponse(['error' => 'Entité non trouvée'], 404);
        }

        $search = $request->query->get('q', '');
        $page = (int) $request->query->get('page', 1);
        $limit = (int) $request->query->get('limit', 50);

        $result = $this->adminDataService->searchData($entityClass, $search, $page, $limit);
        $metadata = $this->adminDataService->getEntityMetadata($entityClass);

        // Formater les données pour le JSON
        $formattedData = [];
        foreach ($result['data'] as $entity) {
            $row = [];
            foreach ($metadata['columns'] as $column) {
                $getter = 'get' . ucfirst($column['name']);
                if (method_exists($entity, $getter)) {
                    $value = $entity->$getter();
                    $row[$column['name']] = $this->formatValueForDisplay($value, $column['type']);
                }
            }
            $formattedData[] = $row;
        }

        return new JsonResponse([
            'data' => $formattedData,
            'pagination' => [
                'page' => $result['page'],
                'total' => $result['total'],
                'limit' => $result['limit'],
                'totalPages' => $result['totalPages']
            ],
            'searchTerm' => $search
        ]);
    }

    #[Route('/entity/{entityName}/export/{format}', name: 'admin_entity_export', methods: ['GET'])]
    public function exportEntity(string $entityName, string $format, Request $request): Response
    {
        $entityClass = $this->getEntityClass($entityName);
        
        if (!$entityClass) {
            throw $this->createNotFoundException('Entité non trouvée');
        }

        $search = $request->query->get('search', '');
        $page = (int) $request->query->get('page', 1);
        $limit = (int) $request->query->get('limit', 50);

        // Pour l'export, on récupère toutes les données (pas de pagination)
        if ($search) {
            $result = $this->adminDataService->searchData($entityClass, $search, 1, 10000);
        } else {
            $result = $this->adminDataService->getAllData($entityClass, 1, 10000);
        }

        $metadata = $this->adminDataService->getEntityMetadata($entityClass);
        $filename = sprintf('%s_%s.%s', 
            strtolower($metadata['entityName']), 
            date('Y-m-d_H-i-s'), 
            $format
        );

        if ($format === 'csv') {
            $content = $this->adminDataService->exportToCsv($entityClass, $result['data']);
            $response = new Response($content);
            $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        } elseif ($format === 'json') {
            $content = $this->adminDataService->exportToJson($entityClass, $result['data']);
            $response = new Response($content);
            $response->headers->set('Content-Type', 'application/json; charset=utf-8');
        } else {
            throw $this->createNotFoundException('Format d\'export non supporté');
        }

        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename
        );
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    #[Route('/login', name: 'login', methods: ['GET', 'POST'])]
    public function login(Request $request, SessionInterface $session): Response
    {
        $error = null;
        if ($request->isMethod('POST')) {
            $id = $request->request->get('id');
            $password = $request->request->get('password');
            if ($id === 'PCH' && $password === 'Ulis93200') {
                $session->set('is_admin', true);
                return $this->redirectToRoute('admin_dashboard');
            } else {
                $error = 'Identifiants invalides';
            }
        }
        return $this->render('login.html.twig', [
            'error' => $error
        ]);
    }

    #[Route('/logout', name: 'logout', methods: ['GET'])]
    public function logout(SessionInterface $session): Response
    {
        $session->clear();
        return $this->redirectToRoute('login');
    }

    private function getAllModules(): array
    {
        return [
            'Accueil',
            'Document BI',
            'Utilisateurs',
            'Débloquer MDP',
            'Administration',
        ];
    }

    #[Route('/admin/administration', name: 'admin_user_access', methods: ['GET', 'POST'])]
    public function userAccess(Request $request, SessionInterface $session): Response
    {
        if (!$this->isAdmin($session)) {
            return $this->redirectToRoute('login');
        }
        $userId = $request->get('user_id');
        $access = null;
        $allModules = $this->getAllModules();
        if ($userId) {
            // Simulation des droits d'accès
            $fakeAccess = [
                'jdupont' => ['Document BI', 'Utilisateurs', 'Administration'],
                'smartin' => ['Document BI'],
            ];
            $access = $fakeAccess[$userId] ?? [];
        }
        return $this->render('admin/user_access.html.twig', [
            'userId' => $userId,
            'access' => $access,
            'allModules' => $allModules,
        ]);
    }

    #[Route('/admin/unlock-password', name: 'admin_user_unlock', methods: ['GET', 'POST'])]
    public function unlockPassword(Request $request, SessionInterface $session): Response
    {
        if (!$this->isAdmin($session)) {
            return $this->redirectToRoute('login');
        }
        $userId = $request->get('user_id');
        $newPassword = null;
        $success = false;
        $currentPassword = null;
        // Simulation : mot de passe en dur pour jdupont et smartin
        $fakePasswords = [
            'jdupont' => 'azerty123',
            'smartin' => 'motdepasse',
        ];
        if ($request->isMethod('POST')) {
            $userId = $request->request->get('user_id');
            $action = $request->request->get('action');
            if ($userId && $action === 'unlock') {
                $fakePasswords[$userId] = 'azerty123';
                $success = true;
                $currentPassword = 'azerty123';
            } elseif ($userId && $action === 'modify') {
                $newPassword = $request->request->get('new_password');
                if ($newPassword) {
                    $fakePasswords[$userId] = $newPassword;
                    $success = true;
                    $currentPassword = $newPassword;
                }
            }
        } elseif ($userId && isset($fakePasswords[$userId])) {
            $currentPassword = $fakePasswords[$userId];
        }
        return $this->render('admin/unlock_password.html.twig', [
            'userId' => $userId,
            'currentPassword' => $currentPassword,
            'success' => $success
        ]);
    }

    #[Route('/admin/show-password', name: 'admin_user_show_password', methods: ['GET'])]
    public function showPassword(Request $request, SessionInterface $session): Response
    {
        if (!$this->isAdmin($session)) {
            return $this->redirectToRoute('login');
        }
        $userId = $request->get('user_id');
        $password = null;
        $fakePasswords = [
            'jdupont' => 'azerty123',
            'smartin' => 'motdepasse',
        ];
        if ($userId && isset($fakePasswords[$userId])) {
            $password = $fakePasswords[$userId];
        }
        return $this->render('admin/show_password.html.twig', [
            'userId' => $userId,
            'password' => $password
        ]);
    }

    /**
     * Récupère la classe d'entité à partir du nom
     */
    private function getEntityClass(string $entityName): ?string
    {
        $entityMap = [
            'editionbureautique' => EditionBureautique::class,
            'edition-bureautique' => EditionBureautique::class,
        ];

        return $entityMap[strtolower($entityName)] ?? null;
    }

    /**
     * Formate une valeur pour l'affichage
     */
    private function formatValueForDisplay($value, string $type): string
    {
        if ($value === null) {
            return '-';
        }

        return match($type) {
            'datetime' => $value instanceof \DateTimeInterface ? $value->format('d/m/Y H:i') : $value,
            'date' => $value instanceof \DateTimeInterface ? $value->format('d/m/Y') : $value,
            'boolean' => $value ? 'Oui' : 'Non',
            'text' => strlen($value) > 100 ? substr($value, 0, 100) . '...' : $value,
            default => (string) $value
        };
    }

    private function isAdmin(SessionInterface $session): bool
    {
        return $session->get('is_admin', false) === true;
    }
} 