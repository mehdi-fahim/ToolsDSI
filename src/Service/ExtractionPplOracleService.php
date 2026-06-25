<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class ExtractionPplOracleService
{
    private const QUOTE_PARTS_COLUMNS = [
        'GLCON_NUM',
        'INTITULE',
        'ESI',
        'GLGPR_COD',
        'GLPER_COD',
        'GLRUB_COD',
        'MGNAD_COD',
        'GLQTP_IMPUT',
        'GLQTP_MNTBAS',
        'GLQTP_NBRJOUR',
        'GLQTP_MNTQTP',
        'GLQTP_SOLDE',
        'GLQTP_REPAR',
    ];

    private const LISTE_OD_COLUMNS = [
        'NO_OD',
        'LIBELLE',
        'RUBRIQUE',
        'FAMILLE',
        'ESI',
        'GROUPE',
        'CONTRAT',
        'INTITULE',
        'MONTANT',
        'TITRE_OCCUPATION',
        'NATURE_CONTRAT',
    ];

    private const TOPNU_ALLOC_COLUMNS = [
        'NUM_ALLOC',
        'STATUS',
        'REF_CONT',
        'DATE_DEBUT',
        'DATE_FIN',
        'REF_INT_COMPTE',
    ];

    private const TOPNU_DETAIL_COLUMNS = [
        'NUMERO',
        'ETAT',
        'TYPE_NUMERO',
        'CONTRAT',
        'VERSION',
        'CONTRAT_DEBUT',
        'CONTRAT_FIN',
        'INTITULE',
        'TITRE_INTITULE',
        'NOM_INTITULE',
        'ESI',
        'ADR_ESI',
        'TIERS_PRINIPAL',
        'NOM_TIERS_PRINCIPAL',
        'AGENCE',
    ];

    private const FACT_EAU_COLUMNS = [
        'CONTRAT',
        'GLCON_NUMVER',
        'ESI',
        'RUBRIQUE',
        'MONTANT',
        'INTITULE',
        'LIBELLE_INTITULE',
        'ECTIN_COD',
        'PAINS_COD',
        'ECCON_VAL',
        'ECCON_DATREL',
    ];

    private const CLES_REPARTITION_COLUMNS = [
        'TACLE_COD',
        'TACLE_LIB',
    ];

    public function __construct(private DatabaseConnectionResolver $connectionResolver)
    {
    }

    private function getConnection(): Connection
    {
        return $this->connectionResolver->getConnection();
    }

    public function generateQuotePartsCsv(string $periode): string
    {
        $sql = <<<SQL
SELECT
    a.GLCON_NUM,
    c.caint_num AS INTITULE,
    b.paesi_codext AS ESI,
    a.GLGPR_COD,
    a.GLPER_COD,
    a.GLRUB_COD,
    a.MGNAD_COD,
    a.GLQTP_IMPUT,
    a.GLQTP_MNTBAS,
    a.GLQTP_NBRJOUR,
    a.GLQTP_MNTQTP,
    a.GLQTP_SOLDE,
    a.GLQTP_REPAR
FROM syn_pat b,
     OPULISE.GLQTP a
     LEFT JOIN cactr c ON c.cactr_num = a.glcon_num AND c.cactr_vrs = a.glcon_numver
WHERE a.GLPER_COD = :periode
  AND b.paesi_num = a.paesi_num
ORDER BY b.paesi_codext
SQL;

        return $this->rowsToCsv(
            $this->getConnection()->executeQuery($sql, ['periode' => $periode])->fetchAllAssociative(),
            self::QUOTE_PARTS_COLUMNS
        );
    }

    public function generateListeOdCsv(\DateTimeInterface $dateMin): string
    {
        $sql = <<<SQL
SELECT
    a.GLOPD_NUM AS NO_OD,
    a.GLOPD_LIB AS LIBELLE,
    a.GLRUB_COD AS RUBRIQUE,
    a.GLFOD_COD AS FAMILLE,
    b.paesi_codext AS ESI,
    b.groupe AS GROUPE,
    a.glcon_num AS CONTRAT,
    GET_INTITULE(a.glcon_num, a.glcon_numver) AS INTITULE,
    c.GLDOD_MNT AS MONTANT,
    GET_TITRE_OCC(a.glcon_num) AS TITRE_OCCUPATION,
    (SELECT glnac_cod FROM glcon WHERE glcon_num = a.glcon_num AND glcon_numver = a.glcon_numver) AS NATURE_CONTRAT
FROM glopd a, syn_pat b, gldod c
WHERE a.GLOPD_DTDECH >= TO_DATE(:dateMin, 'DD/MM/YYYY')
  AND b.paesi_num = a.paesi_num
  AND c.glopd_num = a.glopd_num
SQL;

        return $this->rowsToCsv(
            $this->getConnection()->executeQuery($sql, [
                'dateMin' => $dateMin->format('d/m/Y'),
            ])->fetchAllAssociative(),
            self::LISTE_OD_COLUMNS
        );
    }

    public function generateTopnuAllocCsv(): string
    {
        $sql = <<<SQL
SELECT
    t2.TOPNU_COD AS NUM_ALLOC,
    DECODE(t2.TOPNU_ETA, 'A', 'Actif', 'I', 'Inactif') AS STATUS,
    g.GLCON_NUM AS REF_CONT,
    g.GLCON_DTD AS DATE_DEBUT,
    g.GLCON_DTF AS DATE_FIN,
    c2.CAINT_NUM AS REF_INT_COMPTE
FROM caicl c, TOPNU t2, cactr c2, glcon g
WHERE c.TOTIE_COD = t2.TOTIE_COD_AFF
  AND c.CAINT_NUM = c2.CAINT_NUM
  AND c2.cactr_num = g.glcon_num
SQL;

        return $this->streamQueryToCsv($sql, [], self::TOPNU_ALLOC_COLUMNS);
    }

    public function generateTopnuDetailCsv(): string
    {
        $sql = <<<SQL
SELECT
    t2.TOPNU_COD AS NUMERO,
    t2.TOPNU_ETA AS ETAT,
    t2.TOTAF_COD AS TYPE_NUMERO,
    g.GLCON_NUM AS CONTRAT,
    g.glcon_numver AS VERSION,
    g.GLCON_DTD AS CONTRAT_DEBUT,
    g.GLCON_DTF AS CONTRAT_FIN,
    c2.CAINT_NUM AS INTITULE,
    g2.glrec_titre AS TITRE_INTITULE,
    g2.GLREC_INTCOU1 AS NOM_INTITULE,
    FGETINFOESI(g.glcon_num, g.GLCON_NUMVER, 'E') AS ESI,
    FGETADRESI(FGETINFOESI(g.glcon_num, g.GLCON_NUMVER, 'P')) AS ADR_ESI,
    g3.totie_cod AS TIERS_PRINIPAL,
    FGETLIBTIERS(g3.totie_cod) AS NOM_TIERS_PRINCIPAL,
    GET_NOM_AGENCE(FGETINFOESI(g.glcon_num, g.GLCON_NUMVER, 'E')) AS AGENCE
FROM caicl c, TOPNU t2, cactr c2, glcon g, glrec g2, glcsi g3
WHERE c.TOTIE_COD = t2.TOTIE_COD_AFF
  AND c.CAINT_NUM = c2.CAINT_NUM
  AND c2.cactr_num = g.glcon_num
  AND c2.cactr_vrs = g.GLCON_NUMVER
  AND g2.caint_num = c.caint_num
  AND g3.GLCON_NUM = g.GLCON_NUM
  AND g3.GLCON_NUMVER = g.GLCON_NUMVER
  AND c.CAICL_TEMP = 'T'
  AND g3.GLCSI_TEMCONPRIN = 'T'
SQL;

        return $this->streamQueryToCsv($sql, [], self::TOPNU_DETAIL_COLUMNS);
    }

    public function generateFactEauMensuelleCsv(\DateTimeInterface $moisMin): string
    {
        $sql = <<<SQL
SELECT
    b.contrat AS CONTRAT,
    b.glcon_numver AS GLCON_NUMVER,
    b.esi AS ESI,
    CASE a.ECTIN_COD
        WHEN 'F' THEN 'C115'
        WHEN 'C' THEN 'C116'
    END AS RUBRIQUE,
    CASE a.ECTIN_COD
        WHEN 'F' THEN a.ECCON_VAL * 4.36
        WHEN 'C' THEN a.ECCON_VAL * 10.6
    END AS MONTANT,
    b.intitule AS INTITULE,
    b.LIBELLE_INTITULE,
    a.ECTIN_COD,
    a.PAINS_COD,
    a.ECCON_VAL,
    a.ECCON_DATREL
FROM eccon a, final_integ_cdc b
WHERE a.pains_cod IN (
    SELECT pains_cod
    FROM paidl
    WHERE paesi_num IN (
        SELECT paesi_num
        FROM syn_pat
        WHERE groupe IN ('ST4000', 'ST4100', 'ST4200')
    )
)
  AND a.ECCON_DATREL >= TO_DATE(:moisMin, 'DD/MM/YYYY')
  AND b.contrat = a.glcon_num
ORDER BY b.esi
SQL;

        return $this->rowsToCsv(
            $this->getConnection()->executeQuery($sql, [
                'moisMin' => $moisMin->format('d/m/Y'),
            ])->fetchAllAssociative(),
            self::FACT_EAU_COLUMNS
        );
    }

    public function generateClesRepartitionCsv(): string
    {
        $sql = <<<SQL
SELECT tacle_cod AS TACLE_COD, tacle_lib AS TACLE_LIB
FROM tacle
WHERE tafur_cod = 'EDF'
ORDER BY tacle_cod
SQL;

        return $this->rowsToCsv(
            $this->getConnection()->executeQuery($sql)->fetchAllAssociative(),
            self::CLES_REPARTITION_COLUMNS
        );
    }

    /**
     * @param array<string, mixed> $params
     * @param list<string> $columns
     */
    private function streamQueryToCsv(string $sql, array $params, array $columns): string
    {
        $result = $this->getConnection()->executeQuery($sql, $params);
        $output = fopen('php://temp', 'r+');
        fputcsv($output, $columns, ';');

        while ($row = $result->fetchAssociative()) {
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
