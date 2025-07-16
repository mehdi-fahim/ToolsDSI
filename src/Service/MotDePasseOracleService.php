<?php
namespace App\Service;

use Doctrine\DBAL\Connection;

class MotDePasseOracleService
{
    private Connection $connection;

    public function __construct(Connection $defaultConnection)
    {
        $this->connection = $defaultConnection;
    }

    public function getMotDePasseInfo(string $userId): ?array
    {
        $sql = <<<SQL
SELECT
    motdepasse(mguti_cod) AS mdp,
    CASE MGUTI_TEMMDP
        WHEN 0 THEN 'Mdp valide'
        WHEN 1 THEN 'A changer à la prochaine connexion'
        WHEN 2 THEN 'Mdp verrouillé'
    END AS Statut,
    CASE
        WHEN MGUTI_MOTPEXP < sysdate THEN ' - Mot de passe expiré'
        ELSE ''
    END AS expiration
FROM
    mguti
WHERE
    mguti_cod = upper(:userId)
SQL;

        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue('userId', strtoupper($userId));
        $stmt->execute();
        
        $result = $stmt->fetchAssociative();
        return $result ?: null;
    }

    public function debloquerMotDePasse(string $userId): bool
    {
        $sql = <<<SQL
UPDATE MGUTI 
SET MGUTI_TEMMDP = 0 
WHERE MGUTI_COD = upper(:userId)
SQL;

        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue('userId', strtoupper($userId));
        
        try {
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function reinitialiserMotDePasse(string $userId): bool
    {
        $sql = <<<SQL
UPDATE MGUTI 
SET MGUTI_TEMMDP = 1, MGUTI_MOTP = 'ZE19' 
WHERE MGUTI_COD = upper(:userId)
SQL;

        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue('userId', strtoupper($userId));
        
        try {
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function verifierUtilisateurExiste(string $userId): bool
    {
        $sql = <<<SQL
SELECT COUNT(*) as count
FROM MGUTI 
WHERE MGUTI_COD = upper(:userId)
SQL;

        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue('userId', strtoupper($userId));
        $stmt->execute();
        
        $result = $stmt->fetchAssociative();
        return $result && $result['count'] > 0;
    }
} 