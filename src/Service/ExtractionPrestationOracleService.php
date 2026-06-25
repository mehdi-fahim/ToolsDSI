<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class ExtractionPrestationOracleService
{
    private const COLUMNS = [
        'TAMAC_NUM',
        'TAVMA_NUMVER',
        'TAMAL_COD',
        'TAMAP_COD',
        'TOTIE_CODINTE',
        'TAPRE_COD',
        'TAFPR_COD',
        'TACET_COD',
        'PLUNO_COD',
        'MGNAD_CODR',
        'MGNAD_CODNR',
        'TAMAP_LIB',
        'TAMAP_PRXINIT',
        'TAMAP_PRXACTU',
        'TAMAP_QTEPREV',
        'TAMAP_MNTPRES',
        'TAMAP_MNTACTU',
        'TAMAP_TAUXTVA',
        'TAMAP_DATDEB',
        'TAMAP_DUREE',
        'TAMAP_UNITEMP',
        'TAMAP_DATFIN',
        'TAMAP_VALPRDE',
        'TAMAP_UNIPRDE',
        'TAMAP_TEMACTU',
        'TAMAP_TEMREVI',
        'TAMAP_PCTRECUP',
        'TAMAP_INDBASE',
        'TAMAP_CODBASE',
        'TAMAP_LIBCFAC',
        'TAMAP_LIBCEXE',
        'MGDEV_COD',
        'TAOBS_NUM',
        'TAMAP_UTICRE',
        'TAMAP_UTIMAJ',
        'TAMAP_DATCRE',
        'TAMAP_DATMAJ',
        'PLTCH_CPT',
        'TAMAP_PXINI_ORI',
        'TAMAP_MNTPRES_ORI',
        'TAMAP_DATREV',
        'TAMAP_COEFREV',
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
    public function fetchPrestations(int $marche, string $lot): array
    {
        $lot = trim($lot);
        if ($marche <= 0 || $lot === '') {
            return [];
        }

        $selectList = implode(', ', self::COLUMNS);

        $sql = <<<SQL
SELECT {$selectList}
FROM TAMAP a
WHERE tamac_num = :marche
  AND tamal_cod = :lot
  AND tavma_numver = (
      SELECT MAX(tavma_numver)
      FROM tamap b
      WHERE b.tamac_num = a.tamac_num
  )
SQL;

        return $this->getConnection()->executeQuery($sql, [
            'marche' => $marche,
            'lot' => $lot,
        ])->fetchAllAssociative();
    }

    public function generateCsv(int $marche, string $lot): string
    {
        $rows = $this->fetchPrestations($marche, $lot);

        $output = fopen('php://temp', 'r+');
        fputcsv($output, self::COLUMNS, ';');

        foreach ($rows as $row) {
            $line = [];
            foreach (self::COLUMNS as $column) {
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
