<?php
namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

class UtilisateurOracleService
{
    public function __construct(private DatabaseConnectionResolver $connectionResolver)
    {
    }

    private function getConnection(): Connection
    {
        return $this->connectionResolver->getConnection();
    }

    public function fetchUtilisateurs(string $search = '', int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;

        // Requête de base
        $baseSql = <<<SQL
        FROM MGUTI
        WHERE MGUTI_ETA = 'A'
        SQL;

        // Conditions et paramètres de recherche
        $whereConditions = [];
        $params = [];

        if (!empty($search)) {
            $whereConditions[] = "(UPPER(MGUTI_COD) LIKE UPPER(:search) OR UPPER(MGUTI_NOM) LIKE UPPER(:search) OR UPPER(MGUTI_PRENOM) LIKE UPPER(:search))";
            $params['search'] = '%' . $search . '%';
        }

        if (!empty($whereConditions)) {
            $baseSql .= ' AND ' . implode(' AND ', $whereConditions);
        }

        // 1. Récupération du total
        $countSql = "SELECT COUNT(*) as total " . $baseSql;
        $total = $this->getConnection()
            ->executeQuery($countSql, $params)
            ->fetchOne();

        // 2. Récupération des données paginées
        $sql = <<<SQL
        SELECT
            TOTIE_COD AS NUM_TIERS,
            MGUTI_COD AS CODE_UTILISATEUR,
            MGGUT_COD AS GROUPE,
            MGUTI_NOM AS NOM,
            MGUTI_PRENOM AS PRENOM,
            MGUTI_ETA AS ETAT,
            MGGUT_CODWEB AS CODE_WEB,
            MGMWB_COD AS CODE_ULIS,
            MGUTI_DERCON AS DERNIERE_CONNEXION
        $baseSql
        ORDER BY TOTIE_COD
        OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY
        SQL;

        // Fusion des paramètres
        $params['offset'] = $offset;
        $params['limit'] = $limit;

        // Types des paramètres pour OFFSET et LIMIT
        $types = [
            'offset' => ParameterType::INTEGER,
            'limit' => ParameterType::INTEGER,
        ];

        $data = $this->getConnection()
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

    public function fetchUtilisateurById(string $codeUtilisateur): ?array
    {
        $sql = <<<SQL
        SELECT
            TOTIE_COD AS NUM_TIERS,
            MGUTI_COD AS CODE_UTILISATEUR,
            MGGUT_COD AS GROUPE,
            MGUTI_NOM AS NOM,
            MGUTI_PRENOM AS PRENOM,
            MGUTI_ETA AS ETAT,
            MGGUT_CODWEB AS CODE_WEB,
            MGMWB_COD AS CODE_ULIS,
            TO_CHAR(MGUTI_DERCON, 'YYYY-MM-DD HH24:MI:SS') AS DERNIERE_CONNEXION
        FROM MGUTI
        WHERE MGUTI_ETA = 'A'
            AND MGUTI_COD = :codeUtilisateur
        ORDER BY TOTIE_COD
        SQL;
        

        $result = $this->getConnection()
            ->executeQuery($sql, ['codeUtilisateur' => $codeUtilisateur])
            ->fetchAssociative();

        return $result ?: null;
    }

    public function authenticateUser(string $userId, string $password): ?array
    {
        $sql = <<<SQL
        SELECT 
            MGUTI_COD AS USER_ID, 
            motdepasse(mguti_cod) AS MOT_DE_PASEE 
        FROM MGUTI 
        WHERE MGUTI_COD = :userId
        SQL;

        $result = $this->getConnection()
            ->executeQuery($sql, ['userId' => $userId])
            ->fetchAssociative();

        if (!$result) {
            return null; // Utilisateur non trouvé
        }

        // Vérifier si le mot de passe correspond
        if ($result['MOT_DE_PASEE'] === $password) {
            return $this->fetchUtilisateurById($userId);
        }

        return null; // Mot de passe incorrect
    }
}
