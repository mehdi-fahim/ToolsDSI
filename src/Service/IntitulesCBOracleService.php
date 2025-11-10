<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

class IntitulesCBOracleService
{
    private Connection $defaultConnection;
    private Connection $etudesConnection;

    public function __construct(Connection $defaultConnection, Connection $etudesConnection)
    {
        $this->defaultConnection = $defaultConnection;
        $this->etudesConnection = $etudesConnection;
    }

    /**
     * Récupère les intitulés déjà présents dans la table INTERNET_INTITULES (avec pagination)
     */
    public function fetchExistingIntitulesPaginated(?string $search, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = max(1, $limit);
        $offset = ($page - 1) * $limit;

        $baseSql = 'FROM etudes.internet_intitules';
        $where = '';
        $params = [];
        $types = [];

        if ($search !== null && $search !== '') {
            $where = " WHERE (TO_CHAR(caint_num) LIKE :search OR UPPER(nom) LIKE :search)";
            $params['search'] = '%' . strtoupper($search) . '%';
            $types['search'] = \PDO::PARAM_STR;
        }

        $countSql = "SELECT COUNT(*) {$baseSql}{$where}";
        $dataSql = <<<SQL
SELECT
    caint_num AS CAINT_NUM,
    nom AS NOM,
    mdp AS MDP,
    datecre AS DATECRE
{$baseSql}{$where}
ORDER BY caint_num
OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY
SQL;

        $paramsWithPagination = $params + [
            'offset' => $offset,
            'limit' => $limit,
        ];
        $typesWithPagination = $types + [
            'offset' => ParameterType::INTEGER,
            'limit' => ParameterType::INTEGER,
        ];

        $data = $this->queryWithFallback($dataSql, $paramsWithPagination, preferEtudes: true, types: $typesWithPagination);
        $total = (int) $this->querySingleValueWithFallback($countSql, $params, preferEtudes: true, types: $types);
        $totalPages = (int) max(1, ceil($total / $limit));

        return [
            'data' => $data,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'totalPages' => $totalPages,
            ],
        ];
    }

    /**
     * Récupère les intitulés présents dans GL mais absents d'INTERNET_INTITULES (avec pagination)
     */
    public function fetchMissingIntitulesPaginated(?string $search, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = max(1, $limit);
        $offset = ($page - 1) * $limit;

        $baseSql = <<<SQL
SELECT DISTINCT
    g3.caint_num AS CAINT_NUM,
    DECODE(NVL(g3.glrec_titre, 'vide'), 'vide', '', g3.glrec_titre || ' ') ||
    DECODE(NVL(g3.glrec_intcou2, 'vide'), 'vide', g3.glrec_intcou1, g3.glrec_intcou1 || ' ' || g3.glrec_intcou2) AS NOM,
    REPLACE(TRIM(SUBSTR(TRIM(g3.glrec_intcou1), 1, 4)), '''', '') AS MDP,
    SYSDATE AS DATECRE
FROM opulise.glrec g3
JOIN opulise.glrfc g2 ON g2.glrec_num = g3.glrec_num
JOIN opulise.glcon g1 ON g1.glcon_num = g2.glcon_num AND g1.glcon_numver = g2.glcon_numver
WHERE g1.glcon_temca = 'F'
  AND g1.glcon_dtf IS NULL
  AND g3.caint_num IS NOT NULL
  AND NOT EXISTS (
        SELECT 1
        FROM etudes.internet_intitules ii
        WHERE ii.caint_num = g3.caint_num
          AND ii.mdp = REPLACE(TRIM(SUBSTR(TRIM(g3.glrec_intcou1), 1, 4)), '''', '')
    )
SQL;

        $outerSql = "SELECT CAINT_NUM, NOM, MDP, DATECRE FROM ({$baseSql}) t";

        $params = [];
        $types = [];
        if ($search !== null && $search !== '') {
            $outerSql .= " WHERE (TO_CHAR(t.CAINT_NUM) LIKE :search OR UPPER(t.NOM) LIKE :search)";
            $params['search'] = '%' . strtoupper($search) . '%';
            $types['search'] = \PDO::PARAM_STR;
        }

        $countSql = "SELECT COUNT(*) FROM ({$outerSql})";

        $dataSql = $outerSql . " ORDER BY t.CAINT_NUM OFFSET :offset ROWS FETCH NEXT :limit ROWS ONLY";
        $paramsWithPagination = $params + [
            'offset' => $offset,
            'limit' => $limit,
        ];
        $typesWithPagination = $types + [
            'offset' => ParameterType::INTEGER,
            'limit' => ParameterType::INTEGER,
        ];

        $data = $this->queryWithFallback($dataSql, $paramsWithPagination, preferEtudes: false, types: $typesWithPagination);
        $total = (int) $this->querySingleValueWithFallback($countSql, $params, preferEtudes: false, types: $types);
        $totalPages = (int) max(1, ceil($total / $limit));

        return [
            'data' => $data,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'totalPages' => $totalPages,
            ],
        ];
    }

    /**
     * Raccourcis sans pagination (compatibilité)
     */
    public function fetchExistingIntitules(?string $search = null): array
    {
        return $this->fetchExistingIntitulesPaginated($search, 1, 10000)['data'];
    }

    public function fetchMissingIntitules(?string $search = null): array
    {
        return $this->fetchMissingIntitulesPaginated($search, 1, 10000)['data'];
    }

    /**
     * Insère une liste d'intitulés dans INTERNET_INTITULES
     */
    public function insertIntitules(array $rows): int
    {
        if (empty($rows)) {
            return 0;
        }

        $sql = <<<SQL
INSERT INTO etudes.internet_intitules (caint_num, nom, mdp, datecre)
VALUES (:caint_num, :nom, :mdp, SYSDATE)
SQL;

        $inserted = 0;
        foreach ($rows as $row) {
            if (!isset($row['CAINT_NUM'], $row['NOM'], $row['MDP'])) {
                continue;
            }

            $params = [
                'caint_num' => $row['CAINT_NUM'],
                'nom' => $row['NOM'],
                'mdp' => $row['MDP'],
            ];

            try {
                $inserted += $this->executeStatementWithFallback($sql, $params);
            } catch (\Throwable $e) {
                // Entrée déjà présente : ignorer silencieusement
                if (str_contains($e->getMessage(), 'ORA-00001')) {
                    continue;
                }
                throw $e;
            }
        }

        return $inserted;
    }

    /**
     * Exporte tous les intitulés sous forme de CSV (sans entête)
     */
    public function exportAllIntitules(): string
    {
        $sql = "SELECT caint_num AS NO_INTITULE, nom, mdp AS CLE FROM etudes.internet_intitules ORDER BY caint_num";
        $rows = $this->queryWithFallback($sql, [], preferEtudes: true);
        return $this->toCsvWithoutHeader($rows);
    }

    public function generateCsvForToday(): string
    {
        $sql = "SELECT caint_num AS NO_INTITULE, nom, mdp AS CLE FROM internet_intitules WHERE TRUNC(datecre) = TRUNC(SYSDATE) ORDER BY caint_num";

        // Appeler la procédure avec un argument vide
        try {
            $this->defaultConnection->executeStatement('BEGIN INS_INTERNET_INTITULE(:p); END;', ['p' => '']);
            $rows = $this->defaultConnection->executeQuery($sql)->fetchAllAssociative();
            return $this->toCsv($rows);
        } catch (\Throwable $e) {
            // Fallback: connexion ETUDES
            try {
                $this->etudesConnection->executeStatement('BEGIN INS_INTERNET_INTITULE(:p); END;', ['p' => '']);
                $rows = $this->etudesConnection->executeQuery($sql)->fetchAllAssociative();
                return $this->toCsv($rows);
            } catch (\Throwable $e2) {
                // Dernier essai: qualifier le schéma ETUDES
                $this->etudesConnection->executeStatement('BEGIN ETUDES.INS_INTERNET_INTITULE(:p); END;', ['p' => '']);
                $rows = $this->etudesConnection->executeQuery($sql)->fetchAllAssociative();
                return $this->toCsv($rows);
            }
        }
    }

    private function queryWithFallback(string $sql, array $params = [], bool $preferEtudes = true, array $types = []): array
    {
        $connections = $preferEtudes
            ? [$this->etudesConnection, $this->defaultConnection]
            : [$this->defaultConnection, $this->etudesConnection];

        $lastException = null;
        foreach ($connections as $connection) {
            try {
                return $connection->executeQuery($sql, $params, $types)->fetchAllAssociative();
            } catch (\Throwable $e) {
                $lastException = $e;
            }
        }

        throw $lastException ?? new \RuntimeException('Impossible d\'exécuter la requête.');
    }

    private function querySingleValueWithFallback(string $sql, array $params = [], bool $preferEtudes = true, array $types = [])
    {
        $connections = $preferEtudes
            ? [$this->etudesConnection, $this->defaultConnection]
            : [$this->defaultConnection, $this->etudesConnection];

        $lastException = null;
        foreach ($connections as $connection) {
            try {
                return $connection->executeQuery($sql, $params, $types)->fetchOne();
            } catch (\Throwable $e) {
                $lastException = $e;
            }
        }

        throw $lastException ?? new \RuntimeException('Impossible d\'exécuter la requête.');
    }

    private function executeStatementWithFallback(string $sql, array $params = [], array $types = []): int
    {
        $connections = [$this->etudesConnection, $this->defaultConnection];
        $lastException = null;

        foreach ($connections as $connection) {
            try {
                return $connection->executeStatement($sql, $params, $types);
            } catch (\Throwable $e) {
                $lastException = $e;
            }
        }

        throw $lastException ?? new \RuntimeException('Impossible d\'exécuter l\'instruction.');
    }

    private function toCsv(array $rows): string
    {
        $output = fopen('php://temp', 'r+');
        // En-têtes
        fputcsv($output, ['NO_INTITULE', 'NOM', 'CLE'], ';');
        foreach ($rows as $row) {
            fputcsv($output, [
                $row['NO_INTITULE'] ?? '',
                $row['NOM'] ?? '',
                $row['CLE'] ?? '',
            ], ';');
        }
        rewind($output);
        return stream_get_contents($output) ?: '';
    }

    private function toCsvWithoutHeader(array $rows): string
    {
        $output = fopen('php://temp', 'r+');

        foreach ($rows as $row) {
            fputcsv($output, [
                $row['NO_INTITULE'] ?? '',
                $row['NOM'] ?? '',
                $row['CLE'] ?? '',
            ], ';');
        }

        rewind($output);
        return stream_get_contents($output) ?: '';
    }
}


