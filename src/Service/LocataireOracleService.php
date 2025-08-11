<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

class LocataireOracleService
{
    private Connection $connection;

    public function __construct(Connection $defaultConnection)
    {
        $this->connection = $defaultConnection;
    }

    /**
     * Recherche par ESI
     */
    public function searchByEsi(string $esi, int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;

        // TODO: Remplacer par la vraie requête Oracle
        $baseSql = <<<SQL
        FROM LOCATAIRES l
        WHERE l.ESI = :esi
        SQL;

        $params = ['esi' => $esi];

        // Requête pour le total
        $countSql = "SELECT COUNT(*) as total " . $baseSql;
        $total = $this->connection
            ->executeQuery($countSql, $params)
            ->fetchOne();

        // Requête principale avec pagination
        $sql = <<<SQL
        SELECT
            l.ESI,
            l.CONTRAT,
            l.INTITULE,
            l.NOM,
            l.PRENOM,
            l.ADRESSE,
            l.TELEPHONE,
            l.EMAIL
        $baseSql
        ORDER BY l.ESI
        OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY
        SQL;

        $params['offset'] = $offset;
        $params['limit'] = $limit;

        $types = [
            'offset' => ParameterType::INTEGER,
            'limit' => ParameterType::INTEGER,
        ];

        $data = $this->connection
            ->executeQuery($sql, $params, $types)
            ->fetchAllAssociative();

        return [
            'data' => $data,
            'total' => (int) $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => ceil($total / $limit),
        ];
    }

    /**
     * Recherche par Contrat
     */
    public function searchByContrat(string $contrat, int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;

        // TODO: Remplacer par la vraie requête Oracle
        $baseSql = <<<SQL
        FROM LOCATAIRES l
        WHERE l.CONTRAT = :contrat
        SQL;

        $params = ['contrat' => $contrat];

        // Requête pour le total
        $countSql = "SELECT COUNT(*) as total " . $baseSql;
        $total = $this->connection
            ->executeQuery($countSql, $params)
            ->fetchOne();

        // Requête principale avec pagination
        $sql = <<<SQL
        SELECT
            l.ESI,
            l.CONTRAT,
            l.INTITULE,
            l.NOM,
            l.PRENOM,
            l.ADRESSE,
            l.TELEPHONE,
            l.EMAIL
        $baseSql
        ORDER BY l.CONTRAT
        OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY
        SQL;

        $params['offset'] = $offset;
        $params['limit'] = $limit;

        $types = [
            'offset' => ParameterType::INTEGER,
            'limit' => ParameterType::INTEGER,
        ];

        $data = $this->connection
            ->executeQuery($sql, $params, $types)
            ->fetchAllAssociative();

        return [
            'data' => $data,
            'total' => (int) $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => ceil($total / $limit),
        ];
    }

    /**
     * Recherche par Intitulé
     */
    public function searchByIntitule(string $intitule, int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;

        // TODO: Remplacer par la vraie requête Oracle
        $baseSql = <<<SQL
        FROM LOCATAIRES l
        WHERE UPPER(l.INTITULE) LIKE UPPER(:intitule)
        SQL;

        $params = ['intitule' => '%' . $intitule . '%'];

        // Requête pour le total
        $countSql = "SELECT COUNT(*) as total " . $baseSql;
        $total = $this->connection
            ->executeQuery($countSql, $params)
            ->fetchOne();

        // Requête principale avec pagination
        $sql = <<<SQL
        SELECT
            l.ESI,
            l.CONTRAT,
            l.INTITULE,
            l.NOM,
            l.PRENOM,
            l.ADRESSE,
            l.TELEPHONE,
            l.EMAIL
        $baseSql
        ORDER BY l.INTITULE
        OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY
        SQL;

        $params['offset'] = $offset;
        $params['limit'] = $limit;

        $types = [
            'offset' => ParameterType::INTEGER,
            'limit' => ParameterType::INTEGER,
        ];

        $data = $this->connection
            ->executeQuery($sql, $params, $types)
            ->fetchAllAssociative();

        return [
            'data' => $data,
            'total' => (int) $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => ceil($total / $limit),
        ];
    }

    /**
     * Récupérer un locataire par son ESI
     */
    public function getLocataireByEsi(string $esi): ?array
    {
        // TODO: Remplacer par la vraie requête Oracle
        $sql = <<<SQL
        SELECT
            l.ESI,
            l.CONTRAT,
            l.INTITULE,
            l.NOM,
            l.PRENOM,
            l.ADRESSE,
            l.TELEPHONE,
            l.EMAIL,
            l.DATE_ENTREE,
            l.DATE_SORTIE,
            l.STATUT
        FROM LOCATAIRES l
        WHERE l.ESI = :esi
        SQL;

        $result = $this->connection
            ->executeQuery($sql, ['esi' => $esi])
            ->fetchAssociative();

        return $result ?: null;
    }
} 