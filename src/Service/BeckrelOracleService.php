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
}


