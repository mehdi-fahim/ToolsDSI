<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class AccessControlOracleService
{
    public function __construct(private DatabaseConnectionResolver $connectionResolver)
    {
    }

    private function getConnection(): Connection
    {
        return $this->connectionResolver->getConnection();
    }

    public function isAdmin(string $userId): bool
    {
        try {
            $sql = "SELECT IS_ADMIN FROM ADM_ADMIN WHERE ID_UTILISATEUR = :id";
            $result = $this->getConnection()->executeQuery($sql, ['id' => strtoupper($userId)])->fetchOne();
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
            $result = $this->getConnection()->executeQuery($sql, [
                'id' => strtoupper($userId),
                'code' => $codePage,
            ])->fetchOne();
            return $result !== false && $result !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getUserPageAccess(string $userId): array
    {
        $sql = "SELECT CODE_PAGE, NOM_PAGE FROM ADM_PAGE_ACCESS WHERE ID_UTILISATEUR = :id";
        $rows = $this->getConnection()->executeQuery($sql, ['id' => strtoupper($userId)])->fetchAllAssociative();
        $access = [];
        foreach ($rows as $row) {
            $access[$row['CODE_PAGE']] = $row['NOM_PAGE'] ?? $row['CODE_PAGE'];
        }
        return $access;
    }

    /**
     * Retourne la liste des utilisateurs ayant le statut administrateur (ADM_ADMIN.IS_ADMIN = 'Y').
     *
     * @return list<array{ID_UTILISATEUR: string, DATE_ADMIN: string|null}>
     */
    public function getAdminUsers(): array
    {
        try {
            $sql = "SELECT ID_UTILISATEUR, TO_CHAR(DATE_ADMIN, 'DD/MM/YYYY HH24:MI') AS DATE_ADMIN FROM ADM_ADMIN WHERE IS_ADMIN = 'Y' ORDER BY ID_UTILISATEUR";
            $rows = $this->getConnection()->executeQuery($sql)->fetchAllAssociative();
            return $rows;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function setAdminFlag(string $userId, bool $isAdmin): void
    {
        $sql = "MERGE INTO ADM_ADMIN a
USING (SELECT :id AS ID_UTILISATEUR FROM DUAL) s
ON (a.ID_UTILISATEUR = s.ID_UTILISATEUR)
WHEN MATCHED THEN UPDATE SET a.IS_ADMIN = :is_admin, a.DATE_ADMIN = SYSDATE
WHEN NOT MATCHED THEN INSERT (ID_UTILISATEUR, IS_ADMIN, DATE_ADMIN) VALUES (:id, :is_admin, SYSDATE)";
        $this->getConnection()->executeStatement($sql, [
            'id' => strtoupper($userId),
            'is_admin' => $isAdmin ? 'Y' : 'N',
        ]);
    }

    public function replaceUserPageAccess(string $userId, array $codeToLabel): void
    {
        $this->getConnection()->beginTransaction();
        try {
            $this->getConnection()->executeStatement(
                'DELETE FROM ADM_PAGE_ACCESS WHERE ID_UTILISATEUR = :id',
                ['id' => strtoupper($userId)]
            );
            foreach ($codeToLabel as $code => $label) {
                $this->getConnection()->executeStatement(
                    'INSERT INTO ADM_PAGE_ACCESS (ID_UTILISATEUR, CODE_PAGE, NOM_PAGE, DATE_ADMIN) VALUES (:id, :code, :nom, SYSDATE)',
                    [
                        'id' => strtoupper($userId),
                        'code' => $code,
                        'nom' => $label,
                    ]
                );
            }
            $this->getConnection()->commit();
        } catch (\Throwable $e) {
            $this->getConnection()->rollBack();
            throw $e;
        }
    }
}


