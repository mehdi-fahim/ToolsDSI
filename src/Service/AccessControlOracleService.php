<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class AccessControlOracleService
{
    public function __construct(private Connection $defaultConnection)
    {
    }

    public function isAdmin(string $userId): bool
    {
        try {
            $sql = "SELECT IS_ADMIN FROM ADM_ADMIN WHERE ID_UTILISATEUR = :id";
            $result = $this->defaultConnection->fetchOne($sql, ['id' => strtoupper($userId)]);
            if ($result === false || $result === null) {
                return false;
            }
            return strtoupper((string) $result) === 'Y';
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function hasPageAccess(string $userId, string $codePage): bool
    {
        try {
            $sql = "SELECT 1 FROM ADM_PAGE_ACCESS WHERE ID_UTILISATEUR = :id AND CODE_PAGE = :code";
            $result = $this->defaultConnection->fetchOne($sql, [
                'id' => strtoupper($userId),
                'code' => $codePage,
            ]);
            return $result !== false && $result !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getUserPageAccess(string $userId): array
    {
        $sql = "SELECT CODE_PAGE, NOM_PAGE FROM ADM_PAGE_ACCESS WHERE ID_UTILISATEUR = :id";
        $rows = $this->defaultConnection->fetchAllAssociative($sql, ['id' => strtoupper($userId)]);
        $access = [];
        foreach ($rows as $row) {
            $access[$row['CODE_PAGE']] = $row['NOM_PAGE'] ?? $row['CODE_PAGE'];
        }
        return $access;
    }

    public function setAdminFlag(string $userId, bool $isAdmin): void
    {
        $sql = "MERGE INTO ADM_ADMIN a
USING (SELECT :id AS ID_UTILISATEUR FROM DUAL) s
ON (a.ID_UTILISATEUR = s.ID_UTILISATEUR)
WHEN MATCHED THEN UPDATE SET a.IS_ADMIN = :is_admin, a.DATE_ADMIN = SYSDATE
WHEN NOT MATCHED THEN INSERT (ID_UTILISATEUR, IS_ADMIN, DATE_ADMIN) VALUES (:id, :is_admin, SYSDATE)";
        $this->defaultConnection->executeStatement($sql, [
            'id' => strtoupper($userId),
            'is_admin' => $isAdmin ? 'Y' : 'N',
        ]);
    }

    public function replaceUserPageAccess(string $userId, array $codeToLabel): void
    {
        $this->defaultConnection->beginTransaction();
        try {
            $this->defaultConnection->executeStatement(
                'DELETE FROM ADM_PAGE_ACCESS WHERE ID_UTILISATEUR = :id',
                ['id' => strtoupper($userId)]
            );
            foreach ($codeToLabel as $code => $label) {
                $this->defaultConnection->executeStatement(
                    'INSERT INTO ADM_PAGE_ACCESS (ID_UTILISATEUR, CODE_PAGE, NOM_PAGE, DATE_ADMIN) VALUES (:id, :code, :nom, SYSDATE)',
                    [
                        'id' => strtoupper($userId),
                        'code' => $code,
                        'nom' => $label,
                    ]
                );
            }
            $this->defaultConnection->commit();
        } catch (\Throwable $e) {
            $this->defaultConnection->rollBack();
            throw $e;
        }
    }
}


