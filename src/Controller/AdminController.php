<?php

namespace App\Controller;

use App\Entity\EditionBureautique;
use App\Service\EditionBureautiqueOracleService;
use App\Service\UtilisateurOracleService;
use App\Service\MotDePasseOracleService;
use App\Service\LocataireOracleService;
use App\Service\ExtractionOracleService;
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
        private MotDePasseOracleService $motDePasseOracleService,
        private LocataireOracleService $locataireOracleService,
        private ExtractionOracleService $extractionOracleService
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
        $metadata = [
            'entityName' => 'Utilisateur',
            'columns' => [
                ['name' => 'NUM_TIERS', 'label' => 'Numéro Tiers'],
                ['name' => 'CODE_UTILISATEUR', 'label' => 'Code Utilisateur'],
                ['name' => 'GROUPE', 'label' => 'Groupe'],
                ['name' => 'NOM', 'label' => 'Nom'],
                ['name' => 'PRENOM', 'label' => 'Prénom'],
                ['name' => 'ETAT', 'label' => 'État'],
                ['name' => 'CODE_WEB', 'label' => 'Code Web'],
                ['name' => 'CODE_ULIS', 'label' => 'Code ULIS'],
                ['name' => 'DERNIERE_CONNEXION', 'label' => 'Dernière connexion'],
            ]
        ];
        return $this->render('admin/user_detail.html.twig', [
            'entity' => $entity,
            'entityName' => 'utilisateur',
            'metadata' => $metadata
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
        $metadata = [
            'entityName' => 'EditionBureautique',
            'columns' => [
                ['name' => 'NOM_BI', 'label' => 'Code BI'],
                ['name' => 'DOCUMENT_TYPE', 'label' => 'Type de document'],
                ['name' => 'DESCRIPTION_BI', 'label' => 'Description BI'],
                ['name' => 'NOM_DOCUMENT', 'label' => 'Nom du document'],
                ['name' => 'DESCRIPTION_PLUS', 'label' => 'Description complémentaire'],
            ]
        ];
        return $this->render('admin/edition_bureautique_detail.html.twig', [
            'entity' => $entity,
            'entityName' => 'EditionBureautique',
            'metadata' => $metadata
        ]);
    }

    #[Route('/entity/editionbureautique/download/{id}', name: 'admin_edition_bureautique_download', methods: ['GET'])]
    public function downloadEditionBureautiqueModel($id, SessionInterface $session): Response
    {
        if (!$this->isAuthenticated($session)) {
            return $this->redirectToRoute('login');
        }
        
        $entity = $this->oracleService->fetchEditionById($id);
        if (!$entity) {
            throw $this->createNotFoundException('Document BI non trouvé');
        }
        
        $nomDocument = $entity['NOM_DOCUMENT'] ?? $entity['NOM_BI'] ?? $id;
        $cheminFichier = "\\\\172.22.0.34\\Conteneur\\OPULISE\\bureautique\\" . $nomDocument;
        
        // Vérifier si le fichier existe
        if (!file_exists($cheminFichier)) {
            throw $this->createNotFoundException('Fichier modèle non trouvé sur le serveur');
        }
        
        // Créer la réponse de téléchargement
        $response = new Response(file_get_contents($cheminFichier));
        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . basename($nomDocument) . '"');
        
        return $response;
    }

    #[Route('/entity/editionbureautique/query/{id}', name: 'admin_edition_bureautique_query', methods: ['GET'])]
    public function viewEditionBureautiqueQuery($id, SessionInterface $session): Response
    {
        if (!$this->isAuthenticated($session)) {
            return $this->redirectToRoute('login');
        }
        
        $entity = $this->oracleService->fetchEditionById($id);
        if (!$entity) {
            throw $this->createNotFoundException('Document BI non trouvé');
        }
        
        // Récupérer la requête SQL depuis le service Oracle
        $query = $this->oracleService->getQueryForEdition($id);
        
        return $this->render('admin/edition_bureautique_query.html.twig', [
            'entity' => $entity,
            'query' => $query,
            'entityName' => 'EditionBureautique'
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

    #[Route('/admin/locataire', name: 'admin_locataire', methods: ['GET'])]
    public function locataire(Request $request, SessionInterface $session): Response
    {
        if (!$this->isAuthenticated($session)) {
            return $this->redirectToRoute('login');
        }

        $searchType = $request->query->get('search_type', '');
        $searchValue = $request->query->get('search_value', '');
        $page = (int) $request->query->get('page', 1);
        $limit = 20;

        $data = [];
        $pagination = [];
        $error = null;

        if ($searchType && $searchValue) {
            try {
                switch ($searchType) {
                    case 'esi':
                        $result = $this->locataireOracleService->searchByEsi($searchValue, $page, $limit);
                        break;
                    case 'contrat':
                        $result = $this->locataireOracleService->searchByContrat($searchValue, $page, $limit);
                        break;
                    case 'intitule':
                        $result = $this->locataireOracleService->searchByIntitule($searchValue, $page, $limit);
                        break;
                    default:
                        $error = 'Type de recherche non valide';
                        $result = ['data' => [], 'total' => 0, 'page' => 1, 'limit' => $limit, 'totalPages' => 0];
                }
                
                $data = $result['data'];
                $pagination = [
                    'page' => $result['page'],
                    'total' => $result['total'],
                    'limit' => $result['limit'],
                    'totalPages' => $result['totalPages']
                ];
            } catch (\Exception $e) {
                $error = 'Erreur de connexion à la base de données Oracle. Veuillez vérifier la configuration de la connexion.';
                $data = [];
                $pagination = ['page' => 1, 'total' => 0, 'limit' => $limit, 'totalPages' => 0];
            }
        }

        return $this->render('admin/locataire.html.twig', [
            'searchType' => $searchType,
            'searchValue' => $searchValue,
            'data' => $data,
            'pagination' => $pagination,
            'error' => $error
        ]);
    }

    #[Route('/admin/locataire/detail/{esi}', name: 'admin_locataire_detail', methods: ['GET'])]
    public function locataireDetail(string $esi, SessionInterface $session): Response
    {
        if (!$this->isAuthenticated($session)) {
            return $this->redirectToRoute('login');
        }

        try {
            $locataire = $this->locataireOracleService->getLocataireByEsi($esi);
            if (!$locataire) {
                throw $this->createNotFoundException('Locataire non trouvé');
            }
        } catch (\Exception $e) {
            return $this->render('admin/locataire_detail.html.twig', [
                'locataire' => null,
                'esi' => $esi,
                'error' => 'Erreur de connexion à la base de données Oracle. Veuillez vérifier la configuration de la connexion.'
            ]);
        }

        return $this->render('admin/locataire_detail.html.twig', [
            'locataire' => $locataire,
            'esi' => $esi,
            'error' => null
        ]);
    }

    #[Route('/admin/extraction', name: 'admin_extraction', methods: ['GET', 'POST'])]
    public function extraction(Request $request, SessionInterface $session): Response
    {
        if (!$this->isAuthenticated($session)) {
            return $this->redirectToRoute('login');
        }

        $groupeSi = $request->request->get('groupe_si', '');
        $selectedFields = $request->request->all('fields') ?? [];
        $error = null;
        $success = null;
        $query = null;

        if ($request->isMethod('POST')) {
            if (empty($groupeSi)) {
                $error = 'Veuillez saisir un groupe SI.';
            } elseif (empty($selectedFields)) {
                $error = 'Veuillez sélectionner au moins un champ à extraire.';
            } else {
                try {
                    $csv = $this->extractionOracleService->generateCsvForGroup($groupeSi, $selectedFields);
                    
                    if ($this->isSuperAdmin($session)) {
                        $query = $this->extractionOracleService->getQueryForDisplay($groupeSi, $selectedFields);
                    }
                    
                    $response = new Response($csv);
                    $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
                    $response->headers->set('Content-Disposition', 'attachment; filename="extraction_' . $groupeSi . '_' . date('Y-m-d_H-i-s') . '.csv"');
                    
                    return $response;
                } catch (\Exception $e) {
                    $error = 'Erreur lors de la génération du CSV: ' . $e->getMessage();
                }
            }
        }

        return $this->render('admin/extraction.html.twig', [
            'groupeSi' => $groupeSi,
            'selectedFields' => $selectedFields,
            'error' => $error,
            'success' => $success,
            'query' => $query,
            'isSuperAdmin' => $this->isSuperAdmin($session)
        ]);
    }

    #[Route('/admin/extraction/query', name: 'admin_extraction_query', methods: ['POST'])]
    public function extractionQuery(Request $request, SessionInterface $session): Response
    {
        if (!$this->isSuperAdmin($session)) {
            return new JsonResponse(['error' => 'Accès non autorisé'], 403);
        }

        $groupeSi = $request->request->get('groupe_si', '');
        $selectedFields = $request->request->all('fields') ?? [];

        // Permettre l'affichage de la requête même si le groupe SI est vide
        // Mais on garde la vérification pour les champs sélectionnés
        if (empty($selectedFields)) {
            return new JsonResponse(['error' => 'Veuillez sélectionner au moins un champ à extraire.'], 400);
        }

        try {
            $query = $this->extractionOracleService->getQueryForDisplay($groupeSi, $selectedFields);
            return new JsonResponse(['query' => $query]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
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