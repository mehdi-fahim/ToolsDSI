<?php
namespace App\Service;

use Doctrine\DBAL\Connection;

class UtilisateurOracleService
{
    private Connection $connection;

    public function __construct(Connection $defaultConnection)
    {
        // Utilise la connexion par défaut (Oracle, selon DATABASE_URL)
        $this->connection = $defaultConnection;
    }

    public function fetchUtilisateurs(string $search = '', int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;
        
        // Requête de base
        $baseSql = <<<SQL
        FROM MGUTI
        WHERE MGUTI_ETA = 'A'
        SQL;

        // Ajouter la recherche si spécifiée
        $whereConditions = [];
        $params = [];
        
        if (!empty($search)) {
            $whereConditions[] = "(UPPER(MGUTI_COD) LIKE UPPER(:search) OR UPPER(MGUTI_NOM) LIKE UPPER(:search) OR UPPER(MGUTI_PRENOM) LIKE UPPER(:search))";
            $params['search'] = '%' . $search . '%';
        }
        
        if (!empty($whereConditions)) {
            $baseSql .= ' AND ' . implode(' AND ', $whereConditions);
        }

        // Requête pour compter le total
        $countSql = "SELECT COUNT(*) as total " . $baseSql;
        $countStmt = $this->connection->prepare($countSql);
        $countStmt->execute($params);
        $total = $countStmt->fetchAssociative()['total'];

        // Requête principale avec pagination
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
SQL . $baseSql . <<<SQL
ORDER BY TOTIE_COD
OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY
SQL;

        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue('offset', $offset, \Doctrine\DBAL\ParameterType::INTEGER);
        $stmt->bindValue('limit', $limit, \Doctrine\DBAL\ParameterType::INTEGER);
        
        // Ajouter les paramètres de recherche
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        $data = $stmt->fetchAllAssociative();

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => ceil($total / $limit)
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
    MGUTI_DERCON AS DERNIERE_CONNEXION
FROM MGUTI
WHERE MGUTI_ETA = 'A'
    AND MGUTI_COD = :codeUtilisateur
ORDER BY TOTIE_COD
SQL;

        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue('codeUtilisateur', $codeUtilisateur);
        $stmt->execute();
        
        $result = $stmt->fetchAssociative();
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

        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue('userId', $userId);
        $stmt->execute();
        
        $result = $stmt->fetchAssociative();
        
        if (!$result) {
            return null; // Utilisateur non trouvé
        }
        
        // Vérifier si le mot de passe correspond
        if ($result['MOT_DE_PASEE'] === $password) {
            // Récupérer les informations complètes de l'utilisateur
            return $this->fetchUtilisateurById($userId);
        }
        
        return null; // Mot de passe incorrect
    }
} 