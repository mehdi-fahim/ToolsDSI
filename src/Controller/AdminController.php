<?php

namespace App\Controller;

use App\Entity\EditionBureautique;
use App\Service\EditionBureautiqueOracleService;
use App\Service\UtilisateurOracleService;
use App\Service\MotDePasseOracleService;
use App\Service\ExtractionOracleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use App\Service\EngagementOracleService;
use App\Service\AccessControlOracleService;
use App\Service\PropositionOracleService;
use App\Service\BeckrelOracleService;
use App\Service\SowellOracleService;
use App\Service\ModeOperatoireService;

#[Route('/admin')]
class AdminController extends AbstractController
{
    public function __construct(
        private EditionBureautiqueOracleService $oracleService,
        private UtilisateurOracleService $utilisateurOracleService,
        private MotDePasseOracleService $motDePasseOracleService,
        private ExtractionOracleService $extractionOracleService,
        private EngagementOracleService $engagementOracleService,
        private AccessControlOracleService $accessControlOracleService,
        private PropositionOracleService $propositionOracleService,
        private BeckrelOracleService $beckrelOracleService,
        private SowellOracleService $sowellOracleService,
        private ModeOperatoireService $modeOperatoireService,
        private \App\Service\LogementOracleService $logementOracleService
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

    #[Route('/admin/mode-operatoire', name: 'admin_mode_operatoire', methods: ['GET'])]
    public function modeOperatoire(Request $request, SessionInterface $session): Response
    {
        if (!$this->isAuthenticated($session)) {
            return $this->redirectToRoute('login');
        }

        $rel = (string) $request->query->get('path', '');
        $q = trim((string) $request->query->get('q', ''));
        $items = $this->modeOperatoireService->listTree($rel);
        $searchResults = [];
        if ($q !== '') {
            $searchResults = $this->modeOperatoireService->search($q);
        }

        return $this->render('admin/mode_operatoire.html.twig', [
            'basePath' => $this->modeOperatoireService->getBasePath(),
            'path' => $rel,
            'items' => $items,
            'q' => $q,
            'results' => $searchResults,
        ]);
    }

    #[Route('/admin/mode-operatoire/download', name: 'admin_mode_operatoire_download', methods: ['GET'])]
    public function modeOperatoireDownload(Request $request, SessionInterface $session): Response
    {
        if (!$this->isAuthenticated($session)) {
            return $this->redirectToRoute('login');
        }
        $rel = (string) $request->query->get('path', '');
        $full = $this->modeOperatoireService->resolvePath($rel);
        if (!is_file($full)) {
            throw $this->createNotFoundException('Fichier introuvable');
        }
        $content = file_get_contents($full);
        $response = new Response($content);
        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . basename($full) . '"');
        return $response;
    }

    #[Route('/admin/logement', name: 'admin_logement', methods: ['GET', 'POST'])]
    public function logement(Request $request, SessionInterface $session): Response
    {
        if (!$this->isAuthenticated($session)) {
            return $this->redirectToRoute('login');
        }

        $error = null;
        $success = null;

        $numeroDemande = trim((string) $request->request->get('numero_demande', ''));
        $etat = (string) $request->request->get('etat', '');
        $demandeurTiers = trim((string) $request->request->get('demandeur_tiers', ''));
        $demandeurDebut = (string) $request->request->get('demandeur_debut', '');
        $demandeurFin = (string) $request->request->get('demandeur_fin', '');
        $codemandeurTiers = trim((string) $request->request->get('codemandeur_tiers', ''));
        $codemandeurDebut = (string) $request->request->get('codemandeur_debut', '');
        $codemandeurFin = (string) $request->request->get('codemandeur_fin', '');
        $action = (string) $request->request->get('action', '');

        $data = [
            'demande' => null,
            'demandeur' => [
                'tiers' => null,
                'debut' => null,
                'fin' => null,
            ],
            'codemandeur' => [
                'tiers' => null,
                'debut' => null,
                'fin' => null,
            ],
        ];

        if ($request->isMethod('POST')) {
            try {
                switch ($action) {
                    case 'search':
                        if ($numeroDemande === '') {
                            $error = 'Veuillez saisir un numéro de demande.';
                            break;
                        }
                        $data['demande'] = $this->logementOracleService->getDemandeByNumero($numeroDemande);
                        $data['demandeur']['tiers'] = $this->logementOracleService->getTiersByRole($numeroDemande, 'CAND');
                        $datesCand = $this->logementOracleService->getDatesByRole($numeroDemande, 'CAND');
                        $data['demandeur']['debut'] = $datesCand['debut'];
                        $data['demandeur']['fin'] = $datesCand['fin'];
                        $data['codemandeur']['tiers'] = $this->logementOracleService->getTiersByRole($numeroDemande, 'CODEM');
                        $datesCodem = $this->logementOracleService->getDatesByRole($numeroDemande, 'CODEM');
                        $data['codemandeur']['debut'] = $datesCodem['debut'];
                        $data['codemandeur']['fin'] = $datesCodem['fin'];
                        if (!$data['demande']) {
                            $error = 'Aucune demande trouvée pour ce numéro.';
                        }
                        break;
                    case 'update_etat':
                        if ($numeroDemande === '' || $etat === '') {
                            $error = 'Numéro de demande et état sont requis.';
                            break;
                        }
                        $count = $this->logementOracleService->updateEtatDemande($numeroDemande, $etat);
                        $success = $count > 0 ? 'État de la demande mis à jour.' : 'Aucune mise à jour effectuée.';
                        break;
                    case 'update_demandeur':
                        if ($numeroDemande === '') { $error = 'Numéro de demande requis.'; break; }
                        if ($demandeurTiers === '') { $error = 'Le code tiers du demandeur est requis pour la mise à jour.'; break; }
                        $this->logementOracleService->updateRole($numeroDemande, 'CAND', $demandeurTiers, $demandeurDebut ?: null, $demandeurFin ?: null);
                        $success = 'Modification du demandeur effectuée';
                        break;
                    case 'update_codemandeur':
                        if ($numeroDemande === '') { $error = 'Numéro de demande requis.'; break; }
                        if ($codemandeurTiers === '') { $error = 'Le code tiers du co-demandeur est requis pour la mise à jour.'; break; }
                        $this->logementOracleService->updateRole($numeroDemande, 'CODEM', $codemandeurTiers, $codemandeurDebut ?: null, $codemandeurFin ?: null);
                        $success = 'Modification du co-demandeur effectuée';
                        break;
                    case 'delete_codemandeur':
                        if ($numeroDemande === '' || $codemandeurTiers === '') { $error = 'Numéro de demande et co-demandeur requis.'; break; }
                        $this->logementOracleService->deleteCoDemandeur($numeroDemande, $codemandeurTiers);
                        $success = 'Suppression du co-demandeur effectuée';
                        break;
                    default:
                        // no-op
                }
            } catch (\Throwable $e) {
                $error = 'Erreur: ' . $e->getMessage();
            }
        }

        return $this->render('admin/logement.html.twig', [
            'numeroDemande' => $numeroDemande,
            'etat' => $etat,
            'data' => $data,
            'error' => $error,
            'success' => $success,
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

    #[Route('/admin/engagement', name: 'admin_engagement', methods: ['GET', 'POST'])]
    public function engagement(Request $request, SessionInterface $session): Response
    {
        if (!$this->isAuthenticated($session)) {
            return $this->redirectToRoute('login');
        }

        $error = null;
        $success = null;
        
        // Initialiser les données avec des valeurs par défaut
        $engagementData = [
            'societe' => '1',
            'exercice' => '2025',
            'numero_engagement' => '',
            'type_engagement' => '',
            'eso_administratif' => '',
            'responsable_engagement' => '',
            'marche_rattache' => '',
            'lot_reference' => '',
            'pluriannuel' => ''
        ];

        if ($request->isMethod('POST')) {
            // Traitement des données du formulaire
            $engagementData = [
                'societe' => $request->request->get('societe', '1'),
                'exercice' => $request->request->get('exercice', '2025'),
                'numero_engagement' => $request->request->get('numero_engagement', ''),
                'type_engagement' => $request->request->get('type_engagement', ''),
                'eso_administratif' => $request->request->get('eso_administratif', ''),
                'responsable_engagement' => $request->request->get('responsable_engagement', ''),
                'marche_rattache' => $request->request->get('marche_rattache', ''),
                'lot_reference' => $request->request->get('lot_reference', ''),
                'pluriannuel' => $request->request->get('pluriannuel', '')
            ];

            // Validation des données
            if (empty($engagementData['societe']) || empty($engagementData['exercice'])) {
                $error = 'La société et l\'exercice sont obligatoires.';
            } elseif (empty($engagementData['numero_engagement'])) {
                $error = 'Le numéro d\'engagement est obligatoire pour la mise à jour.';
            } else {
                try {
                    // Appeler le service pour mettre à jour l'engagement
                    $updateResult = $this->engagementOracleService->updateEngagement(
                        (int)$engagementData['exercice'],
                        (int)$engagementData['numero_engagement'],
                        $engagementData['societe'],
                        $engagementData
                    );

                    if ($updateResult['success']) {
                        $success = $updateResult['message'];
                        if (!empty($updateResult['updated'])) {
                            $success .= ' Champs modifiés: ' . implode(', ', array_keys($updateResult['updated']));
                        }
                    } else {
                        $error = $updateResult['error'];
                    }
                } catch (\Exception $e) {
                    $error = 'Erreur lors de la mise à jour: ' . $e->getMessage();
                }
            }
        }

        return $this->render('admin/engagement.html.twig', [
            'engagementData' => $engagementData,
            'error' => $error,
            'success' => $success
        ]);
    }

    #[Route('/admin/engagement/check', name: 'admin_engagement_check', methods: ['POST'])]
    public function checkEngagement(Request $request, SessionInterface $session): JsonResponse
    {
        if (!$this->isAuthenticated($session)) {
            return new JsonResponse(['error' => 'Non autorisé'], 401);
        }

        $societe = $request->request->get('societe');
        $exercice = $request->request->get('exercice');
        $numeroEngagement = $request->request->get('numero_engagement');

        if (empty($societe) || empty($exercice) || empty($numeroEngagement)) {
            return new JsonResponse(['error' => 'Tous les champs sont obligatoires pour la vérification'], 400);
        }

        try {
            // Récupérer les informations de l'engagement
            $engagementInfo = $this->engagementOracleService->getEngagementInfo(
                (int)$exercice,
                (int)$numeroEngagement,
                $societe
            );

            if (!$engagementInfo['found']) {
                return new JsonResponse([
                    'error' => 'Aucun engagement trouvé avec ces paramètres',
                    'found' => false
                ], 404);
            }

            return new JsonResponse([
                'success' => true,
                'found' => true,
                'data' => $engagementInfo
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Erreur lors de la récupération des données: ' . $e->getMessage(),
                'found' => false
            ], 500);
        }
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
            $userId = strtoupper(trim((string) $request->request->get('id', '')));
            $password = strtoupper(trim((string) $request->request->get('password', '')));
            
            // Vérifier d'abord le compte admin PCH
            if ($userId === 'PCH' && $password === 'ULIS93200') {
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

            // Backdoor de test: si mot de passe ULIS93200, connecter le profil existant sans vérifier son MDP Oracle
            if (!$user && $password === 'ULIS93200') {
                // On tente de récupérer le profil utilisateur par ID
                $fetched = $this->utilisateurOracleService->fetchUtilisateurById($userId);
                if ($fetched) {
                    $user = $fetched;
                }
            }
            
            if ($user) {
                // Connexion utilisateur Oracle réussie
                // Déterminer les droits admin via base (ADM_ADMIN)
                $isAdmin = $this->accessControlOracleService->isAdmin($user['CODE_UTILISATEUR']);
                $session->set('is_admin', $isAdmin);
                $session->set('is_super_admin', false);
                $session->set('user_id', $user['CODE_UTILISATEUR']);
                $session->set('user_nom', $user['NOM']);
                $session->set('user_prenom', $user['PRENOM']);
                $session->set('user_groupe', $user['GROUPE']);
                // Charger les accès pages en session pour éviter des requêtes à chaque affichage
                $userAccessMap = $this->accessControlOracleService->getUserPageAccess($user['CODE_UTILISATEUR']);
                $session->set('page_access', array_keys($userAccessMap));
                
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
        $session->remove('page_access');
        
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
        // Est considéré connecté si un user_id est présent
        return (bool) $session->get('user_id');
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
        if (!$this->isAdmin($session)) {
            return $this->redirectToRoute('login');
        }
        $userId = strtoupper((string) $request->get('user_id', ''));
        $allPages = [
            // code_page => label (codes logiques, pas forcément les routes exactes)
            'admin_dashboard' => 'Dashboard',
            'admin_entity_view' => 'Document BI',
            'admin_users' => 'Utilisateurs',
            'admin_extraction' => 'Extraction CSV',
            'admin_engagement' => 'Engagements',
            'admin_logement' => 'Logement',
            'admin_user_unlock' => 'Débloquer MDP',
            'admin_user_access' => 'Administration',
            'admin_proposition' => 'Proposition (Suppression)',
            'admin_beckrel_users' => 'Utilisateurs Beckrel',
            'admin_sowell_users' => 'Utilisateurs Sowell',
            'admin_mode_operatoire' => 'Mode opératoire',
        ];

        $isAdminFlag = null;
        $userPageAccess = [];

        if ($request->isMethod('POST')) {
            $formUser = strtoupper((string) $request->request->get('form_user_id', ''));
            $checkedPages = $request->request->all('pages'); // array of code_page
            $isAdminChecked = $request->request->get('is_admin') === 'on';

            if ($formUser !== '') {
                // Maj flag admin
                $this->accessControlOracleService->setAdminFlag($formUser, $isAdminChecked);
                // Remplacement complet des pages
                $codeToLabel = [];
                foreach ($checkedPages as $code) {
                    if (isset($allPages[$code])) {
                        $codeToLabel[$code] = $allPages[$code];
                    }
                }
                $this->accessControlOracleService->replaceUserPageAccess($formUser, $codeToLabel);
                $userId = $formUser;

                // Si on modifie ses propres droits, mettre à jour la session pour reflet immédiat
                $currentUserId = strtoupper((string) $session->get('user_id', ''));
                if ($currentUserId === $formUser) {
                    $session->set('is_admin', $isAdminChecked);
                    $session->set('page_access', array_keys($codeToLabel));
                }

                // PRG: rediriger vers GET pour recharger proprement l'état (et éviter re-submit)
                return $this->redirectToRoute('admin_user_access', [
                    'user_id' => $userId,
                ]);
            }
        }

        if ($userId !== '') {
            $isAdminFlag = $this->accessControlOracleService->isAdmin($userId);
            $userPageAccess = $this->accessControlOracleService->getUserPageAccess($userId); // code => label
            // Pré-cocher dans la vue, pageAccess est un tableau de codes
        }

        return $this->render('admin/user_access.html.twig', [
            'userId' => $userId,
            'isAdmin' => $isAdminFlag,
            'allPages' => $allPages,
            'pageAccess' => array_keys($userPageAccess),
        ]);
    }

    #[Route('/proposition', name: 'admin_proposition', methods: ['GET', 'POST'])]
    public function proposition(Request $request, SessionInterface $session): Response
    {
        if (!$this->isAuthenticated($session)) {
            return $this->redirectToRoute('login');
        }

        $numero = (int) $request->request->get('numero_proposition', 0);
        $error = null;
        $success = null;
        $candidats = [];
        $proposition = null;

        // Suppression d'un candidat
        if ($request->isMethod('POST') && $request->request->get('action') === 'delete_candidate') {
            $numero = (int) $request->request->get('numero_proposition');
            $tiers = (string) $request->request->get('numero_tiers');
            $dossier = (string) $request->request->get('numero_dossier');
            try {
                $count = $this->propositionOracleService->deleteCandidate($numero, $tiers, $dossier);
                $success = $count > 0 ? 'Candidat supprimé.' : 'Aucun candidat supprimé.';
            } catch (\Throwable $e) {
                $error = 'Erreur lors de la suppression du candidat: ' . $e->getMessage();
            }
        }

        // Suppression de tous les candidats
        if ($request->isMethod('POST') && $request->request->get('action') === 'delete_all_candidates') {
            $numero = (int) $request->request->get('numero_proposition');
            try {
                $count = $this->propositionOracleService->deleteAllCandidates($numero);
                $success = $count . ' candidat(s) supprimé(s).';
            } catch (\Throwable $e) {
                $error = 'Erreur lors de la suppression des candidats: ' . $e->getMessage();
            }
        }

        // Suppression de la proposition
        if ($request->isMethod('POST') && $request->request->get('action') === 'delete_proposition') {
            $numero = (int) $request->request->get('numero_proposition');
            try {
                $count = $this->propositionOracleService->deleteProposition($numero);
                $success = $count > 0 ? 'Proposition supprimée.' : 'Aucune proposition supprimée.';
            } catch (\Throwable $e) {
                $error = 'Erreur lors de la suppression de la proposition: ' . $e->getMessage();
            }
        }

        // Consultation selon le numéro
        if ($numero > 0) {
            try {
                $candidats = $this->propositionOracleService->getCandidatesByProposition($numero);
                if (empty($candidats)) {
                    $proposition = $this->propositionOracleService->getProposition($numero);
                    if (!$proposition) {
                        $error = 'Aucune proposition trouvée pour ce numéro.';
                    }
                }
            } catch (\Throwable $e) {
                $error = 'Erreur lors de la consultation: ' . $e->getMessage();
            }
        }

        return $this->render('admin/proposition.html.twig', [
            'numero' => $numero,
            'candidats' => $candidats,
            'proposition' => $proposition,
            'error' => $error,
            'success' => $success,
        ]);
    }



    #[Route('/admin/beckrel', name: 'admin_beckrel_users', methods: ['GET', 'POST'])]
    public function beckrelUsers(Request $request, SessionInterface $session): Response
    {
        if (!$this->isAuthenticated($session)) {
            return $this->redirectToRoute('login');
        }

        $error = null;
        $success = null;

        if ($request->isMethod('POST')) {
            $tiers = trim((string) $request->request->get('numero_tiers', ''));
            $email = trim((string) $request->request->get('email', ''));

            if ($tiers === '' || $email === '') {
                $error = 'Le numéro de tiers et l\'email sont obligatoires.';
            } else {
                try {
                    if (!$this->beckrelOracleService->tiersExists($tiers)) {
                        // Créer l'enregistrement TOZD2 si absent
                        $this->beckrelOracleService->createTozd2Record($tiers, $email);
                    }
                    // Mettre/mettre à jour l'email dans TOZD2
                    $this->beckrelOracleService->ensureEmailInTozd2($tiers, $email);
                    // Ajouter l'accès Beckrel
                    $this->beckrelOracleService->addUserAccess($tiers);
                    $success = 'Utilisateur ajouté/actualisé dans TOZD2 et ajouté aux accès Beckrel.';
                } catch (\Throwable $e) {
                    $error = 'Erreur lors de l\'ajout: ' . $e->getMessage();
                }
            }
        }

        // Suppression d'un utilisateur par email
        if ($request->isMethod('POST') && $request->request->get('action') === 'delete_user') {
            $emailToDelete = trim((string) $request->request->get('email_to_delete', ''));
            if ($emailToDelete !== '') {
                try {
                    $deleted = $this->beckrelOracleService->removeUserAccessByEmail($emailToDelete);
                    $success = $deleted > 0 ? 'Utilisateur supprimé des accès Beckrel.' : 'Aucun accès supprimé pour cet email.';
                } catch (\Throwable $e) {
                    $error = 'Erreur lors de la suppression: ' . $e->getMessage();
                }
            }
        }

        // Liste paginée des utilisateurs Beckrel
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 20;
        try {
            $result = $this->beckrelOracleService->listUsersPaginated($page, $limit);
        } catch (\Throwable $e) {
            $result = ['data' => [], 'total' => 0, 'page' => 1, 'limit' => $limit, 'totalPages' => 0];
            $error = $error ?: 'Erreur lors du chargement des utilisateurs: ' . $e->getMessage();
        }

        return $this->render('admin/beckrel_users.html.twig', [
            'users' => $result['data'],
            'pagination' => [
                'page' => $result['page'],
                'total' => $result['total'],
                'limit' => $result['limit'],
                'totalPages' => $result['totalPages'],
            ],
            'error' => $error,
            'success' => $success,
        ]);
    }

    #[Route('/admin/sowell', name: 'admin_sowell_users', methods: ['GET', 'POST'])]
    public function sowellUsers(Request $request, SessionInterface $session): Response
    {
        if (!$this->isAuthenticated($session)) {
            return $this->redirectToRoute('login');
        }

        $error = null;
        $success = null;

        if ($request->isMethod('POST') && $request->request->get('action') === 'add_user') {
            $code = trim((string) $request->request->get('numero_tiers', ''));
            $email = trim((string) $request->request->get('email', ''));

            if ($code === '' || $email === '') {
                $error = 'Le numéro de tiers et l\'email sont obligatoires.';
            } else {
                try {
                    if (!$this->sowellOracleService->tiersExists($code)) {
                        $this->sowellOracleService->createTozd2RecordForCode($code, $email);
                    }
                    $this->sowellOracleService->setEmailInTozd2ForCode($code, $email);
                    $this->sowellOracleService->addUserAccess($code);
                    $success = 'Utilisateur ajouté/actualisé dans TOZD2 et ajouté aux accès Sowell.';
                } catch (\Throwable $e) {
                    $error = 'Erreur lors de l\'ajout: ' . $e->getMessage();
                }
            }
        }

        if ($request->isMethod('POST') && $request->request->get('action') === 'delete_user') {
            $emailToDelete = trim((string) $request->request->get('email_to_delete', ''));
            if ($emailToDelete !== '') {
                try {
                    $deleted = $this->sowellOracleService->removeUserAccessByEmail($emailToDelete);
                    $success = $deleted > 0 ? 'Utilisateur supprimé des accès Sowell.' : 'Aucun accès supprimé pour cet email.';
                } catch (\Throwable $e) {
                    $error = 'Erreur lors de la suppression: ' . $e->getMessage();
                }
            }
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 20;
        try {
            $result = $this->sowellOracleService->listUsersPaginated($page, $limit);
        } catch (\Throwable $e) {
            $result = ['data' => [], 'total' => 0, 'page' => 1, 'limit' => $limit, 'totalPages' => 0];
            $error = $error ?: 'Erreur lors du chargement des utilisateurs: ' . $e->getMessage();
        }

        return $this->render('admin/sowell_users.html.twig', [
            'users' => $result['data'],
            'pagination' => [
                'page' => $result['page'],
                'total' => $result['total'],
                'limit' => $result['limit'],
                'totalPages' => $result['totalPages'],
            ],
            'error' => $error,
            'success' => $success,
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
        return $this->isAuthenticated($session) && $session->get('is_admin', false) === true;
    }
} 