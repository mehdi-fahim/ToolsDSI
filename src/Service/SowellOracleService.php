<?php
namespace App\Service;

use Doctrine\DBAL\Connection;

class SowellOracleService
{
    private Connection $connection;

    public function __construct(Connection $defaultConnection)
    {
        $this->connection = $defaultConnection;
    }

    public function listUsers(): array
    {
        $sql = "SELECT FIRST_NAME, LAST_NAME, CODE, EMAIL FROM SOWELL_V_USER ORDER BY LAST_NAME, FIRST_NAME";
        return $this->connection->executeQuery($sql)->fetchAllAssociative();
    }

    public function setEmailInTozd2ForCode(string $code, string $email): int
    {
        // Met TOZD2_VALPHA = email pour le TOTIE_COD = CODE (on ne demande pas le tiers en entrée)
        $sql = "UPDATE TOZD2 SET TOZD2_VALPHA = :email WHERE TOTIE_COD = :code";
        return $this->connection->executeStatement($sql, [
            'email' => $email,
            'code' => $code,
        ]);
    }
}


