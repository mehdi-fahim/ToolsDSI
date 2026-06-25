<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ExtractionFlemmingOracleService
{
    public function __construct(private DatabaseConnectionResolver $connectionResolver)
    {
    }

    private function getConnection(): Connection
    {
        return $this->connectionResolver->getConnection();
    }

    public function countIntitulesCharges(): int
    {
        return (int) $this->getConnection()->executeQuery('SELECT COUNT(*) FROM SP_LOC_PARTIS')->fetchOne();
    }

    public function countColibeauCharges(): int
    {
        return (int) $this->getConnection()->executeQuery('SELECT COUNT(*) FROM flemming_colibeau')->fetchOne();
    }

    public function loadIntitulesFromText(string $text): int
    {
        $connection = $this->getConnection();
        $connection->beginTransaction();

        try {
            $connection->executeStatement('DELETE FROM SP_LOC_PARTIS');

            $inserted = 0;
            foreach (preg_split('/\R/', $text) as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }

                $connection->executeStatement(
                    'INSERT INTO SP_LOC_PARTIS (CAINT_NUM) VALUES (:caint)',
                    ['caint' => $line]
                );
                $inserted++;
            }

            $connection->executeStatement('DELETE FROM SP_LOC_PARTIS WHERE CAINT_NUM IS NULL');
            $connection->commit();

            return $inserted;
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    public function generateFlemmingCsv(): string
    {
        $this->getConnection()->executeStatement('BEGIN Genere_flemming(:p); END;', ['p' => '']);

        $rows = $this->getConnection()
            ->executeQuery('SELECT * FROM flemming ORDER BY 1')
            ->fetchAllAssociative();

        if ($rows === []) {
            return $this->rowsToCsv([], []);
        }

        $columns = array_keys($rows[0]);

        return $this->rowsToCsv($rows, $columns);
    }

    public function loadColibeauFromCsvFile(UploadedFile $file): int
    {
        $path = $file->getRealPath();
        if ($path === false) {
            throw new \RuntimeException('Fichier CSV invalide.');
        }

        $parsed = $this->parseCsvFile($path);
        if ($parsed['headers'] === [] || $parsed['data'] === []) {
            throw new \RuntimeException('Le fichier CSV est vide ou invalide.');
        }

        $connection = $this->getConnection();
        $connection->beginTransaction();

        try {
            $connection->executeStatement('DELETE FROM flemming_colibeau');

            $columns = array_map([$this, 'normalizeColumnName'], $parsed['headers']);
            $placeholders = implode(', ', array_map(static fn (string $col) => ':' . strtolower($col), $columns));
            $columnList = implode(', ', $columns);
            $sql = sprintf('INSERT INTO flemming_colibeau (%s) VALUES (%s)', $columnList, $placeholders);

            $inserted = 0;
            foreach ($parsed['data'] as $row) {
                $params = [];
                foreach ($parsed['headers'] as $index => $header) {
                    $params[strtolower($this->normalizeColumnName($header))] = $row[$header] ?? '';
                }
                $connection->executeStatement($sql, $params);
                $inserted++;
            }

            $connection->commit();

            return $inserted;
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    public function chargerMouvements(int $classeur, int $lot): int
    {
        $sql = <<<SQL
INSERT INTO camov (
    TOTIE_CODSOC, CALOT_CLANUM, CALOT_NUM, CAMOV_NUM, MGDEV_COD, CAINT_NUM,
    CAMOV_TYP, CAMOV_MNTD, CAMOV_MNTC, CAMOV_LIB, CARDC_COD, TOTIE_CODSOCINT,
    CAMOV_UTICRE, CAMOV_DATCRE
)
SELECT
    1,
    :classeur,
    :lot,
    camov_seq.nextval,
    'EUR',
    refcli,
    'RVTLOCPART',
    0,
    VERSEMENT_CLIENT,
    'Recouvrement locataire parti',
    'CL',
    1,
    'ODSI',
    TRUNC(SYSDATE)
FROM flemming_colibeau a
WHERE refcli IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM camov b
      WHERE TOTIE_CODSOC = 1
        AND CALOT_CLANUM = :classeurCheck
        AND CALOT_NUM = :lotCheck
        AND CAINT_NUM = a.refcli
  )
SQL;

        return $this->getConnection()->executeStatement($sql, [
            'classeur' => $classeur,
            'lot' => $lot,
            'classeurCheck' => $classeur,
            'lotCheck' => $lot,
        ]);
    }

    /**
     * @return array{headers: list<string>, data: list<array<string, string>>}
     */
    private function parseCsvFile(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Impossible de lire le fichier CSV.');
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            return ['headers' => [], 'data' => []];
        }

        $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';
        rewind($handle);

        $headers = [];
        $data = [];
        $rowIndex = 0;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($rowIndex === 0) {
                $headers = array_map('trim', $row);
            } else {
                $rowData = [];
                foreach ($headers as $index => $header) {
                    if ($header === '') {
                        continue;
                    }
                    $rowData[$header] = isset($row[$index]) ? trim((string) $row[$index]) : '';
                }
                if ($this->rowHasValue($rowData)) {
                    $data[] = $rowData;
                }
            }
            $rowIndex++;
        }

        fclose($handle);

        return ['headers' => $headers, 'data' => $data];
    }

    /**
     * @param array<string, string> $row
     */
    private function rowHasValue(array $row): bool
    {
        foreach ($row as $value) {
            if (trim($value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function normalizeColumnName(string $name): string
    {
        $name = strtoupper(trim($name));
        $name = preg_replace('/[^A-Z0-9_]/', '_', $name) ?? $name;

        return trim($name, '_');
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<string> $columns
     */
    private function rowsToCsv(array $rows, array $columns): string
    {
        $output = fopen('php://temp', 'r+');
        if ($columns !== []) {
            fputcsv($output, $columns, ';');
        }

        foreach ($rows as $row) {
            $line = [];
            foreach ($columns as $column) {
                $line[] = $row[$column] ?? $row[strtolower($column)] ?? '';
            }
            fputcsv($output, $line, ';');
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return "\xEF\xBB\xBF" . ($csv ?: '');
    }
}
