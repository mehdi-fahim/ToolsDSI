<?php

namespace App\Controller;

use App\Entity\EditionBureautique;
use App\Service\EditionBureautiqueOracleService;
use App\Service\UtilisateurOracleService;
use App\Service\MotDePasseOracleService;
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
        private EditionBureautiqueOracleService $oracleService,
        private UtilisateurOracleService $utilisateurOracleService,
        private MotDePasseOracleService $motDePasseOracleService
    ) {}

    #[Route('', name: 'admin_dashboard', methods: ['GET'])]
    public function dashboard(SessionInterface $session): Response
    {
        if (!$this->isAuthenticated($session)) {
            return $this->redirectToRoute('login');
        }
        
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
    public function viewEntity(string $entityName, Request $request, SessionInterface $session): Response
    {
        if (!$this->isAuthenticated($session)) {
            return $this->redirectToRoute('login');
        }
        
        if (strtolower($entityName) === 'editionbureautique' || strtolower($entityName) === 'edition-bureautique') {
            // Récupérer les paramètres de pagination et recherche
            $page = (int) $request->query->get('page', 1);
            $search = $request->query->get('search', '');
            $limit = 20; // 20 lignes par page
            
            // Récupérer les données Oracle avec pagination et recherche
            $result = $this->oracleService->fetchEditions($search, $page, $limit);
            
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

        if (strtolower($entityName) === 'utilisateur' || strtolower($entityName) === 'utilisateurs') {
            // Récupérer les paramètres de pagination et recherche
            $page = (int) $request->query->get('page', 1);
            $search = $request->query->get('search', '');
            $limit = 20; // 20 lignes par page
            
            // Récupérer les données Oracle avec pagination et recherche
            $result = $this->utilisateurOracleService->fetchUtilisateurs($search, $page, $limit);
            
            // Adapter les métadonnées pour la vue
            $metadata = [
                'entityName' => 'Utilisateur',
                'tableName' => 'Oracle',
                'columns' => [
                    ['name' => 'NUM_TIERS', 'label' => 'Numéro Tiers', 'type' => 'string'],
                    ['name' => 'CODE_UTILISATEUR', 'label' => 'Code Utilisateur', 'type' => 'string'],
                    ['name' => 'GROUPE', 'label' => 'Groupe', 'type' => 'string'],
                    ['name' => 'NOM', 'label' => 'Nom', 'type' => 'string'],
                    ['name' => 'PRENOM', 'label' => 'Prénom', 'type' => 'string'],
                ]
            ];
            
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

    #[Route('/entity/utilisateur/detail/{id}', name: 'admin_user_detail', methods: ['GET'])]
    public function viewUserDetail($id, SessionInterface $session): Response
    {
        if (!$this->isAuthenticated($session)) {
            return $this->redirectToRoute('login');
        }
        $entity = $this->utilisateurOracleService->fetchUtilisateurById($id);
        if (!$entity) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }
        return $this->render('admin/user_detail.html.twig', [
            'entity' => $entity
        ]);
    }

    #[Route('/entity/editionbureautique/detail/{id}', name: 'admin_edition_bureautique_detail', methods: ['GET'])]
    public function viewEditionBureautiqueDetail($id, SessionInterface $session): Response
    {
        if (!$this->isAuthenticated($session)) {
            return $this->redirectToRoute('login');
        }
        $entity = $this->oracleService->fetchEditionById($id);
        if (!$entity) {
            throw $this->createNotFoundException('Document BI non trouvé');
        }
        return $this->render('admin/edition_bureautique_detail.html.twig', [
            'entity' => $entity
        ]);
    }

    #[Route('/entity/{entityName}/search', name: 'admin_entity_search', methods: ['GET'])]
    public function searchEntity(string $entityName, Request $request, SessionInterface $session): JsonResponse
    {
        if (!$this->isAuthenticated($session)) {
            return new JsonResponse(['error' => 'Non autorisé'], 401);
        }
        
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
    public function exportEntity(string $entityName, string $format, Request $request, SessionInterface $session): Response
    {
        if (!$this->isAuthenticated($session)) {
            return $this->redirectToRoute('login');
        }
        
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
            $userId = $request->request->get('id');
            $password = $request->request->get('password');
            
            // Vérifier d'abord le compte admin PCH
            if ($userId === 'PCH' && $password === 'Ulis93200') {
                // Connexion admin réussie
                $session->set('is_admin', true);
                $session->set('is_super_admin', true);
                $session->set('user_id', 'PCH');
                $session->set('user_nom', 'Plaine Commune Habitat');
                $session->set('user_prenom', 'Administrateur');
                $session->set('user_groupe', 'SUPER_ADMIN');
                
                return $this->redirectToRoute('admin_dashboard');
            }
            
            // Authentification via Oracle pour les autres utilisateurs
            $user = $this->utilisateurOracleService->authenticateUser($userId, $password);
            
            if ($user) {
                // Connexion utilisateur Oracle réussie
                $session->set('is_admin', true);
                $session->set('is_super_admin', false);
                $session->set('user_id', $user['CODE_UTILISATEUR']);
                $session->set('user_nom', $user['NOM']);
                $session->set('user_prenom', $user['PRENOM']);
                $session->set('user_groupe', $user['GROUPE']);
                
                return $this->redirectToRoute('admin_dashboard');
            } else {
                $error = 'Identifiants invalides. Vérifiez votre code utilisateur et mot de passe.';
            }
        }
        return $this->render('login.html.twig', [
            'error' => $error
        ]);
    }

    #[Route('/logout', name: 'logout', methods: ['GET'])]
    public function logout(SessionInterface $session): Response
    {
        // Nettoyer toutes les données de session
        $session->remove('is_admin');
        $session->remove('is_super_admin');
        $session->remove('user_id');
        $session->remove('user_nom');
        $session->remove('user_prenom');
        $session->remove('user_groupe');
        
        return $this->redirectToRoute('login');
    }

    #[Route('/admin/user/unlock', name: 'admin_user_unlock', methods: ['GET', 'POST'])]
    public function unlockPassword(Request $request, SessionInterface $session): Response
    {
        if (!$this->isAuthenticated($session)) {
            return $this->redirectToRoute('login');
        }

        $message = null;
        $error = null;
        $userInfo = null;
        $userId = $request->request->get('user_id', '');

        if ($request->isMethod('POST')) {
            if (empty($userId)) {
                $error = 'Veuillez saisir un code utilisateur.';
            } else {
                // Vérifier si l'utilisateur existe
                if (!$this->motDePasseOracleService->verifierUtilisateurExiste($userId)) {
                    $error = 'Utilisateur non trouvé dans la base de données.';
                } else {
                    $action = $request->request->get('action');
                    
                    switch ($action) {
                        case 'voir':
                            $userInfo = $this->motDePasseOracleService->getMotDePasseInfo($userId);
                            if ($userInfo) {
                                $message = 'Informations récupérées avec succès.';
                            } else {
                                $error = 'Impossible de récupérer les informations du mot de passe.';
                            }
                            break;
                            
                        case 'debloquer':
                            if ($this->motDePasseOracleService->debloquerMotDePasse($userId)) {
                                $message = 'Mot de passe débloqué avec succès.';
                                $userInfo = $this->motDePasseOracleService->getMotDePasseInfo($userId);
                            } else {
                                $error = 'Erreur lors du déblocage du mot de passe.';
                            }
                            break;
                            
                        case 'reinitialiser':
                            if ($this->motDePasseOracleService->reinitialiserMotDePasse($userId)) {
                                $message = 'Mot de passe réinitialisé avec succès (nouveau mot de passe: ZE19).';
                                $userInfo = $this->motDePasseOracleService->getMotDePasseInfo($userId);
                            } else {
                                $error = 'Erreur lors de la réinitialisation du mot de passe.';
                            }
                            break;
                            
                        default:
                            $error = 'Action non reconnue.';
                    }
                }
            }
        }

        return $this->render('admin/unlock_password.html.twig', [
            'userId' => $userId,
            'userInfo' => $userInfo,
            'message' => $message,
            'error' => $error
        ]);
    }

    private function isAuthenticated(SessionInterface $session): bool
    {
        return $session->get('is_admin') === true && $session->get('user_id');
    }

    private function isSuperAdmin(SessionInterface $session): bool
    {
        return $this->isAuthenticated($session) && $session->get('is_super_admin') === true;
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
        if (!$this->isSuperAdmin($session)) {
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