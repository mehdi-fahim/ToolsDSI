<?php
namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

class BeckrelOracleService
{
    public function __construct(private DatabaseConnectionResolver $connectionResolver)
    {
    }

    private function getConnection(): Connection
    {
        return $this->getConnection()Resolver->getConnection();
    }

    public function listUsers(): array
    {
        $sql = "SELECT EMAIL, PHONE FROM BECKREL_V_USERS ORDER BY EMAIL";
        return $this->getConnection()->executeQuery($sql)->fetchAllAssociative();
    }

    public function listUsersPaginated(int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;
        $countSql = "SELECT COUNT(*) FROM BECKREL_V_USERS";
        $total = (int) $this->getConnection()->executeQuery($countSql)->fetchOne();

        $sql = "SELECT EMAIL, PHONE FROM BECKREL_V_USERS ORDER BY EMAIL OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY";
        $data = $this->getConnection()->executeQuery($sql, [
            'offset' => $offset,
            'limit' => $limit,
        ], [
            'offset' => ParameterType::INTEGER,
            'limit' => ParameterType::INTEGER,
        ])->fetchAllAssociative();

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => (int) ceil($total / $limit),
        ];
    }

    public function tiersExists(string $numeroTiers): bool
    {
        $sql = "SELECT COUNT(*) FROM TOZD2 WHERE TOTIE_COD = :tiers";
        $count = (int) $this->getConnection()->executeQuery($sql, ['tiers' => $numeroTiers])->fetchOne();
        return $count > 0;
    }

    public function ensureEmailInTozd2(string $numeroTiers, string $email): int
    {
        // Remplit TOZD2_VALPHA avec l'email (mise à jour inconditionnelle)
        $sql = "UPDATE TOZD2 SET TOZD2_VALPHA = :email WHERE TOTIE_COD = :tiers";
        return $this->getConnection()->executeStatement($sql, [
            'email' => $email,
            'tiers' => $numeroTiers,
        ]);
    }

    public function addUserAccess(string $numeroTiers): int
    {
        // Hypothèse: table minimaliste avec TOTIE_COD; ajuste si d'autres colonnes sont requises
        $sql = "INSERT INTO BECKREL_USERS_ACCESS (TOTIE_COD) VALUES (:tiers)";
        return $this->getConnection()->executeStatement($sql, ['tiers' => $numeroTiers]);
    }

    public function removeUserAccessByEmail(string $email): int
    {
        // Supprime l'accès en retrouvant le TOTIE_COD via TOZD2 (email stocké dans TOZD2_VALPHA)
        $sql = "DELETE FROM BECKREL_USERS_ACCESS WHERE TOTIE_COD IN (
            SELECT TOTIE_COD FROM TOZD2 WHERE UPPER(TOZD2_VALPHA) = UPPER(:email)
        )";
        return $this->getConnection()->executeStatement($sql, ['email' => $email]);
    }

    public function createTozd2Record(string $numeroTiers, string $email): int
    {
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

        return $this->getConnection()->executeStatement($sql, [
            'tiers' => $numeroTiers,
            'email' => $email,
        ]);
    }
}


