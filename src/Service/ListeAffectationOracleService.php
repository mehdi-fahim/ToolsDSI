<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

class ListeAffectationOracleService
{
    private Connection $connection;

    public function __construct(Connection $defaultConnection)
    {
        $this->connection = $defaultConnection;
    }

    /**
     * Récupère la liste des affectations avec pagination et recherche
     */
    public function getListeAffectations(string $search = '', string $groupe = '', int $page = 1, int $limit = 20, bool $onlyMissing = false): array
    {
        $offset = ($page - 1) * $limit;

        // Requête de base
        $baseSql = <<<SQL
        FROM LISTE_V_AFFECTATIONS
        SQL;

        // Conditions et paramètres de recherche
        $whereConditions = [];
        $params = [];

        if (!empty($search)) {
            // Recherche par préfixe sans UPPER sur la colonne pour aider l'index
            $whereConditions[] = "LOT LIKE :search";
            $params['search'] = strtoupper($search) . '%';
        }

        if (!empty($groupe)) {
            $whereConditions[] = "UPPER(GROUPE) = UPPER(:groupe)";
            $params['groupe'] = $groupe;
        }

        if ($onlyMissing) {
            $whereConditions[] = "( (GARD_TEL IS NULL OR TRIM(GARD_TEL) = '') AND (GARD_MAIL IS NULL OR TRIM(GARD_MAIL) = '') )";
        }

        if (!empty($whereConditions)) {
            $baseSql .= ' WHERE ' . implode(' AND ', $whereConditions);
        }

        // 1. Récupération du total
        $countSql = "SELECT COUNT(*) as total " . $baseSql;
        $total = $this->connection
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

        $data = $this->connection
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

        return $this->connection->executeQuery($sql)->iterateAssociative();
    }

    /**
     * Récupère les affectations correspondant à une recherche (pour export courant)
     */
    public function getAffectationsForExportBySearch(string $search): iterable
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
            RGT_LOT
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
        WHERE UPPER(LOT) LIKE UPPER(:search)
        ORDER BY AGENCE, GROUPE, LOT
        SQL;

        $param = ['search' => strtoupper($search) . '%'];
        return $this->connection->executeQuery($sql, $param)->iterateAssociative();
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

        $result = $this->connection
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

        return $this->connection
            ->executeQuery($sql)
            ->fetchAssociative();
    }

    /**
     * Statistiques filtrées par préfixe de LOT
     */
    public function getAffectationStatsBySearch(string $search): array
    {
        $sql = <<<SQL
        SELECT 
            COUNT(*) as TOTAL_LOTS,
            COUNT(CASE WHEN GARD_TEL IS NOT NULL AND GARD_TEL != '' THEN 1 END) as LOTS_AVEC_TELEPHONE,
            COUNT(CASE WHEN GARD_MAIL IS NOT NULL AND GARD_MAIL != '' THEN 1 END) as LOTS_AVEC_EMAIL,
            COUNT(CASE WHEN ( (GARD_TEL IS NULL OR TRIM(GARD_TEL) = '') AND (GARD_MAIL IS NULL OR TRIM(GARD_MAIL) = '') ) THEN 1 END) as LOTS_SANS_CONTACT
        FROM LISTE_V_AFFECTATIONS
        WHERE UPPER(LOT) LIKE UPPER(:search)
        SQL;

        return $this->connection
            ->executeQuery($sql, ['search' => strtoupper($search) . '%'])
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

        $result = $this->connection
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

        $result = $this->connection
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

        return $this->connection
            ->executeQuery($sql, ['agence' => $agence])
            ->fetchAllAssociative();
    }
}
