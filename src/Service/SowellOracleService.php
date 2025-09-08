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

    public function listUsersPaginated(int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;
        $countSql = "SELECT COUNT(*) FROM SOWELL_V_USER";
        $total = (int) $this->connection->executeQuery($countSql)->fetchOne();

        $sql = "SELECT FIRST_NAME, LAST_NAME, CODE, EMAIL FROM SOWELL_V_USER ORDER BY LAST_NAME, FIRST_NAME OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY";
        $data = $this->connection->executeQuery($sql, [
            'offset' => $offset,
            'limit' => $limit,
        ], [
            'offset' => \Doctrine\DBAL\ParameterType::INTEGER,
            'limit' => \Doctrine\DBAL\ParameterType::INTEGER,
        ])->fetchAllAssociative();

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => (int) ceil($total / $limit),
        ];
    }

    public function findUserByEmail(string $email): ?array
    {
        $sql = "SELECT FIRST_NAME, LAST_NAME, CODE, EMAIL FROM SOWELL_V_USER WHERE UPPER(EMAIL) = UPPER(:email)";
        $row = $this->connection->executeQuery($sql, ['email' => $email])->fetchAssociative();
        return $row ?: null;
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

    public function tiersExists(string $code): bool
    {
        $sql = "SELECT COUNT(*) FROM TOZD2 WHERE TOTIE_COD = :code";
        $count = (int) $this->connection->executeQuery($sql, ['code' => $code])->fetchOne();
        return $count > 0;
    }

    public function createTozd2RecordForCode(string $code, string $email): int
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
            :code,
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
            'code' => $code,
            'email' => $email,
        ]);
    }

    public function addUserAccess(string $code): int
    {
        $sql = "INSERT INTO SOWELL_USERS_ACCESS (TOTIE_COD) VALUES (:code)";
        return $this->connection->executeStatement($sql, ['code' => $code]);
    }

    public function removeUserAccessByEmail(string $email): int
    {
        $sql = "DELETE FROM SOWELL_USERS_ACCESS WHERE TOTIE_COD IN (
            SELECT TOTIE_COD FROM TOZD2 WHERE UPPER(TOZD2_VALPHA) = UPPER(:email)
        )";
        return $this->connection->executeStatement($sql, ['email' => $email]);
    }
}


