<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class ExtractionListingOracleService
{
    private const GROUP_SEARCH_COLUMNS = [
        'PAESI_CODEXT',
        'PANES_COD',
        'PAESI_LIB',
        'ADRESSE',
    ];

    private const LISTE_LOC_BASE_COLUMNS = [
        'ESI',
        'ADRESSE',
        'LIBELLE_INTITULE',
        'NB_OCCUPANTS',
        'LISTE_OCCUPANTS',
        'ETAGE',
        'TEL_LOCATAIRE',
        'TEL_PORTABLE',
        'NO_TIERS',
    ];

    private const LISTE_LOC_LOGEMENT_COLUMNS = [
        'NODEMANDELOGEMENTENCOURS',
        'TYPOLOGIE',
        'TYPO_DEMANDE',
    ];

    private const LISTE_LOC_SIMPLE_COLUMNS = [
        'TYPOLOGIE',
    ];

    private const RELOGES_COLUMNS = [
        'ACDOS_NUM',
        'ACPRO_NUM',
        'DATE_SATISFACTION',
        'CONTINGENT_RELOGEMENT',
        'RESERVATAIRE_BASE',
        'ACDEM_TOPNU',
        'TOTIE_COD',
        'NOM',
        'EMPLOYEUR',
        'ESI',
        'RPLS',
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
    public function searchGroupByStreet(string $nomRue): array
    {
        $nomRue = trim($nomRue);
        if ($nomRue === '') {
            return [];
        }

        $sql = <<<SQL
SELECT
    a.PAESI_CODEXT,
    a.PANES_COD,
    a.PAESI_LIB,
    FGETADRESI(a.paesi_num) AS ADRESSE
FROM paesi a
WHERE a.paesi_num IN (
    SELECT paesi_num
    FROM paead
    WHERE mgadr_num IN (
        SELECT mgadr_num
        FROM mgadr
        WHERE UPPER(MGADR_NOMVOI) LIKE :pattern
    )
)
AND a.panes_cod = 'ESC'
SQL;

        return $this->getConnection()->executeQuery($sql, [
            'pattern' => '%' . mb_strtoupper($nomRue) . '%',
        ])->fetchAllAssociative();
    }

    public function generateListeLocataireCsv(string $groupe, bool $inclureLogement): string
    {
        $groupe = trim($groupe);
        if ($groupe === '') {
            return $this->rowsToCsv([], $this->getListeLocataireColumns($inclureLogement));
        }

        $columns = $this->getListeLocataireColumns($inclureLogement);
        $rows = $this->fetchListeLocataire($groupe, $inclureLogement);

        return $this->rowsToCsv($rows, $columns);
    }

    public function generateRelogesCsv(int $anneeMin = 2015): string
    {
        $sql = <<<SQL
SELECT
    a.acdos_num AS ACDOS_NUM,
    a.acpro_num AS ACPRO_NUM,
    acdec_dat AS DATE_SATISFACTION,
    acmti_cod AS CONTINGENT_RELOGEMENT,
    ACCAD_COD AS RESERVATAIRE_BASE,
    acdem_topnu AS ACDEM_TOPNU,
    d.totie_cod AS TOTIE_COD,
    FGETLIBTIERS(d.totie_cod) AS NOM,
    F_GetEmployeur(d.totie_cod) AS EMPLOYEUR,
    (SELECT paesi_codext FROM syn_pat WHERE paesi_num = e.paesi_num) AS ESI,
    GET_RPLS(e.paesi_num) AS RPLS
FROM acdec a, acdos b, acdem c, acpar d, acpro e
WHERE acave_cod = 'RELOG'
  AND acdec_dat >= TO_DATE(:dateMin, 'DD/MM/YYYY')
  AND b.acdos_num = a.acdos_num
  AND c.acdos_num = a.acdos_num
  AND d.acdos_num = a.acdos_num
  AND ACTPA_COD = 'CAND'
  AND c.acdem_eta = 'I'
  AND e.acdos_numdir = a.acdos_num
  AND e.acpro_num = a.acpro_num
SQL;

        $rows = $this->getConnection()->executeQuery($sql, [
            'dateMin' => sprintf('01/01/%d', $anneeMin),
        ])->fetchAllAssociative();

        return $this->rowsToCsv($rows, self::RELOGES_COLUMNS);
    }

    public function generateGroupSearchCsv(string $nomRue): string
    {
        return $this->rowsToCsv($this->searchGroupByStreet($nomRue), self::GROUP_SEARCH_COLUMNS);
    }

    /**
     * @return list<string>
     */
    private function getListeLocataireColumns(bool $inclureLogement): array
    {
        return array_merge(
            self::LISTE_LOC_BASE_COLUMNS,
            $inclureLogement ? self::LISTE_LOC_LOGEMENT_COLUMNS : self::LISTE_LOC_SIMPLE_COLUMNS
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchListeLocataire(string $groupe, bool $inclureLogement): array
    {
        if ($inclureLogement) {
            $sql = <<<SQL
SELECT
    paesi_codext AS ESI,
    FGETADRESI(a.paesi_num) AS ADRESSE,
    (SELECT GLREC_TITRE || ' ' || GLREC_INTCOU1
     FROM glrec
     WHERE caint_num = (GET_INTITULE(GET_CONTRAT_DT(a.paesi_num, SYSDATE), 0))) AS LIBELLE_INTITULE,
    GET_NBOCC(F_CONTRAT_ESI(a.paesi_num, SYSDATE), 0) AS NB_OCCUPANTS,
    F_Tous_Occupants(a.paesi_num, SYSDATE) AS LISTE_OCCUPANTS,
    (SELECT PAETG_COD FROM paifg x WHERE x.paesi_num = a.paesi_num AND PAIFG_DTF IS NULL) AS ETAGE,
    GET_TEL(NVL(
        get_signataire((SELECT GET_CONTRAT_DT(a.paesi_num, SYSDATE) FROM DUAL)),
        (SELECT TOTIE_COD FROM glocc
         WHERE glcon_num = (SELECT GET_CONTRAT_DT(a.paesi_num, SYSDATE) FROM DUAL)
           AND ROWNUM = 1)
    )) AS TEL_LOCATAIRE,
    GET_PORTABLE(NVL(
        get_signataire((SELECT GET_CONTRAT_DT(a.paesi_num, SYSDATE) FROM DUAL)),
        (SELECT TOTIE_COD FROM glocc
         WHERE glcon_num = (SELECT GET_CONTRAT_DT(a.paesi_num, SYSDATE) FROM DUAL)
           AND ROWNUM = 1)
    )) AS TEL_PORTABLE,
    get_signataire(GET_CONTRAT_DT(a.paesi_num, SYSDATE)) AS NO_TIERS,
    (SELECT LISTAGG(x.acdos_num, ';') WITHIN GROUP (ORDER BY x.acdos_num)
     FROM acpar x, acdem y
     WHERE y.acdos_num = x.acdos_num
       AND ACDEM_ETA IN ('E', 'A')
       AND TOTIE_COD = get_signataire(GET_CONTRAT_DT(a.paesi_num, SYSDATE))) AS NODEMANDELOGEMENTENCOURS,
    PAETY_COD AS TYPOLOGIE,
    (SELECT LISTAGG(PAETY_COD, '; ') WITHIN GROUP (ORDER BY paety_cod)
     FROM actld
     WHERE acdos_num = (
         SELECT MAX(x.acdos_num)
         FROM acpar x, acdem y
         WHERE y.acdos_num = x.acdos_num
           AND ACDEM_ETA IN ('E', 'A')
           AND TOTIE_COD = get_signataire(GET_CONTRAT_DT(a.paesi_num, SYSDATE))
     )) AS TYPO_DEMANDE
FROM paesi a, paifg b
WHERE paesi_codext LIKE :groupePattern
  AND panes_cod = 'APPT'
  AND PAESI_DATSOR IS NULL
  AND b.paesi_num = a.paesi_num
SQL;
        } else {
            $sql = <<<SQL
SELECT
    paesi_codext AS ESI,
    FGETADRESI(a.paesi_num) AS ADRESSE,
    (SELECT GLREC_TITRE || ' ' || GLREC_INTCOU1
     FROM glrec
     WHERE caint_num = (GET_INTITULE(GET_CONTRAT_DT(a.paesi_num, SYSDATE), 0))) AS LIBELLE_INTITULE,
    GET_NBOCC(F_CONTRAT_ESI(a.paesi_num, SYSDATE), 0) AS NB_OCCUPANTS,
    F_Tous_Occupants(a.paesi_num, SYSDATE) AS LISTE_OCCUPANTS,
    (SELECT PAETG_COD FROM paifg x WHERE x.paesi_num = a.paesi_num AND PAIFG_DTF IS NULL) AS ETAGE,
    GET_TEL(NVL(
        get_signataire((SELECT GET_CONTRAT_DT(a.paesi_num, SYSDATE) FROM DUAL)),
        (SELECT TOTIE_COD FROM glocc
         WHERE glcon_num = (SELECT GET_CONTRAT_DT(a.paesi_num, SYSDATE) FROM DUAL)
           AND ROWNUM = 1)
    )) AS TEL_LOCATAIRE,
    GET_PORTABLE(NVL(
        get_signataire((SELECT GET_CONTRAT_DT(a.paesi_num, SYSDATE) FROM DUAL)),
        (SELECT TOTIE_COD FROM glocc
         WHERE glcon_num = (SELECT GET_CONTRAT_DT(a.paesi_num, SYSDATE) FROM DUAL)
           AND ROWNUM = 1)
    )) AS TEL_PORTABLE,
    get_signataire(GET_CONTRAT_DT(a.paesi_num, SYSDATE)) AS NO_TIERS,
    PAETY_COD AS TYPOLOGIE
FROM paesi a, paifg b
WHERE paesi_codext LIKE :groupePattern
  AND panes_cod IN ('APPT', 'PAV')
  AND PAESI_DATSOR IS NULL
  AND b.paesi_num = a.paesi_num
SQL;
        }

        return $this->getConnection()->executeQuery($sql, [
            'groupePattern' => $groupe . '%',
        ])->fetchAllAssociative();
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
