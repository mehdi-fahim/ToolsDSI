<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class ExtractionSuiviGestionOracleService
{
    private const CONTENTIEUX_COLUMNS = [
        'AGENCE',
        'UTILISATEUR',
        'GROUPE',
        'ESI',
        'DOSSIER',
        'PROCEDURE',
        'CODE_EVENEMENT',
        'LIBELLE_EVENEMENT',
        'DATE_EVENEMENT',
        'DATE_CREATION_EVENEMENT',
        'DATE_MAJ_EVENEMENT',
        'PRE_CTX_CTX',
        'PRELEVEMENT',
        'INTITULE',
        'DETTE_INTITULE',
    ];

    public function __construct(private DatabaseConnectionResolver $connectionResolver)
    {
    }

    private function getConnection(): Connection
    {
        return $this->connectionResolver->getConnection();
    }

    /**
     * @return list<string>
     */
    public function getAgences(): array
    {
        $sql = <<<SQL
SELECT DISTINCT agence
FROM syn_pat
WHERE agence IS NOT NULL
ORDER BY agence
SQL;

        return $this->getConnection()->executeQuery($sql)->fetchFirstColumn();
    }

    public function generateContentieuxCsv(\DateTimeInterface $dateDu, \DateTimeInterface $dateAu, string $agence): string
    {
        $rows = $this->fetchContentieux($dateDu, $dateAu, $agence);

        return $this->rowsToCsv($rows, self::CONTENTIEUX_COLUMNS);
    }

    public function generatePreEncCsv(\DateTimeInterface $dateDu, \DateTimeInterface $dateAu, string $agence): string
    {
        $rows = $this->fetchPreEnc($dateDu, $dateAu, $agence);
        if ($rows === []) {
            return $this->rowsToCsv([], ['AGENCE', 'GROUPE']);
        }

        $columns = array_keys($rows[0]);

        return $this->rowsToCsv($rows, $columns);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchContentieux(\DateTimeInterface $dateDu, \DateTimeInterface $dateAu, string $agence): array
    {
        $sql = <<<SQL
SELECT DISTINCT
    d.agence AS AGENCE,
    a.bieva_uticre AS UTILISATEUR,
    d.groupe AS GROUPE,
    d.paesi_codext AS ESI,
    a.bidos_num AS DOSSIER,
    b.bipcd_num AS PROCEDURE,
    a.bitev_cod AS CODE_EVENEMENT,
    a.bieva_obj AS LIBELLE_EVENEMENT,
    a.bieva_dat AS DATE_EVENEMENT,
    a.bieva_datcre AS DATE_CREATION_EVENEMENT,
    a.BIEVA_DATMAJ AS DATE_MAJ_EVENEMENT,
    IS_CTX_PRECTX(c.totie_cod) AS PRE_CTX_CTX,
    DECODE(GET_PRELEVE(f.caint_num), 0, 'NON', 'OUI') AS PRELEVEMENT,
    f.caint_num AS INTITULE,
    GET_DETTE_DATE(f.caint_num, :dateAuDette) AS DETTE_INTITULE
FROM bieva a, bipdo b, bidos c, syn_pat d, caicl f, cactr g
WHERE TRUNC(bieva_dat) >= TO_DATE(:dateDu, 'DD/MM/YYYY')
  AND TRUNC(bieva_dat) <= TO_DATE(:dateAu, 'DD/MM/YYYY')
  AND b.bidos_num = a.bidos_num
  AND c.bidos_num = a.bidos_num
  AND d.PAESI_NUM = GETESITIERS(c.totie_cod)
  AND b.bipcd_num IN (2, 7, 17, 20, 21)
  AND BITEV_COD NOT IN ('REL1', 'REL2')
  AND (TRUNC(BIEVA_DATCRE) >= TO_DATE(:dateDuCre, 'DD/MM/YYYY') OR TRUNC(BIEVA_DATMAJ) >= TO_DATE(:dateDuMaj, 'DD/MM/YYYY'))
  AND bieva_uticre <> 'PLAN_AUTO'
  AND f.totie_cod = c.totie_cod
  AND f.CAICL_TEMP = 'T'
  AND f.CAICL_DTF IS NULL
  AND g.CAINT_NUM = f.caint_num
  AND BITEV_COD IN (
      '1°SUIVPL', 'CCAPEX', 'COMIMP', 'CT', 'ECHEC PL', 'IDENTITE', 'MED PLAN',
      'MEDAGENC', 'MOBILIS°', 'OBSERV', 'PLAN', 'SAICAP', 'SIGNCDT', 'SUIVPLAN',
      'TRANSMI', 'TRINO'
  )
  AND b.bipdo_num = a.bipdo_num
  AND d.agence LIKE :agencePattern
ORDER BY AGENCE, ESI, a.bieva_dat
SQL;

        return $this->getConnection()->executeQuery($sql, [
            'dateDu' => $dateDu->format('d/m/Y'),
            'dateAu' => $dateAu->format('d/m/Y'),
            'dateDuCre' => $dateDu->format('d/m/Y'),
            'dateDuMaj' => $dateDu->format('d/m/Y'),
            'dateAuDette' => $dateAu->format('d/m/Y'),
            'agencePattern' => $this->buildAgenceLikePattern($agence),
        ], [
            'dateDu' => \PDO::PARAM_STR,
            'dateAu' => \PDO::PARAM_STR,
            'dateDuCre' => \PDO::PARAM_STR,
            'dateDuMaj' => \PDO::PARAM_STR,
            'dateAuDette' => \PDO::PARAM_STR,
            'agencePattern' => \PDO::PARAM_STR,
        ])->fetchAllAssociative();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchPreEnc(\DateTimeInterface $dateDu, \DateTimeInterface $dateAu, string $agence): array
    {
        $sql = <<<SQL
SELECT b.agence AS AGENCE, b.groupe AS GROUPE, a.*
FROM PREENC_SAISIE a, syn_pat b
WHERE TRUNC(date_preenc) >= TO_DATE(:dateDu, 'DD/MM/YYYY')
  AND TRUNC(date_preenc) <= TO_DATE(:dateAu, 'DD/MM/YYYY')
  AND b.PAESI_CODEXT = F_ESI_INT(intitule, SYSDATE, 'PRI')
SQL;

        $params = [
            'dateDu' => $dateDu->format('d/m/Y'),
            'dateAu' => $dateAu->format('d/m/Y'),
        ];

        if ($agence !== '*') {
            $sql .= ' AND agence = :agence';
            $params['agence'] = $agence;
        }

        $sql .= ' ORDER BY date_preenc DESC';

        return $this->getConnection()->executeQuery($sql, $params)->fetchAllAssociative();
    }

    private function buildAgenceLikePattern(string $agence): string
    {
        $agence = trim($agence);
        if ($agence === '' || $agence === '*') {
            return '%';
        }

        return '%' . str_replace('*', '%', $agence) . '%';
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<string> $columns
     */
    private function rowsToCsv(array $rows, array $columns): string
    {
        $output = fopen('php://temp', 'r+');
        fputcsv($output, $columns, ';');

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
