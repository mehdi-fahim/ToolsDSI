<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

class ListeAffectationOracleService
{
    public function __construct(private DatabaseConnectionResolver $connectionResolver)
    {
    }

    private function getConnection(): Connection
    {
        return $this->connectionResolver->getConnection();
    }

    /** Critères de recherche autorisés => colonne SQL (dans LISTE_V_AFFECTATIONS) */
    public const CRITERIA_COLUMNS = [
        'ESI' => 'ESO_GROUPE',
        'ESO' => 'ESO_GARDIEN',
        'Gardien' => 'GARDIEN_LOT',
        'TPROX' => 'TPROX_LOT',
        'RS' => 'RVQ_LOT',
    ];

    /**
     * Récupère la liste des affectations avec pagination et recherche
     * @param string $searchValue Valeur saisie pour la recherche
     * @param string $criterion Critère : ESI, ESO, Gardien, TPROX, RS
     */
    public function getListeAffectations(
        string $searchValue = '',
        string $groupe = '',
        int $page = 1,
        int $limit = 20,
        bool $onlyMissing = false,
        string $criterion = 'ESO'
    ): array
    {
        $offset = ($page - 1) * $limit;

        // Requête de base
        $baseSql = <<<SQL
        FROM LISTE_V_AFFECTATIONS
        SQL;

        // Conditions et paramètres de recherche (un critère + une valeur)
        $whereConditions = [];
        $params = [];

        $searchTrim = trim($searchValue);
        if ($searchTrim !== '' && isset(self::CRITERIA_COLUMNS[$criterion])) {
            $col = self::CRITERIA_COLUMNS[$criterion];
            $whereConditions[] = "UPPER(TRIM($col)) LIKE :criterionSearch";
            $params['criterionSearch'] = '%' . strtoupper($searchTrim) . '%';
        }

        if (!empty($groupe)) {
            $whereConditions[] = "UPPER(GROUPE) = UPPER(:groupe)";
            $params['groupe'] = $groupe;
        }

        if ($onlyMissing) {
            $whereConditions[] = "( (GARD_TEL IS NULL OR TRIM(GARD_TEL) = '') OR (GARD_MAIL IS NULL OR TRIM(GARD_MAIL) = '') )";
        }

        if (!empty($whereConditions)) {
            $baseSql .= ' WHERE ' . implode(' AND ', $whereConditions);
        }

        // 1. Récupération du total
        $countSql = "SELECT COUNT(*) as total " . $baseSql;
        $total = $this->getConnection()
            ->executeQuery($countSql, $params)
            ->fetchOne();

        // 2. Récupération des données paginées
        $sql = <<<SQL
        SELECT
            AGENCE,
            GROUPE,
            LOT,
            NATURE_LOT,
            GARDIEN_LOT,
            ESO_GARDIEN,
            GARD_TEL,
            GARD_MAIL
        $baseSql
        ORDER BY AGENCE, GROUPE, LOT
        OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY
        SQL;

        $params['offset'] = $offset;
        $params['limit'] = $limit;

        $data = $this->getConnection()
            ->executeQuery($sql, $params)
            ->fetchAllAssociative();

        $totalPages = ceil($total / $limit);

        return [
            'data' => $data,
            'total' => (int) $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => (int) $totalPages
        ];
    }

    /**
     * Récupère toutes les affectations (pour export)
     */
    public function getAllAffectationsForExport(): iterable
    {
        $sql = <<<SQL
        SELECT 
            AGENCE,
            GROUPE,
            ESO_GROUPE,
            BATIMENT,
            ESO_BATIMENT,
            ESCALIER,
            ESO_ESC,
            LOT,
            NATURE_LOT,
            RGT_GPE,
            RGTL_GPE,
            CGLS_GPE,
            INLOG_GPE,
            TEDL_GPE,
            TPROX_GPE,
            RVQ_GPE,
            GARDIEN_GPE,
            RGT_BAT,
            RGTL_BAT,
            CGLS_BAT,
            INLOG_BAT,
            TEDL_BAT,
            TPROX_BAT,
            RVQ_BAT,
            GARDIEN_BAT,
            RGT_ESC,
            RGTL_ESC,
            CGLS_ESC,
            INLOG_ESC,
            TEDL_ESC,
            TPROX_ESC,
            RVQ_ESC,
            GARDIEN_ESC,
            RGT_LOT,
            RGTL_LOT,
            CGLS_LOT,
            INLOG_LOT,
            TEDL_LOT,
            TPROX_LOT,
            RVQ_LOT,
            GARDIEN_LOT,
            ESO_GARDIEN,
            GARD_TEL,
            GARD_MAIL
        FROM LISTE_V_AFFECTATIONS
        ORDER BY AGENCE, GROUPE, LOT
        SQL;

        return $this->getConnection()->executeQuery($sql)->iterateAssociative();
    }

    /**
     * Récupère les affectations correspondant à une recherche (pour export courant)
     */
    public function getAffectationsForExportBySearch(string $searchValue = '', string $criterion = 'ESO'): iterable
    {
        $selectSql = <<<SQL
        SELECT 
            AGENCE,
            GROUPE,
            ESO_GROUPE,
            BATIMENT,
            ESO_BATIMENT,
            ESCALIER,
            ESO_ESC,
            LOT,
            NATURE_LOT,
            RGT_GPE,
            RGTL_GPE,
            CGLS_GPE,
            INLOG_GPE,
            TEDL_GPE,
            TPROX_GPE,
            RVQ_GPE,
            GARDIEN_GPE,
            RGT_BAT,
            RGTL_BAT,
            CGLS_BAT,
            INLOG_BAT,
            TEDL_BAT,
            TPROX_BAT,
            RVQ_BAT,
            GARDIEN_BAT,
            RGT_ESC,
            RGTL_ESC,
            CGLS_ESC,
            INLOG_ESC,
            TEDL_ESC,
            TPROX_ESC,
            RVQ_ESC,
            GARDIEN_ESC,
            RGT_LOT,
            RGTL_LOT,
            CGLS_LOT,
            INLOG_LOT,
            TEDL_LOT,
            TPROX_LOT,
            RVQ_LOT,
            GARDIEN_LOT,
            ESO_GARDIEN,
            GARD_TEL,
            GARD_MAIL
        SQL;

        $baseSql = 'FROM LISTE_V_AFFECTATIONS';
        $whereConditions = [];
        $params = [];

        $searchTrim = trim($searchValue);
        if ($searchTrim !== '' && isset(self::CRITERIA_COLUMNS[$criterion])) {
            $col = self::CRITERIA_COLUMNS[$criterion];
            $whereConditions[] = "UPPER(TRIM($col)) LIKE :criterionSearch";
            $params['criterionSearch'] = '%' . strtoupper($searchTrim) . '%';
        }

        if (!empty($whereConditions)) {
            $baseSql .= ' WHERE ' . implode(' AND ', $whereConditions);
        }

        $sql = $selectSql . ' ' . $baseSql . ' ORDER BY AGENCE, GROUPE, LOT';

        return $this->getConnection()->executeQuery($sql, $params)->iterateAssociative();
    }

    /**
     * Récupère les détails d'une affectation par LOT
     */
    public function getAffectationDetails(string $lot): ?array
    {
        $sql = <<<SQL
        SELECT 
            AGENCE,
            GROUPE,
            ESO_GROUPE,
            BATIMENT,
            ESO_BATIMENT,
            ESCALIER,
            ESO_ESC,
            LOT,
            NATURE_LOT,
            RGT_GPE,
            RGTL_GPE,
            CGLS_GPE,
            INLOG_GPE,
            TEDL_GPE,
            TPROX_GPE,
            RVQ_GPE,
            GARDIEN_GPE,
            RGT_BAT,
            RGTL_BAT,
            CGLS_BAT,
            INLOG_BAT,
            TEDL_BAT,
            TPROX_BAT,
            RVQ_BAT,
            GARDIEN_BAT,
            RGT_ESC,
            RGTL_ESC,
            CGLS_ESC,
            INLOG_ESC,
            TEDL_ESC,
            TPROX_ESC,
            RVQ_ESC,
            GARDIEN_ESC,
            RGT_LOT,
            RGTL_LOT,
            CGLS_LOT,
            INLOG_LOT,
            TEDL_LOT,
            TPROX_LOT,
            RVQ_LOT,
            GARDIEN_LOT,
            ESO_GARDIEN,
            GARD_TEL,
            GARD_MAIL
        FROM LISTE_V_AFFECTATIONS
        WHERE UPPER(LOT) = UPPER(:lot)
        SQL;

        $result = $this->getConnection()
            ->executeQuery($sql, ['lot' => $lot])
            ->fetchAssociative();

        return $result ?: null;
    }

    /**
     * Récupère les statistiques des affectations
     */
    public function getAffectationStats(): array
    {
        $sql = <<<SQL
        SELECT 
            COUNT(*) as TOTAL_LOTS,
            COUNT(DISTINCT AGENCE) as TOTAL_AGENCES,
            COUNT(CASE WHEN GARDIEN_LOT IS NOT NULL AND GARDIEN_LOT != '' THEN 1 END) as LOTS_AVEC_GARDIEN,
            COUNT(CASE WHEN GARD_TEL IS NOT NULL AND GARD_TEL != '' THEN 1 END) as LOTS_AVEC_TELEPHONE,
            COUNT(CASE WHEN GARD_MAIL IS NOT NULL AND GARD_MAIL != '' THEN 1 END) as LOTS_AVEC_EMAIL
        FROM LISTE_V_AFFECTATIONS
        SQL;

        return $this->getConnection()
            ->executeQuery($sql)
            ->fetchAssociative();
    }

    /**
     * Statistiques filtrées par critère + valeur
     */
    public function getAffectationStatsBySearch(string $searchValue = '', string $criterion = 'ESO'): array
    {
        $sql = <<<SQL
        SELECT 
            COUNT(*) as TOTAL_LOTS,
            COUNT(CASE WHEN TRIM(GARD_TEL) IS NOT NULL THEN 1 END) as LOTS_AVEC_TELEPHONE,
            COUNT(CASE WHEN TRIM(GARD_MAIL) IS NOT NULL THEN 1 END) as LOTS_AVEC_EMAIL,
            COUNT(CASE WHEN (TRIM(GARD_TEL) IS NULL AND TRIM(GARD_MAIL) IS NULL) THEN 1 END) as LOTS_SANS_CONTACT
        FROM LISTE_V_AFFECTATIONS
        SQL;

        $whereConditions = [];
        $params = [];

        $searchTrim = trim($searchValue);
        if ($searchTrim !== '' && isset(self::CRITERIA_COLUMNS[$criterion])) {
            $col = self::CRITERIA_COLUMNS[$criterion];
            $whereConditions[] = "UPPER(TRIM($col)) LIKE :criterionSearch";
            $params['criterionSearch'] = '%' . strtoupper($searchTrim) . '%';
        }

        if (!empty($whereConditions)) {
            $sql .= ' WHERE ' . implode(' AND ', $whereConditions);
        }

        return $this->getConnection()
            ->executeQuery($sql, $params)
            ->fetchAssociative();
    }

    /**
     * Récupère la liste des agences disponibles
     */
    public function getAgences(): array
    {
        $sql = <<<SQL
        SELECT DISTINCT AGENCE 
        FROM LISTE_V_AFFECTATIONS 
        WHERE AGENCE IS NOT NULL 
        ORDER BY AGENCE
        SQL;

        $result = $this->getConnection()
            ->executeQuery($sql)
            ->fetchFirstColumn();

        return $result;
    }

    /**
     * Récupère la liste des groupes disponibles
     */
    public function getGroupes(): array
    {
        $sql = <<<SQL
        SELECT DISTINCT GROUPE 
        FROM LISTE_V_AFFECTATIONS 
        WHERE GROUPE IS NOT NULL AND GROUPE != ''
        ORDER BY GROUPE
        SQL;

        $result = $this->getConnection()
            ->executeQuery($sql)
            ->fetchFirstColumn();

        return $result;
    }

    /**
     * Récupère la liste des lots par agence
     */
    public function getLotsByAgence(string $agence): array
    {
        $sql = <<<SQL
        SELECT LOT, NATURE_LOT, GARDIEN_LOT
        FROM LISTE_V_AFFECTATIONS 
        WHERE UPPER(AGENCE) = UPPER(:agence)
        ORDER BY LOT
        SQL;

        return $this->getConnection()
            ->executeQuery($sql, ['agence' => $agence])
            ->fetchAllAssociative();
    }
}
