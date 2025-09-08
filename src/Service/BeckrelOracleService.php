<?php
namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

class BeckrelOracleService
{
    private Connection $connection;

    public function __construct(Connection $defaultConnection)
    {
        $this->connection = $defaultConnection;
    }

    public function listUsers(): array
    {
        $sql = "SELECT EMAIL, PHONE FROM BECKREL_V_USERS ORDER BY EMAIL";
        return $this->connection->executeQuery($sql)->fetchAllAssociative();
    }

    public function tiersExists(string $numeroTiers): bool
    {
        $sql = "SELECT COUNT(*) FROM TOZD2 WHERE TOTIE_COD = :tiers";
        $count = (int) $this->connection->executeQuery($sql, ['tiers' => $numeroTiers])->fetchOne();
        return $count > 0;
    }

    public function ensureEmailInTozd2(string $numeroTiers, string $email): int
    {
        // Remplit TOZD2_VALPHA avec l'email (mise à jour inconditionnelle)
        $sql = "UPDATE TOZD2 SET TOZD2_VALPHA = :email WHERE TOTIE_COD = :tiers";
        return $this->connection->executeStatement($sql, [
            'email' => $email,
            'tiers' => $numeroTiers,
        ]);
    }

    public function addUserAccess(string $numeroTiers): int
    {
        // Hypothèse: table minimaliste avec TOTIE_COD; ajuste si d'autres colonnes sont requises
        $sql = "INSERT INTO BECKREL_USERS_ACCESS (TOTIE_COD) VALUES (:tiers)";
        return $this->connection->executeStatement($sql, ['tiers' => $numeroTiers]);
    }

    public function createTozd2Record(string $numeroTiers, string $email): int
    {
        // Crée une ligne dans TOZD2 selon le format fourni
        // Valeurs par défaut: MGZDE_COD='SOWE', MGENT_COD='TODPP', U_VERSION=1, SIT=NULL, TOZD2_VDATE=NULL, TOZD2_VNUM=NULL,
        // TOZD2_UTICRE='SOPRAUNG', TOZD2_DATCRE=NULL, TOZD2_UTIMAJ=NULL, TOZD2_DATMAJ=SYSDATE
        $sql = "INSERT INTO TOZD2 (
            TOTIE_COD,
            MGZDE_COD,
            MGENT_COD,
            U_VERSION,
            SIT,
            TOZD2_VALPHA,
            TOZD2_VDATE,
            TOZD2_VNUM,
            TOZD2_UTICRE,
            TOZD2_DATCRE,
            TOZD2_UTIMAJ,
            TOZD2_DATMAJ
        ) VALUES (
            :tiers,
            'SOWE',
            'TODPP',
            1,
            NULL,
            :email,
            NULL,
            NULL,
            'SOPRAUNG',
            NULL,
            NULL,
            SYSDATE
        )";

        return $this->connection->executeStatement($sql, [
            'tiers' => $numeroTiers,
            'email' => $email,
        ]);
    }
}


