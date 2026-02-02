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
use App\Service\UserActionLogger;
use App\Service\LogViewerService;
use App\Service\DetailedUserActionLogger;
use App\Service\ImportODService;
use App\Service\ImportODOracleService;
use App\Service\ListeAffectationOracleService;
use App\Service\InseeOracleService;
use App\Service\IntitulesCBOracleService;
use App\Service\PlansOfficeOracleService;
use App\Service\BiFieldDescriptionService;
use App\Service\EnvironmentContext;

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
        private \App\Service\LogementOracleService $logementOracleService,
        private UserActionLogger $userActionLogger,
        private LogViewerService $logViewerService,
        private \App\Service\ReouvExemptesOracleService $reouvExemptesOracleService,
        private ImportODService $importODService,
        private ImportODOracleService $importODOracleService,
        private ListeAffectationOracleService $listeAffectationOracleService,
        private InseeOracleService $inseeOracleService,
        private IntitulesCBOracleService $intitulesCBOracleService,
        private PlansOfficeOracleService $plansOfficeOracleService,
        private BiFieldDescriptionService $biFieldDescriptionService,
        private EnvironmentContext $environmentContext,
        private ?DetailedUserActionLogger $detailedUserActionLogger = null
    ) {}

    #[Route('', name: 'admin_dashboard', methods: ['GET'])]
    public function dashboard(SessionInterface $session, Request $request): Response
    {
        if (!$this->isAuthenticated($session)) {
            return $this->redirectToRoute('login');
        }

        // Log de l'accès au dashboard
        if ($this->detailedUserActionLogger) {
            $this->detailedUserActionLogger->logPageAccess(
                'Dashboard',
                $session->get('user_id'),
                $request->getClientIp()
            );
        }

        // Forcer un log de test pour s'assurer que l'utilisateur apparaît dans la liste
        $this->userActionLogger->logUserLogin(
            $session->get('user_id', 'UNKNOWN'),
            $request->getClientIp(),
            true
        );
        
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

    #[Route('/liste-affectation/missing', name: 'admin_liste_affectation_missing', methods: ['GET'])]
    public function listeAffectationMissing(Request $request, SessionInterface $session): Response
    {
        if (!$this->isAuthenticated($session)) {
            return $this->redirectToRoute('login');
        }

        $page = (int) $request->query->get('page', 1);
        $search = trim((string) $request->query->get('search', ''));
        $esoGardien = trim((string) $request->query->get('eso', ''));
        $gardien = trim((string) $request->query->get('gardien', ''));
        $limit = 50;
        $hasLotSearch = $search !== '' && strlen($search) >= 5;
        $hasEsoSearch = $esoGardien !== '' && strlen($esoGardien) >= 3;
        $hasGardienSearch = $gardien !== '' && strlen($gardien) >= 3;
        $hasFilters = $hasLotSearch || $hasEsoSearch || $hasGardienSearch;

        if (!$hasFilters) {
            return $this->redirectToRoute('admin_liste_affectation');
        }

        $searchFilter = $hasLotSearch ? $search : '';
        $esoFilter = $hasEsoSearch ? $esoGardien : '';
        $gardienFilter = $hasGardienSearch ? $gardien : '';

        $result = $this->listeAffectationOracleService->getListeAffectations(
            $searchFilter,
            '',
            $page,
            $limit,
            true,
            $esoFilter,
            $gardienFilter
        );

        return $this->render('admin/liste_affectation.html.twig', [
            'data' => $result['data'],
            'pagination' => [
                'page' => $result['page'],
                'total' => $result['total'],
                'limit' => $result['limit'],
                'totalPages' => $result['totalPages']
            ],
            'search' => $search,
            'eso' => $esoGardien,
            'gardien' => $gardien,
            'groupe' => '',
            'groupes' => [],
            'stats' => null,
            'hasSearch' => true,
            'showingMissing' => true
        ]);
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
        
        $etatActuel = null;

        if ($request->isMethod('POST')) {
            try {
                switch ($action) {
                    case 'search':
                        if ($numeroDemande === '') {
                            $error = 'Veuillez saisir un numéro de demande.';
                            break;
                        }
                        $data['demande'] = $this->logementOracleService->getDemandeByNumero($numeroDemande);
                        if ($data['demande']) {
                            // Récupérer le statut actuel de la demande
                            $etatActuel = $data['demande']['ACDEM_ETA'] ?? null;
                            
                            $data['demandeur']['tiers'] = $this->logementOracleService->getTiersByRole($numeroDemande, 'CAND');
                            $datesCand = $this->logementOracleService->getDatesByRole($numeroDemande, 'CAND');
                            $data['demandeur']['debut'] = $datesCand['debut'];
                            $data['demandeur']['fin'] = $datesCand['fin'];
                            $data['codemandeur']['tiers'] = $this->logementOracleService->getTiersByRole($numeroDemande, 'CODEM');
                            $datesCodem = $this->logementOracleService->getDatesByRole($numeroDemande, 'CODEM');
                            $data['codemandeur']['debut'] = $datesCodem['debut'];
                            $data['codemandeur']['fin'] = $datesCodem['fin'];
                        } else {
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
                        
                        // Log de l'action
                        $this->userActionLogger->logDataModification('LOGMNT', 'UPDATE_ETAT', [
                            'numero_demande' => $numeroDemande,
                            'nouvel_etat' => $etat
                        ], $session->get('user_id'));
                        break;
                    case 'update_demandeur':
                        if ($numeroDemande === '') { $error = 'Numéro de demande requis.'; break; }
                        if ($demandeurTiers === '') { $error = 'Le code tiers du demandeur est requis pour la mise à jour.'; break; }
                        $this->logementOracleService->updateRole($numeroDemande, 'CAND', $demandeurTiers, $demandeurDebut ?: null, $demandeurFin ?: null);
                        // Si on a supprimé la date de fin pour ce CAND, clôturer les autres CAND de la même demande
                        if ($demandeurFin === '' || $demandeurFin === null) {
                            $this->logementOracleService->closeOtherCandidatesAndKeepOneOpen($numeroDemande, $demandeurTiers);
                        }
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
            'etat' => $etatActuel ?? $etat,
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
        $fields = [];
        try {
            $documentName = trim((string) ($entity['NOM_BI'] ?? $entity['NOM_DOCUMENT'] ?? ''));
            if ($documentName !== '') {
                $fields = $this->biFieldDescriptionService->getFieldsForDocument($documentName);
            }
        } catch (\Throwable $e) {
            $fields = [];
        }

        return $this->render('admin/edition_bureautique_detail.html.twig', [
            'entity' => $entity,
            'entityName' => 'EditionBureautique',
            'metadata' => $metadata,
            'biFields' => $fields
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
            'pluriannuel' => $session->get('engagement_pluriannuel', '')
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
            
            // Conserver la valeur pluriannuel dans la session pour la prochaine requête
            $session->set('engagement_pluriannuel', $engagementData['pluriannuel']);

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
                        
                        // Log de l'action
                        $this->userActionLogger->logDataModification('ENGAGEMENT', 'UPDATE', [
                            'exercice' => $engagementData['exercice'],
                            'numero_engagement' => $engagementData['numero_engagement'],
                            'societe' => $engagementData['societe'],
                            'updated_fields' => $updateResult['updated'] ?? []
                        ], $session->get('user_id'));

                        // Log détaillé
                        if ($this->detailedUserActionLogger) {
                            $this->detailedUserActionLogger->logFormSubmit(
                                'Engagement Update',
                                'Engagement Page',
                                $engagementData,
                                $session->get('user_id'),
                                $request->getClientIp()
                            );
                        }
                    } else {
                        $error = $updateResult['error'];
                        
                        // Log de l'erreur
                        if ($this->detailedUserActionLogger) {
                            $this->detailedUserActionLogger->logUserError(
                                $updateResult['error'],
                                'Engagement Page',
                                $engagementData,
                                $session->get('user_id'),
                                $request->getClientIp()
                            );
                        }
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
                // Toujours repasser en prod à chaque connexion
                $this->environmentContext->setEnvironment('prod');
                
                // Log de la connexion admin
                $this->userActionLogger->logUserLogin('PCH', $request->getClientIp(), true);
                
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

                // Toujours repasser en prod à chaque connexion
                $this->environmentContext->setEnvironment('prod');
                
                // Log de la connexion utilisateur
                $this->userActionLogger->logUserLogin($user['CODE_UTILISATEUR'], $request->getClientIp(), true);
                
                return $this->redirectToRoute('admin_dashboard');
            } else {
                // Log de la tentative de connexion échouée
                $this->userActionLogger->logUserLogin($userId, $request->getClientIp(), false);
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
        // Log de la déconnexion
        $userId = $session->get('user_id');
        if ($userId) {
            $this->userActionLogger->logUserLogout($userId);
        }
        
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

    #[Route('/admin/environment/switch', name: 'admin_environment_switch', methods: ['POST'])]
    public function switchEnvironment(Request $request, SessionInterface $session): Response
    {
        if (!$this->isAdmin($session)) {
            return $this->redirectToRoute('login');
        }

        $env = (string) $request->request->get('environment', 'prod');
        $this->environmentContext->setEnvironment($env);

        $referer = (string) $request->headers->get('referer', '');
        if ($referer !== '') {
            return new RedirectResponse($referer);
        }

        return $this->redirectToRoute('admin_dashboard');
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
        $userId = (string) $request->request->get('user_id', '');

        if ($userId === '') {
            $userId = (string) $request->query->get('user_id', '');
        }

        $userId = strtoupper(trim($userId));

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

    #[Route('/admin/change-password', name: 'admin_change_password', methods: ['POST'])]
    public function changePassword(Request $request, SessionInterface $session): JsonResponse
    {
        if (!$this->isAuthenticated($session)) {
            return new JsonResponse(['error' => 'Session expirée. Merci de vous reconnecter.'], 401);
        }

        $newPassword = trim((string) $request->request->get('new_password', ''));
        $confirmPassword = trim((string) $request->request->get('confirm_password', ''));

        if ($newPassword === '' || $confirmPassword === '') {
            return new JsonResponse(['error' => 'Merci de saisir et confirmer le nouveau mot de passe.'], 400);
        }

        if ($newPassword !== $confirmPassword) {
            return new JsonResponse(['error' => 'Les mots de passe ne correspondent pas.'], 400);
        }

        $userId = (string) $session->get('user_id', '');

        if ($userId === '') {
            return new JsonResponse(['error' => 'Utilisateur introuvable en session.'], 400);
        }

        if (strlen($newPassword) < 4) {
            return new JsonResponse(['error' => 'Le mot de passe doit contenir au moins 4 caractères.'], 400);
        }

        try {
            $changed = $this->motDePasseOracleService->changerMotDePasse($userId, $newPassword);
            if (!$changed) {
                return new JsonResponse(['error' => 'Impossible de mettre à jour le mot de passe.'], 500);
            }

            $this->userActionLogger->logDataModification('UTILISATEUR', 'CHANGE_PASSWORD', [
                'user_id' => $userId
            ], $userId);

            return new JsonResponse(['success' => 'Votre mot de passe a été mis à jour avec succès.']);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Erreur lors de la mise à jour du mot de passe: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/import/paiement-cb', name: 'admin_import_paiement_cb', methods: ['GET', 'POST'])]
    public function paiementCb(Request $request, SessionInterface $session): Response
    {
        if (!$this->isAuthenticated($session)) {
            return $this->redirectToRoute('login');
        }

        $existingSearch = trim((string) $request->query->get('existing_search', ''));
        $missingSearch = trim((string) $request->query->get('missing_search', ''));
        $existingPage = max(1, (int) $request->query->get('existing_page', 1));
        $missingPage = max(1, (int) $request->query->get('missing_page', 1));
        $activeTab = (string) $request->query->get('tab', 'existing');
        if (!in_array($activeTab, ['existing', 'missing'], true)) {
            $activeTab = 'existing';
        }

        $perPage = 25;

        if ($request->isMethod('POST')) {
            $action = (string) $request->request->get('action', '');
            $existingSearch = trim((string) $request->request->get('existing_search', $existingSearch));
            $missingSearch = trim((string) $request->request->get('missing_search', $missingSearch));
            $existingPage = max(1, (int) $request->request->get('existing_page', $existingPage));
            $missingPage = max(1, (int) $request->request->get('missing_page', $missingPage));
            $activeTab = (string) $request->request->get('tab', $activeTab);
            if (!in_array($activeTab, ['existing', 'missing'], true)) {
                $activeTab = 'existing';
            }

            $rowsToInsert = [];
            if ($action === 'import_selected') {
                $selected = $request->request->all('selected');
                if (empty($selected)) {
                    $this->addFlash('error', 'Sélectionnez au moins un intitulé à importer.');
                } else {
                    foreach ($selected as $payload) {
                        $parts = explode('|', (string) $payload, 3);
                        if (count($parts) === 3) {
                            [$caintNum, $nom, $mdp] = $parts;
                            $rowsToInsert[] = [
                                'CAINT_NUM' => trim($caintNum),
                                'NOM' => trim($nom),
                                'MDP' => trim($mdp),
                            ];
                        }
                    }
                    if (empty($rowsToInsert)) {
                        $this->addFlash('error', 'Aucune donnée valide sélectionnée pour l\'import.');
                    }
                }
            } elseif ($action === 'import_all') {
                try {
                    $rowsToInsert = $this->intitulesCBOracleService->fetchMissingIntitules($missingSearch);
                    if (empty($rowsToInsert)) {
                        $this->addFlash('info', 'Aucun intitulé manquant à importer.');
                    }
                } catch (\Throwable $e) {
                    $this->addFlash('error', 'Erreur lors de la récupération des intitulés manquants: ' . $e->getMessage());
                }
            }

            if (!empty($rowsToInsert)) {
                try {
                    $count = $this->intitulesCBOracleService->insertIntitules($rowsToInsert);
                    if ($count > 0) {
                        $this->userActionLogger->logDataModification('PAIEMENT_CB', 'IMPORT', [
                            'count' => $count,
                        ], (string) $session->get('user_id'));
                        $this->addFlash('success', sprintf('%d intitulé(s) importé(s) avec succès.', $count));
                    } else {
                        $this->addFlash('info', 'Aucun nouvel intitulé importé (données déjà présentes).');
                    }
                } catch (\Throwable $e) {
                    $this->addFlash('error', 'Erreur lors de l\'import: ' . $e->getMessage());
                }
            }

            return $this->redirectToRoute('admin_import_paiement_cb', [
                'existing_search' => $existingSearch,
                'missing_search' => $missingSearch,
                'existing_page' => $existingPage,
                'missing_page' => $missingPage,
                'tab' => $action === 'import_selected' || $action === 'import_all' ? 'missing' : $activeTab,
            ]);
        }

        $existingResult = [
            'data' => [],
            'pagination' => [
                'page' => $existingPage,
                'limit' => $perPage,
                'total' => 0,
                'totalPages' => 1,
            ],
        ];
        $missingResult = [
            'data' => [],
            'pagination' => [
                'page' => $missingPage,
                'limit' => $perPage,
                'total' => 0,
                'totalPages' => 1,
            ],
        ];
        $error = null;

        try {
            $existingResult = $this->intitulesCBOracleService->fetchExistingIntitulesPaginated($existingSearch, $existingPage, $perPage);
            $existingPagination = $existingResult['pagination'];
            if ($existingPagination['totalPages'] > 0 && $existingPage > $existingPagination['totalPages']) {
                return $this->redirectToRoute('admin_import_paiement_cb', [
                    'existing_search' => $existingSearch,
                    'missing_search' => $missingSearch,
                    'existing_page' => $existingPagination['totalPages'],
                    'missing_page' => $missingPage,
                    'tab' => $activeTab,
                ]);
            }
        } catch (\Throwable $e) {
            $error = 'Erreur lors du chargement des intitulés existants: ' . $e->getMessage();
        }

        try {
            $missingResult = $this->intitulesCBOracleService->fetchMissingIntitulesPaginated($missingSearch, $missingPage, $perPage);
            $missingPagination = $missingResult['pagination'];
            if ($missingPagination['totalPages'] > 0 && $missingPage > $missingPagination['totalPages']) {
                return $this->redirectToRoute('admin_import_paiement_cb', [
                    'existing_search' => $existingSearch,
                    'missing_search' => $missingSearch,
                    'existing_page' => $existingPage,
                    'missing_page' => $missingPagination['totalPages'],
                    'tab' => $activeTab,
                ]);
            }
        } catch (\Throwable $e) {
            $error = ($error ? $error . ' ' : '') . 'Erreur lors du chargement des intitulés manquants: ' . $e->getMessage();
        }

        if ($error) {
            $this->addFlash('error', $error);
        }

        return $this->render('admin/paiement_cb.html.twig', [
            'existing' => $existingResult['data'],
            'missing' => $missingResult['data'],
            'existingPagination' => $existingResult['pagination'],
            'missingPagination' => $missingResult['pagination'],
            'existingSearch' => $existingSearch,
            'missingSearch' => $missingSearch,
            'existingPage' => $existingResult['pagination']['page'],
            'missingPage' => $missingResult['pagination']['page'],
            'activeTab' => $activeTab,
        ]);
    }

    #[Route('/import/paiement-cb/export', name: 'admin_import_paiement_cb_export', methods: ['GET'])]
    public function exportPaiementCb(SessionInterface $session): Response
    {
        if (!$this->isAuthenticated($session)) {
            return $this->redirectToRoute('login');
        }

        try {
            $csv = $this->intitulesCBOracleService->exportAllIntitules();
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur lors de l\'export: ' . $e->getMessage());
            return $this->redirectToRoute('admin_import_paiement_cb');
        }

        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="paiement_cb_' . (new \DateTimeImmutable())->format('Ymd_His') . '.csv"');

        return $response;
    }


    #[Route('/admin/extraction', name: 'admin_extraction', methods: ['GET', 'POST'])]
    public function extraction(Request $request, SessionInterface $session): Response
    {
        if (!$this->isAuthenticated($session)) {
            return $this->redirectToRoute('login');
        }

        $groupeSi = $request->request->get('groupe_si', '');
        $selectedFields = $request->request->all('fields') ?? [];
        if (!empty($selectedFields)) {
            $selectedFields = array_values(array_unique($selectedFields));
        }
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
                    
                    if ($this->isAdmin($session)) {
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
            'canViewQuery' => $this->isAdmin($session)
        ]);
    }

    #[Route('/admin/extraction/query', name: 'admin_extraction_query', methods: ['POST'])]
    public function extractionQuery(Request $request, SessionInterface $session): Response
    {
        if (!$this->isAdmin($session)) {
            return new JsonResponse(['error' => 'Accès non autorisé'], 403);
        }

        $groupeSi = $request->request->get('groupe_si', '');
        $selectedFields = $request->request->all('fields') ?? [];
        if (!empty($selectedFields)) {
            $selectedFields = array_values(array_unique($selectedFields));
        }

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
            'admin_system' => 'Système',
            'admin_logs' => 'Logs',
            'admin_history' => 'Historique',
            'admin_user_access' => 'Administration',
            'admin_proposition' => 'Proposition (Suppression)',
            'admin_reouv_exemptes' => 'Réouv exemptés',
            'admin_beckrel_users' => 'Utilisateurs Beckrel',
            'admin_sowell_users' => 'Utilisateurs Sowell',
            'admin_mode_operatoire' => 'Mode opératoire',
            'admin_import_od' => 'Import OD',
            'admin_liste_affectation' => 'Liste d\'affectation',
            'admin_traitement_gl' => 'Traitement GL',
            'admin_import_paiement_cb' => 'Import Paiement CB',
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

        $adminUsers = $this->accessControlOracleService->getAdminUsers();

        return $this->render('admin/user_access.html.twig', [
            'userId' => $userId,
            'isAdmin' => $isAdminFlag,
            'allPages' => $allPages,
            'pageAccess' => array_keys($userPageAccess),
            'adminUsers' => $adminUsers,
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

    #[Route('/reouv-exemptes', name: 'admin_reouv_exemptes', methods: ['GET', 'POST'])]
    public function reouvExemptes(Request $request, SessionInterface $session): Response
    {
        if (!$this->isAuthenticated($session)) {
            return $this->redirectToRoute('login');
        }

        $numeroProposition = (int) $request->request->get('numero_proposition', 0);
        $error = null;
        $success = null;
        $propositionInfo = null;
        $exemptedCount = 0;

        if ($request->isMethod('POST') && $numeroProposition > 0) {
            // Vérifier si la proposition existe
            if (!$this->reouvExemptesOracleService->propositionExists($numeroProposition)) {
                $error = "❌ La proposition n°{$numeroProposition} n'existe pas.";
            } else {
                // Obtenir le nombre d'étapes exemptées avant réouverture
                $exemptedCount = $this->reouvExemptesOracleService->getExemptedStepsCount($numeroProposition);
                
                if ($exemptedCount === 0) {
                    $error = "ℹ️ Aucune étape exemptée trouvée pour la proposition n°{$numeroProposition}.";
                } else {
                    // Réouvrir les étapes exemptées
                    $result = $this->reouvExemptesOracleService->reopenExemptedSteps($numeroProposition);
                    
                    if ($result['success']) {
                        $success = $result['message'];
                        $propositionInfo = $this->reouvExemptesOracleService->getPropositionInfo($numeroProposition);
                    } else {
                        $error = $result['error'];
                    }
                }
            }
        }

        // Si on a un numéro de proposition, afficher les informations
        if ($numeroProposition > 0 && !$error && !$success) {
            $propositionInfo = $this->reouvExemptesOracleService->getPropositionInfo($numeroProposition);
            $exemptedCount = $this->reouvExemptesOracleService->getExemptedStepsCount($numeroProposition);
            
            if (!$propositionInfo) {
                $error = "❌ La proposition n°{$numeroProposition} n'existe pas.";
            }
        }

        return $this->render('admin/reouv_exemptes.html.twig', [
            'numero_proposition' => $numeroProposition,
            'proposition_info' => $propositionInfo,
            'exempted_count' => $exemptedCount,
            'error' => $error,
            'success' => $success,
        ]);
    }

    #[Route('/admin/traitement-gl', name: 'admin_traitement_gl', methods: ['GET'])]
    public function traitementGl(Request $request, SessionInterface $session): Response
    {
        if (!$this->isAuthenticated($session)) {
            return $this->redirectToRoute('login');
        }

        return $this->render('admin/traitement_gl.html.twig');
    }

    #[Route('/admin/traitement-gl/insee', name: 'admin_traitement_gl_insee', methods: ['GET'])]
    public function traitementGlInsee(Request $request, SessionInterface $session): Response
    {
        if (!$this->isAuthenticated($session)) {
            return $this->redirectToRoute('login');
        }

        // Par défaut, reprendre l'année 2022 comme dans le code fourni, avec possibilité de la passer en query string
        $annee = (int) ($request->query->get('annee', 2022));
        $csv = $this->inseeOracleService->generateCsv($annee);

        $filename = sprintf('insee_%s.csv', (new \DateTimeImmutable())->format('Ymd_His'));

        return new Response(
            $csv,
            200,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'no-store',
            ]
        );
    }

    #[Route('/admin/traitement-gl/plans/generer', name: 'admin_traitement_gl_plans_generer', methods: ['GET'])]
    public function traitementGlPlansGenerer(Request $request, SessionInterface $session): Response
    {
        if (!$this->isAuthenticated($session)) {
            return $this->redirectToRoute('login');
        }

        $username = (string) strtoupper((string) $session->get('user_id', '')); // même logique de session que le reste de l'app
        if ($username === '') {
            $this->addFlash('error', 'Utilisateur non authentifié');
            return $this->redirectToRoute('admin_traitement_gl');
        }

        try {
            $this->plansOfficeOracleService->triggerGeneration($username);
            $this->addFlash('success', "Génération des plans d'office déclenchée.");
        } catch (\Throwable $e) {
            $this->addFlash('error', "Erreur lors du déclenchement: " . $e->getMessage());
        }

        return $this->redirectToRoute('admin_traitement_gl');
    }

    #[Route('/admin/traitement-gl/plans/obtenir', name: 'admin_traitement_gl_plans_obtenir', methods: ['GET'])]
    public function traitementGlPlansObtenir(Request $request, SessionInterface $session): Response
    {
        if (!$this->isAuthenticated($session)) {
            return $this->redirectToRoute('login');
        }

        $csv = $this->plansOfficeOracleService->exportPlansCsv();
        $filename = sprintf('plans_office_%s.csv', (new \DateTimeImmutable())->format('Ymd_His'));

        return new Response(
            $csv,
            200,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'no-store',
            ]
        );
    }

    #[Route('/admin/traitement-gl/intitules-cb', name: 'admin_traitement_gl_intitules_cb', methods: ['GET'])]
    public function traitementGlIntitulesCb(Request $request, SessionInterface $session): Response
    {
        if (!$this->isAuthenticated($session)) {
            return $this->redirectToRoute('login');
        }

        $csv = $this->intitulesCBOracleService->generateCsvForToday();
        $filename = sprintf('intitules_cb_%s.csv', (new \DateTimeImmutable())->format('Ymd_His'));

        return new Response(
            $csv,
            200,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control' => 'no-store',
            ]
        );
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


    #[Route('/admin/logs', name: 'admin_logs', methods: ['GET'])]
    public function logs(Request $request, SessionInterface $session): Response
    {
        if (!$this->isAuthenticated($session)) {
            return $this->redirectToRoute('login');
        }

        $logType = $request->query->get('type', 'user_actions');
        $limit = (int)$request->query->get('limit', 50);
        $userId = $request->query->get('user_id', '');

        $logs = [];
        $stats = $this->logViewerService->getLogStats();
        $uniqueUsers = $this->logViewerService->getUniqueUsers();

        switch ($logType) {
            case 'user_actions':
                $logs = $this->logViewerService->getUserActionLogs($limit, $userId ?: null);
                break;
            case 'system_events':
                $logs = $this->logViewerService->getSystemEventLogs($limit, $userId ?: null);
                break;
            case 'general':
                $logs = $this->logViewerService->getGeneralLogs($limit, $userId ?: null);
                break;
        }

        return $this->render('admin/logs.html.twig', [
            'logs' => $logs,
            'stats' => $stats,
            'logType' => $logType,
            'limit' => $limit,
            'userId' => $userId,
            'uniqueUsers' => $uniqueUsers,
        ]);
    }

    #[Route('/admin/history', name: 'admin_history', methods: ['GET'])]
    public function history(Request $request, SessionInterface $session): Response
    {
        if (!$this->isAuthenticated($session)) {
            return $this->redirectToRoute('login');
        }

        $user = trim((string) $request->query->get('user_id', ''));
        $action = trim((string) $request->query->get('action', ''));
        $ip = trim((string) $request->query->get('ip', ''));
        $from = (string) $request->query->get('from', '');
        $to = (string) $request->query->get('to', '');
        $limit = (int) $request->query->get('limit', 40);
        $page = (int) $request->query->get('page', 1);

        $result = $this->logViewerService->getUserActionHistory(
            $user !== '' ? $user : null,
            $action !== '' ? $action : null,
            $ip !== '' ? $ip : null,
            $from !== '' ? $from : null,
            $to !== '' ? $to : null,
            $limit > 0 ? $limit : 40,
            $page > 0 ? $page : 1
        );

        return $this->render('admin/history.html.twig', [
            'entries' => $result['data'],
            'filters' => [
                'user_id' => $user,
                'action' => $action,
                'ip' => $ip,
                'from' => $from,
                'to' => $to,
                'limit' => $limit,
                'page' => $result['page'],
                'total' => $result['total'],
                'totalPages' => $result['totalPages'],
            ],
        ]);
    }

    #[Route('/admin/history/purge', name: 'admin_history_purge', methods: ['POST'])]
    public function historyPurge(Request $request, SessionInterface $session): Response
    {
        if (!$this->isAuthenticated($session)) {
            return $this->redirectToRoute('login');
        }

        // Optionnel: sécuriser par droit admin_history
        $access = (array) $session->get('page_access', []);
        if (!in_array('admin_history', $access, true)) {
            return $this->redirectToRoute('login');
        }

        $days = (int) $request->request->get('days', 30);
        $days = $days > 0 ? $days : 30;

        $stats = $this->logViewerService->purgeUserActionsOlderThan($days);
        $this->addFlash('success', sprintf('Purge effectuée: %d supprimé(s) (> %d jours), %d conservé(s).', $stats['purged'], $days, $stats['kept']));

        return $this->redirectToRoute('admin_history');
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

    #[Route('/import/od', name: 'admin_import_od', methods: ['GET'])]
    public function importOD(SessionInterface $session): Response
    {
        if (!$this->isAuthenticated($session)) {
            return $this->redirectToRoute('login');
        }

        $csvData = $this->importODService->getCsvData();
        
        // Si pas de données CSV en session, essayer de récupérer depuis Oracle
        if (!$csvData) {
            try {
                $oracleData = $this->importODOracleService->getOdAChargerData();
                if (!empty($oracleData)) {
                    $csvData = [
                        'headers' => array_keys($oracleData[0]),
                        'data' => $oracleData,
                        'total_rows' => count($oracleData)
                    ];
                }
            } catch (\Exception $e) {
                // Ignorer les erreurs Oracle pour l'affichage initial
            }
        }
        
        return $this->render('admin/import_od.html.twig', [
            'csvData' => $csvData
        ]);
    }

    #[Route('/import/od/upload', name: 'admin_import_od_upload', methods: ['POST'])]
    public function uploadOD(Request $request, SessionInterface $session): Response
    {
        if (!$this->isAuthenticated($session)) {
            return $this->redirectToRoute('login');
        }

        $error = null;
        $success = null;
        $csvData = null;

        if ($request->files->has('csv_file')) {
            $file = $request->files->get('csv_file');
            
            if ($file && $file->isValid()) {
                try {
                    // Upload et parse du fichier CSV
                    $csvData = $this->importODService->uploadAndParseCsv($file);
                    
                    // Nettoyer les codes PAESI_CODEXT (supprimer les espaces)
                    $csvData = $this->importODService->cleanPaesiCodes($csvData);
                    
                    // Valider les données
                    $validationErrors = $this->importODService->validateCsvData($csvData);
                    
                    if (empty($validationErrors)) {
                        // Intégrer les données dans Oracle pour prévisualisation
                        try {
                            $this->importODOracleService->clearOdACharger();
                            $this->importODOracleService->insertDataToOdACharger($csvData);
                            $this->importODOracleService->cleanPaesiCodes();
                            
                            $success = sprintf('Fichier chargé avec succès. %d lignes trouvées et chargées dans Oracle.', $csvData['total_rows']);
                        } catch (\Exception $e) {
                            $error = 'Erreur lors du chargement dans Oracle: ' . $e->getMessage();
                        }
                    } else {
                        $error = 'Erreurs de validation: ' . implode('; ', $validationErrors);
                    }
                    
                } catch (\Exception $e) {
                    $error = 'Erreur lors du chargement du fichier: ' . $e->getMessage();
                }
            } else {
                $error = 'Veuillez sélectionner un fichier CSV valide.';
            }
        } else {
            $error = 'Aucun fichier sélectionné.';
        }

        return $this->render('admin/import_od.html.twig', [
            'csvData' => $csvData,
            'error' => $error,
            'success' => $success
        ]);
    }

    #[Route('/import/od/integrate', name: 'admin_import_od_integrate', methods: ['POST'])]
    public function integrateOD(Request $request, SessionInterface $session): Response
    {
        if (!$this->isAuthenticated($session)) {
            return $this->redirectToRoute('login');
        }

        $userId = $session->get('user_id');
        $error = null;
        $success = null;

        try {
            $result = $this->importODService->integrateData($userId);
            
            if ($result['success']) {
                $success = sprintf(
                    'Intégration réussie ! %d lignes intégrées.',
                    $result['integrated_count']
                );
                
                if (!empty($result['errors'])) {
                    $success .= ' Erreurs: ' . implode('; ', $result['errors']);
                }
                
                // Log de l'action
                $this->userActionLogger->logDataModification('IMPORT_OD', 'INTEGRATION', [
                    'integrated_count' => $result['integrated_count'],
                    'errors' => $result['errors']
                ], $userId);
                
            } else {
                $error = $result['error'];
            }
            
        } catch (\Exception $e) {
            $error = 'Erreur lors de l\'intégration: ' . $e->getMessage();
        }

        return $this->render('admin/import_od.html.twig', [
            'csvData' => null, // Nettoyer les données après intégration
            'error' => $error,
            'success' => $success
        ]);
    }

    #[Route('/import/od/clear', name: 'admin_import_od_clear', methods: ['POST'])]
    public function clearOD(SessionInterface $session): Response
    {
        if (!$this->isAuthenticated($session)) {
            return $this->redirectToRoute('login');
        }

        $error = null;
        $success = null;

        try {
            $this->importODOracleService->clearOdACharger();
            $success = "Table OD_A_CHARGER vidée avec succès.";

            // Log action
            $this->userActionLogger->logDataModification('IMPORT_OD', 'CLEAR_BUFFER', [
                'table' => 'OD_A_CHARGER'
            ], $session->get('user_id'));
        } catch (\Throwable $e) {
            $error = 'Erreur lors du vidage: ' . $e->getMessage();
        }

        // Revenir sur la page avec message et sans données
        return $this->render('admin/import_od.html.twig', [
            'csvData' => null,
            'error' => $error,
            'success' => $success
        ]);
    }

    #[Route('/liste-affectation', name: 'admin_liste_affectation', methods: ['GET'])]
    public function listeAffectation(Request $request, SessionInterface $session): Response
    {
        if (!$this->isAuthenticated($session)) {
            return $this->redirectToRoute('login');
        }

        // Récupérer les paramètres de pagination et recherche
        $page = (int) $request->query->get('page', 1);
        $search = trim((string) $request->query->get('search', ''));
        $esoGardien = trim((string) $request->query->get('eso', ''));
        $gardien = trim((string) $request->query->get('gardien', ''));
        $groupe = $request->query->get('groupe', '');
        $limit = 50; // 50 lignes par page

        $hasLotSearch = $search !== '' && strlen($search) >= 5;
        $hasEsoSearch = $esoGardien !== '' && strlen($esoGardien) >= 3;
        $hasGardienSearch = $gardien !== '' && strlen($gardien) >= 3;
        $hasFilters = $hasLotSearch || $hasEsoSearch || $hasGardienSearch;
        $searchFilter = $hasLotSearch ? $search : '';
        $esoFilter = $hasEsoSearch ? $esoGardien : '';
        $gardienFilter = $hasGardienSearch ? $gardien : '';

        try {
            // Ne pas charger la liste par défaut: attendre une recherche
            if (!$hasFilters) {
                return $this->render('admin/liste_affectation.html.twig', [
                    'data' => [],
                    'pagination' => [
                        'page' => 1,
                        'total' => 0,
                        'limit' => $limit,
                        'totalPages' => 0
                    ],
                    'search' => $search,
                    'eso' => $esoGardien,
                    'gardien' => $gardien,
                    'groupe' => $groupe,
                    'groupes' => [],
                    'stats' => null,
                    'hasSearch' => false,
                    'minSearchMsg' => 'Saisissez au moins 5 caractères pour le lot ou 3 caractères pour l\'ESO / le gardien.'
                ]);
            }

            // Récupérer les données Oracle avec pagination et recherche
            $result = $this->listeAffectationOracleService->getListeAffectations(
                $searchFilter,
                $groupe,
                $page,
                $limit,
                false,
                $esoFilter,
                $gardienFilter
            );

            // Statistiques filtrées
            $stats = $this->listeAffectationOracleService->getAffectationStatsBySearch($searchFilter, $esoFilter, $gardienFilter);

            return $this->render('admin/liste_affectation.html.twig', [
                'data' => $result['data'],
                'pagination' => [
                    'page' => $result['page'],
                    'total' => $result['total'],
                    'limit' => $result['limit'],
                    'totalPages' => $result['totalPages']
                ],
                'search' => $search,
                'eso' => $esoGardien,
                'gardien' => $gardien,
                'groupe' => $groupe,
                'groupes' => [],
                'stats' => $stats,
                'hasSearch' => true
            ]);
            
        } catch (\Exception $e) {
            return $this->render('admin/liste_affectation.html.twig', [
                'data' => [],
                'pagination' => [
                    'page' => 1,
                    'total' => 0,
                    'limit' => 50,
                    'totalPages' => 0
                ],
                'search' => $search,
                'eso' => $esoGardien,
                'gardien' => $gardien,
                'groupe' => $groupe,
                'groupes' => [],
                'stats' => [],
                'error' => 'Erreur lors du chargement des données: ' . $e->getMessage(),
                'hasSearch' => $hasFilters
            ]);
        }
    }

    #[Route('/liste-affectation/export', name: 'admin_liste_affectation_export', methods: ['GET'])]
    public function exportListeAffectation(SessionInterface $session): Response
    {
        if (!$this->isAuthenticated($session)) {
            return $this->redirectToRoute('login');
        }

        $datetime = (new \DateTimeImmutable())->format('Ymd_His');
        $filename = "liste_affectations_{$datetime}.csv";

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $response = new \Symfony\Component\HttpFoundation\StreamedResponse(function () {
            set_time_limit(0);
            $out = fopen('php://output', 'w');
            // BOM UTF-8 pour Excel
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'AGENCE','GROUPE','ESO_GROUPE','BATIMENT','ESO_BATIMENT','ESCALIER','ESO_ESC','LOT','NATURE_LOT',
                'RGT_GPE','RGTL_GPE','CGLS_GPE','INLOG_GPE','TEDL_GPE','TPROX_GPE','RVQ_GPE','GARDIEN_GPE',
                'RGT_BAT','RGTL_BAT','CGLS_BAT','INLOG_BAT','TEDL_BAT','TPROX_BAT','RVQ_BAT','GARDIEN_BAT',
                'RGT_ESC','RGTL_ESC','CGLS_ESC','INLOG_ESC','TEDL_ESC','TPROX_ESC','RVQ_ESC','GARDIEN_ESC',
                'RGT_LOT','RGTL_LOT','CGLS_LOT','INLOG_LOT','TEDL_LOT','TPROX_LOT','RVQ_LOT','GARDIEN_LOT',
                'ESO_GARDIEN','GARD_TEL','GARD_MAIL'
            ], ';');

            foreach ($this->listeAffectationOracleService->getAllAffectationsForExport() as $row) {
                fputcsv($out, [
                    $row['AGENCE'] ?? '', $row['GROUPE'] ?? '', $row['ESO_GROUPE'] ?? '', $row['BATIMENT'] ?? '', $row['ESO_BATIMENT'] ?? '', $row['ESCALIER'] ?? '', $row['ESO_ESC'] ?? '', $row['LOT'] ?? '', $row['NATURE_LOT'] ?? '',
                    $row['RGT_GPE'] ?? '', $row['RGTL_GPE'] ?? '', $row['CGLS_GPE'] ?? '', $row['INLOG_GPE'] ?? '', $row['TEDL_GPE'] ?? '', $row['TPROX_GPE'] ?? '', $row['RVQ_GPE'] ?? '', $row['GARDIEN_GPE'] ?? '',
                    $row['RGT_BAT'] ?? '', $row['RGTL_BAT'] ?? '', $row['CGLS_BAT'] ?? '', $row['INLOG_BAT'] ?? '', $row['TEDL_BAT'] ?? '', $row['TPROX_BAT'] ?? '', $row['RVQ_BAT'] ?? '', $row['GARDIEN_BAT'] ?? '',
                    $row['RGT_ESC'] ?? '', $row['RGTL_ESC'] ?? '', $row['CGLS_ESC'] ?? '', $row['INLOG_ESC'] ?? '', $row['TEDL_ESC'] ?? '', $row['TPROX_ESC'] ?? '', $row['RVQ_ESC'] ?? '', $row['GARDIEN_ESC'] ?? '',
                    $row['RGT_LOT'] ?? '', $row['RGTL_LOT'] ?? '', $row['CGLS_LOT'] ?? '', $row['INLOG_LOT'] ?? '', $row['TEDL_LOT'] ?? '', $row['TPROX_LOT'] ?? '', $row['RVQ_LOT'] ?? '', $row['GARDIEN_LOT'] ?? '',
                    $row['ESO_GARDIEN'] ?? '', $row['GARD_TEL'] ?? '', $row['GARD_MAIL'] ?? '',
                ], ';');
            }

            fclose($out);
        });
        $response->headers->add($headers);
        return $response;
    }

    #[Route('/liste-affectation/export-current', name: 'admin_liste_affectation_export_current', methods: ['GET'])]
    public function exportListeAffectationCurrent(Request $request, SessionInterface $session): Response
    {
        if (!$this->isAuthenticated($session)) {
            return $this->redirectToRoute('login');
        }

        $search = trim((string) $request->query->get('search', ''));
        $esoGardien = trim((string) $request->query->get('eso', ''));
        $gardien = trim((string) $request->query->get('gardien', ''));

        $hasLotSearch = $search !== '' && strlen($search) >= 5;
        $hasEsoSearch = $esoGardien !== '' && strlen($esoGardien) >= 3;
        $hasGardienSearch = $gardien !== '' && strlen($gardien) >= 3;

        if (!$hasLotSearch && !$hasEsoSearch && !$hasGardienSearch) {
            // Pas de filtre -> rediriger sur la page liste
            return $this->redirectToRoute('admin_liste_affectation');
        }

        $searchFilter = $hasLotSearch ? $search : '';
        $esoFilter = $hasEsoSearch ? $esoGardien : '';
        $gardienFilter = $hasGardienSearch ? $gardien : '';

        $datetime = (new \DateTimeImmutable())->format('Ymd_His');
        $filterTokens = array_filter([$searchFilter, $esoFilter, $gardienFilter], static fn (string $value): bool => $value !== '');
        $filterTokens = array_map(
            static function (string $value): string {
                $value = str_replace(' ', '_', $value);
                return preg_replace('/[^A-Za-z0-9_-]+/', '', $value) ?? '';
            },
            $filterTokens
        );
        $filterLabel = $filterTokens !== [] ? implode('-', $filterTokens) : 'filtre';
        $filename = "liste_affectations_{$filterLabel}_{$datetime}.csv";

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $response = new \Symfony\Component\HttpFoundation\StreamedResponse(function () use ($searchFilter, $esoFilter, $gardienFilter) {
            set_time_limit(0);
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'AGENCE','GROUPE','ESO_GROUPE','BATIMENT','ESO_BATIMENT','ESCALIER','ESO_ESC','LOT','NATURE_LOT',
                'RGT_GPE','RGTL_GPE','CGLS_GPE','INLOG_GPE','TEDL_GPE','TPROX_GPE','RVQ_GPE','GARDIEN_GPE',
                'RGT_BAT','RGTL_BAT','CGLS_BAT','INLOG_BAT','TEDL_BAT','TPROX_BAT','RVQ_BAT','GARDIEN_BAT',
                'RGT_ESC','RGTL_ESC','CGLS_ESC','INLOG_ESC','TEDL_ESC','TPROX_ESC','RVQ_ESC','GARDIEN_ESC',
                'RGT_LOT','RGTL_LOT','CGLS_LOT','INLOG_LOT','TEDL_LOT','TPROX_LOT','RVQ_LOT','GARDIEN_LOT',
                'ESO_GARDIEN','GARD_TEL','GARD_MAIL'
            ], ';');

            foreach ($this->listeAffectationOracleService->getAffectationsForExportBySearch($searchFilter, $esoFilter, $gardienFilter) as $row) {
                fputcsv($out, [
                    $row['AGENCE'] ?? '', $row['GROUPE'] ?? '', $row['ESO_GROUPE'] ?? '', $row['BATIMENT'] ?? '', $row['ESO_BATIMENT'] ?? '', $row['ESCALIER'] ?? '', $row['ESO_ESC'] ?? '', $row['LOT'] ?? '', $row['NATURE_LOT'] ?? '',
                    $row['RGT_GPE'] ?? '', $row['RGTL_GPE'] ?? '', $row['CGLS_GPE'] ?? '', $row['INLOG_GPE'] ?? '', $row['TEDL_GPE'] ?? '', $row['TPROX_GPE'] ?? '', $row['RVQ_GPE'] ?? '', $row['GARDIEN_GPE'] ?? '',
                    $row['RGT_BAT'] ?? '', $row['RGTL_BAT'] ?? '', $row['CGLS_BAT'] ?? '', $row['INLOG_BAT'] ?? '', $row['TEDL_BAT'] ?? '', $row['TPROX_BAT'] ?? '', $row['RVQ_BAT'] ?? '', $row['GARDIEN_BAT'] ?? '',
                    $row['RGT_ESC'] ?? '', $row['RGTL_ESC'] ?? '', $row['CGLS_ESC'] ?? '', $row['INLOG_ESC'] ?? '', $row['TEDL_ESC'] ?? '', $row['TPROX_ESC'] ?? '', $row['RVQ_ESC'] ?? '', $row['GARDIEN_ESC'] ?? '',
                    $row['RGT_LOT'] ?? '', $row['RGTL_LOT'] ?? '', $row['CGLS_LOT'] ?? '', $row['INLOG_LOT'] ?? '', $row['TEDL_LOT'] ?? '', $row['TPROX_LOT'] ?? '', $row['RVQ_LOT'] ?? '', $row['GARDIEN_LOT'] ?? '',
                    $row['ESO_GARDIEN'] ?? '', $row['GARD_TEL'] ?? '', $row['GARD_MAIL'] ?? '',
                ], ';');
            }
            fclose($out);
        });
        $response->headers->add($headers);
        return $response;
    }
    #[Route(
        '/liste-affectation/detail/{lot}',
        name: 'admin_liste_affectation_detail',
        methods: ['GET'],
        requirements: ['lot' => '[^/]+' ]
    )]
    #[Route(
        '/liste-affectation/detail',
        name: 'admin_liste_affectation_detail_qs',
        methods: ['GET']
    )]
    public function listeAffectationDetail(string $lot = null, SessionInterface $session, Request $request): Response
    {
        if (!$this->isAuthenticated($session)) {
            return $this->redirectToRoute('login');
        }

        if ($lot === null || $lot === '') {
            $lot = (string) $request->query->get('lot', '');
        }

        try {
            $affectation = $this->listeAffectationOracleService->getAffectationDetails($lot);
            
            if (!$affectation) {
                return $this->render('admin/liste_affectation_detail.html.twig', [
                    'affectation' => null,
                    'lot' => $lot,
                    'error' => 'Aucune affectation trouvée pour le LOT: ' . $lot
                ]);
            }

            return $this->render('admin/liste_affectation_detail.html.twig', [
                'affectation' => $affectation,
                'lot' => $lot
            ]);
            
        } catch (\Exception $e) {
            return $this->render('admin/liste_affectation_detail.html.twig', [
                'affectation' => null,
                'lot' => $lot,
                'error' => 'Erreur lors du chargement des détails: ' . $e->getMessage()
            ]);
        }
    }

    private function isAdmin(SessionInterface $session): bool
    {
        return $this->isAuthenticated($session) && $session->get('is_admin', false) === true;
    }
} 