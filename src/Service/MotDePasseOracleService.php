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
    motdepasse(mguti_cod) AS "mdp",
    CASE MGUTI_TEMMDP
        WHEN 0 THEN 'Mdp valide'
        WHEN 1 THEN 'A changer à la prochaine connexion'
        WHEN 2 THEN 'Mdp verrouillé'
    END AS "Statut",
    CASE
        WHEN MGUTI_MOTPEXP < sysdate THEN ' - Mot de passe expiré'
        ELSE ''
    END AS "expiration"
FROM
    mguti
WHERE
    mguti_cod = upper(:userId)
SQL;

        $result = $this->connection
            ->executeQuery($sql, ['userId' => strtoupper($userId)])
            ->fetchAssociative();

        return $result ?: null;
    }

    public function debloquerMotDePasse(string $userId): bool
    {
        $sql = <<<SQL
UPDATE MGUTI 
SET MGUTI_TEMMDP = 0 
WHERE MGUTI_COD = upper(:userId)
SQL;

        try {
            $affected = $this->connection
                ->executeStatement($sql, ['userId' => strtoupper($userId)]);
            return $affected > 0;
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

        try {
            $affected = $this->connection
                ->executeStatement($sql, ['userId' => strtoupper($userId)]);
            return $affected > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function changerMotDePasse(string $userId, string $nouveauMotDePasse): bool
    {
        $sql = <<<SQL
UPDATE MGUTI
SET MGUTI_MOTP = :nouveauMotDePasse,
    MGUTI_TEMMDP = 0
WHERE MGUTI_COD = upper(:userId)
SQL;

        try {
            $affected = $this->connection
                ->executeStatement($sql, [
                    'nouveauMotDePasse' => strtoupper($nouveauMotDePasse),
                    'userId' => strtoupper($userId)
                ]);

            return $affected > 0;
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

        $count = $this->connection
            ->executeQuery($sql, ['userId' => strtoupper($userId)])
            ->fetchOne();

        return $count > 0;
    }
}
