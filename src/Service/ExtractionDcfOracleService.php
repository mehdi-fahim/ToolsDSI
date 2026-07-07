<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class ExtractionDcfOracleService
{
    private const EXPORT_TABLES = [
        'encaissement' => 'DCF_ENCAISSEMENT',
        'facturation' => 'DCF_FACTURATION',
        'dettes' => 'DCF_DETTES',
    ];

    public function __construct(private DatabaseConnectionResolver $connectionResolver)
    {
    }

    private function getConnection(): Connection
    {
        return $this->connectionResolver->getConnection();
    }

    public function runRubanAlim(int $annee, int $mois, string $userId): void
    {
        $this->runProcedure('SP_RUBAN_ALIM', $annee, $mois, $userId);
    }

    public function runEncaissementAlim(int $annee, int $mois, string $userId): void
    {
        $this->runProcedure('ALIM_DCF_ENCAISSEMENT', $annee, $mois, $userId);
    }

    public function runFacturationAlim(int $annee, int $mois, string $userId): void
    {
        $this->runProcedure('ALIM_DCF_FACTURATION', $annee, $mois, $userId);
    }

    public function runDettesAlim(int $annee, int $mois, string $userId): void
    {
        $this->runProcedure('ALIM_DCF_DETTES', $annee, $mois, $userId);
    }

    public function exportRubanCsv(): string
    {
        return $this->queryToCsv('SELECT DISTINCT * FROM SP_RUBAN ORDER BY 1, 2, 4');
    }

    public function exportDcfTableCsv(string $type): string
    {
        if (!isset(self::EXPORT_TABLES[$type])) {
            throw new \InvalidArgumentException('Type d\'export DCF inconnu.');
        }

        return $this->queryToCsv(sprintf('SELECT * FROM %s', self::EXPORT_TABLES[$type]));
    }

    public function exportLoyersCsv(int $annee): string
    {
        $sql = <<<SQL
SELECT
    b.groupe,
    b.paesi_codext,
    fgetsurface(b.paesi_num,'SU') AS SURF_UTILE,
    fgetsurface(b.paesi_num,'SH') AS SURF_HABITABLE,
    fgetsurface(b.paesi_num,'SCOR') AS SURF_CORRIGEE,
    e.NUM_CONVENTION_APL AS NUM_CONVENTION,
    e.convention_apl AS CONVENTION,
    GLLPC_LOYMAX,
    GLLPC_LOYMAX_TH,
    (
        SELECT GLPRI_PRIX * 12
        FROM glpri
        WHERE glgpr_cod = GET_GPRIX_LOYER(b.paesi_num,'L092')
          AND GLPRI_DTD = TO_DATE(:dateDebutAnnee, 'DD/MM/YYYY')
          AND glrub_cod = 'L092'
          AND gltar_cod = 'APPL'
    ) AS LOYER_APPL_M2_AN
FROM syn_pat b
JOIN paces a ON b.paesi_num = a.paesi_num
JOIN pacnv c ON c.pacnv_num = a.pacnv_num
JOIN syn_dpat e ON e.paesi_num = a.paesi_num
LEFT JOIN gllpc d
    ON d.pacnv_num = a.pacnv_num
   AND d.gllpc_dtd = TO_DATE(:dateDebutAnnee, 'DD/MM/YYYY')
   AND d.gllpc_dtf = TO_DATE(:dateFinAnnee, 'DD/MM/YYYY')
ORDER BY 1, 2, 8 DESC
SQL;

        return $this->queryToCsv($sql, [
            'dateDebutAnnee' => sprintf('01/01/%04d', $annee),
            'dateFinAnnee' => sprintf('31/12/%04d', $annee),
        ]);
    }

    public function getLoyerPriceGroupLabel(int $annee, int $mois, string $esiCode): string
    {
        $esiCode = strtoupper(trim($esiCode));
        if ($esiCode === '') {
            throw new \InvalidArgumentException('Veuillez saisir un ESI.');
        }

        $referenceDate = $this->getLastDayOfMonth($annee, $mois)->format('d/m/Y');
        $paesiNum = $this->getConnection()->executeQuery(
            'SELECT paesi_num FROM paesi WHERE paesi_codext = :esi',
            ['esi' => $esiCode]
        )->fetchOne();

        if ($paesiNum === false) {
            throw new \RuntimeException('ESI introuvable.');
        }

        $sql = <<<SQL
SELECT NVL((
    SELECT a.GLGPR_COD
    FROM glgel a, glpri c
    WHERE a.paesi_num = :paesiNum
      AND c.GLGPR_COD = a.GLGPR_COD
      AND c.GLRUB_COD IN (
          SELECT GLRUB_COD
          FROM glrub
          WHERE glfam_cod = 'LOYER'
      )
      AND c.GLTAR_COD = 'RE'
      AND (c.glpri_dtf >= TO_DATE(:referenceDate, 'DD/MM/YYYY') OR c.glpri_dtf IS NULL)
      AND c.glpri_dtd <= TO_DATE(:referenceDate, 'DD/MM/YYYY')
      AND ROWNUM = 1
), 'ABSENT') AS GROUPE_PRIX
FROM dual
SQL;

        $result = $this->getConnection()->executeQuery($sql, [
            'paesiNum' => $paesiNum,
            'referenceDate' => $referenceDate,
        ])->fetchOne();

        return (string) ($result !== false ? $result : 'ABSENT');
    }

    private function runProcedure(string $procedureName, int $annee, int $mois, string $userId): void
    {
        $userId = strtoupper(trim($userId));
        if ($userId === '') {
            throw new \InvalidArgumentException('Utilisateur manquant.');
        }

        $this->getConnection()->executeStatement(
            sprintf('BEGIN %s(:annee, :mois, :userId); END;', $procedureName),
            [
                'annee' => $annee,
                'mois' => $mois,
                'userId' => $userId,
            ]
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    private function queryToCsv(string $sql, array $params = []): string
    {
        $rows = $this->getConnection()->executeQuery($sql, $params)->fetchAllAssociative();

        if ($rows === []) {
            return $this->rowsToCsv([], []);
        }

        return $this->rowsToCsv($rows, array_keys($rows[0]));
    }

    private function getLastDayOfMonth(int $annee, int $mois): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-n-j', sprintf('%04d-%d-1', $annee, $mois));
        if (!$date) {
            throw new \InvalidArgumentException('Période invalide.');
        }

        return $date->modify('last day of this month');
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
