<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class BudgetEngagementOracleService
{
  public const CONSOMMATION_COLUMNS = [
        'GBICB_NUM',
        'MGTRT_NUM',
        'TOTIE_CODSCTE',
        'ICEXE_NUM',
        'TOESO_COD',
        'GBACT_COD',
        'GBPOB_COD',
        'GBICB_SENS',
        'PAESI_NUM',
        'GBCON_LIB',
        'GBCON_DAT',
        'GBCON_MNT',
        'GBICB_STATUT',
    ];

    public function __construct(private DatabaseConnectionResolver $connectionResolver)
    {
    }

    private function getConnection(): Connection
    {
        return $this->connectionResolver->getConnection();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getConsommationsRejetees(int $societe, int $exercice): array
    {
        $sql = <<<SQL
SELECT GBICB_NUM,
       MGTRT_NUM,
       TOTIE_CODSCTE,
       ICEXE_NUM,
       TOESO_COD,
       GBACT_COD,
       GBPOB_COD,
       GBICB_SENS,
       PAESI_NUM,
       GBCON_LIB,
       GBCON_DAT,
       GBCON_MNT,
       GBICB_STATUT
FROM GBICB
WHERE GBICB_STATUT = 'R'
  AND TOTIE_CODSCTE = :societe
  AND ICEXE_NUM = :exercice
ORDER BY GBICB_NUM
SQL;

        return $this->getConnection()->executeQuery($sql, [
            'societe' => $societe,
            'exercice' => $exercice,
        ])->fetchAllAssociative();
    }

    public function basculerEsoQuitt(int $societe, int $exercice): int
    {
        return $this->getConnection()->executeStatement(
            <<<SQL
UPDATE GBICB
SET TOESO_COD = 'QUITT'
WHERE GBICB_STATUT = 'R'
  AND TOTIE_CODSCTE = :societe
  AND ICEXE_NUM = :exercice
SQL,
            [
                'societe' => $societe,
                'exercice' => $exercice,
            ]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getListeEngagements(int $exercice): array
    {
        return $this->getConnection()->executeQuery(
            'SELECT * FROM eng_temp2 WHERE exercice = :exercice',
            ['exercice' => $exercice]
        )->fetchAllAssociative();
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function exportRowsToCsv(array $rows): string
    {
        if ($rows === []) {
            return $this->rowsToCsv([], []);
        }

        $columns = array_keys($rows[0]);

        return $this->rowsToCsv($rows, $columns);
    }

    public function exportConsommationsRejeteesCsv(int $societe, int $exercice): string
    {
        return $this->exportRowsToCsv($this->getConsommationsRejetees($societe, $exercice));
    }

    public function exportListeEngagementsCsv(int $exercice): string
    {
        return $this->exportRowsToCsv($this->getListeEngagements($exercice));
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
