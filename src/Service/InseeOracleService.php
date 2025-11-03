<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class InseeOracleService
{
    private Connection $connection;

    public function __construct(Connection $defaultConnection)
    {
        $this->connection = $defaultConnection;
    }

    public function generateCsv(int $annee): string
    {
        // Dernier jour du mois précédent (au format YYYY-MM-DD)
        $dateFinMois = $this->connection->executeQuery(
            "SELECT TO_CHAR(LAST_DAY(ADD_MONTHS(SYSDATE, -1)), 'YYYY-MM-DD') FROM DUAL"
        )->fetchOne();

        $sql = <<<SQL
SELECT
  GET_RPLS(b.paesi_num) L_IDENT_REP,
  b.paesi_codext L_IDENT_INT,
  DECODE(SUBSTR(g.paesi_codext,1,2),'IS',93039,'VT',93079,'SD',93066,'PI',93059,'LC',93027,'EP',93031,'AU',93001) L_DEPCOM,
  GET_ADRESSE_LGT(b.paesi_num) L_ADRESSE,
  DECODE(f.paetg_cod,'RDC','00',f.paetg_cod) L_ETAGE,
  NVL(f.paifg_ind,'C') L_TYPECONST,
  SUBSTR(f.paety_cod,2,1) L_NBPIECE,
  opulise.fgetsurface(b.paesi_num,'SH') L_SURFACEHAB,
  NVL(opulise.fgetsurface(b.paesi_num,'SCOR'), opulise.fgetsurface(b.paesi_num,'SU')) L_SURFACECOR,
  TO_CHAR(b.paesi_datent,'YYYY') L_CONSTRUCT,
  1 L_WC, 1 L_SANIT, 1 L_CHAUF,
  DECODE(f.paifg_temasc,'T',1,2) L_ASC,
  NVL(GET_LOYER_FAC(a.glcon_num,a.glcon_numver, TO_DATE(:dfm, 'YYYY-MM-DD')),0) L_LOYER,
  NVL(GET_RLS(a.glcon_num,a.glcon_numver), NVL(GET_SLS(a.glcon_num,a.glcon_numver),0)) L_SURLOYER,
  NVL(GET_CHARGES_FAC(a.glcon_num,a.glcon_numver, TO_DATE(:dfm, 'YYYY-MM-DD')),0) L_CHARGES,
  NVL(-1*GET_APL(a.glcon_num,a.glcon_numver),0) L_AIDES,
  DECODE(a.glcon_num, NULL,'999999', TO_CHAR(LAST_DAY(ADD_MONTHS(SYSDATE,-1)),'YYYYMM')) L_DATELOYER,
  DECODE(a.glcon_num, NULL,'9', DECODE(f.paifg_fact,'M',1,9)) L_PERIODICITE,
  DECODE(a.glcon_num, NULL,'99999999', TO_CHAR(a.glelc_dtd,'YYYYMMDD')) L_DATEENTREE,
  (CASE WHEN a.glcon_num IS NULL THEN 1 ELSE 2 END) L_VACANT,
  DECODE(a.glcon_num, NULL, GET_INSEE_SORTIE(b.paesi_num), '99999999') L_DATESORTIE
FROM opulise.glelc a, opulise.paesi b, opulise.glrfc c, opulise.glrec d, opulise.mgadr e, opulise.paifg f, opulise.paesi g, opulise.glcon h, opulise.parel i
WHERE a.paesi_num(+) = b.paesi_num
  AND a.glelc_dtf(+) IS NULL
  AND b.paesi_num = i.paesi_num
  AND i.totie_cod = 1
  AND i.totre_cod = 'GEST'
  AND b.panes_cod IN ('APPT','PAV')
  AND b.panes_ind = 'L'
  AND b.paesi_num = f.paesi_num
  AND b.paesi_rng1 = g.paesi_rng1
  AND g.panes_cod = 'GPE'
  AND b.paesi_datsor IS NULL
  AND a.glcon_num = c.glcon_num(+)
  AND a.glcon_numver = c.glcon_numver(+)
  AND c.glrec_num = d.glrec_num(+)
  AND d.mgadr_num = e.mgadr_num(+)
  AND a.glcon_num = h.glcon_num(+)
  AND a.glcon_numver = h.glcon_numver(+)
  AND h.glnac_cod(+) != 'GARD'
  AND h.gltoc_cod(+) = 'TBAIL'
  AND (a.glcon_num IS NULL OR (a.glcon_num, a.glcon_numver) NOT IN (SELECT glcon_num, glcon_numver FROM opulise.glcon WHERE glcon_temca = 'T'))
  AND b.paesi_codext IN (SELECT ESI FROM INSEE_ESI WHERE ANNEE = :annee)
UNION
SELECT
  GET_RPLS(b.paesi_num) L_IDENT_REP,
  b.paesi_codext L_IDENT_INT,
  DECODE(SUBSTR(g.paesi_codext,1,2),'IS',93039,'VT',93079,'SD',93066,'PI',93059,'LC',93027,'EP',93031,'AU',93001) L_DEPCOM,
  GET_ADRESSE_LGT(b.paesi_num) L_ADRESSE,
  DECODE(f.paetg_cod,'RDC','00',f.paetg_cod) L_ETAGE,
  NVL(f.paifg_ind,'C') L_TYPECONST,
  SUBSTR(f.paety_cod,2,1) L_NBPIECE,
  opulise.fgetsurface(b.paesi_num,'SH') L_SURFACEHAB,
  opulise.fgetsurface(b.paesi_num,'SCOR') L_SURFACECOR,
  TO_CHAR(b.paesi_datent,'YYYY') L_CONSTRUCT,
  1 L_WC, 1 L_SANIT, 1 L_CHAUF,
  DECODE(f.paifg_temasc,'T',1,2) L_ASC,
  NVL(GET_LOYER_FAC(a.glcon_num,a.glcon_numver, TO_DATE(:dfm, 'YYYY-MM-DD')),0) L_LOYER,
  NVL(GET_RLS(a.glcon_num,a.glcon_numver), NVL(GET_SLS(a.glcon_num,a.glcon_numver),0)) L_SURLOYER,
  NVL(GET_CHARGES_FAC(a.glcon_num,a.glcon_numver, TO_DATE(:dfm, 'YYYY-MM-DD')),0) L_CHARGES,
  NVL(-1*GET_APL(a.glcon_num,a.glcon_numver),0) L_AIDES,
  DECODE(a.glcon_num, NULL,'999999', TO_CHAR(LAST_DAY(ADD_MONTHS(SYSDATE,-1)),'YYYYMM')) L_DATELOYER,
  DECODE(a.glcon_num, NULL,'9', DECODE(f.paifg_fact,'M',1,9)) L_PERIODICITE,
  '99999999' L_DATEENTREE,
  (CASE WHEN a.glcon_num IS NULL THEN 1 ELSE 2 END) L_VACANT,
  GET_INSEE_SORTIE(b.paesi_num) L_DATESORTIE
FROM opulise.glelc a, opulise.paesi b, opulise.glrfc c, opulise.glrec d, opulise.mgadr e, opulise.paifg f, opulise.paesi g, opulise.glcon h, opulise.parel i
WHERE a.paesi_num(+) = b.paesi_num
  AND a.glelc_dtf >= LAST_DAY(ADD_MONTHS(SYSDATE,-3))
  AND b.paesi_num = i.paesi_num
  AND i.totie_cod = 1
  AND i.totre_cod = 'GEST'
  AND b.panes_cod IN ('APPT','PAV')
  AND b.panes_ind = 'L'
  AND b.paesi_num = f.paesi_num
  AND b.paesi_rng1 = g.paesi_rng1
  AND g.panes_cod = 'GPE'
  AND b.paesi_datsor IS NULL
  AND a.glcon_num = c.glcon_num(+)
  AND a.glcon_numver = c.glcon_numver(+)
  AND c.glrec_num = d.glrec_num(+)
  AND d.mgadr_num = e.mgadr_num(+)
  AND a.glcon_num = h.glcon_num(+)
  AND a.glcon_numver = h.glcon_numver(+)
  AND h.glnac_cod(+) != 'GARD'
  AND h.gltoc_cod(+) = 'TBAIL'
  AND (a.glcon_num IS NULL OR (a.glcon_num, a.glcon_numver) NOT IN (SELECT glcon_num, glcon_numver FROM opulise.glcon WHERE glcon_temca = 'T'))
  AND b.paesi_codext IN (SELECT ESI FROM INSEE_ESI WHERE ANNEE = :annee)
ORDER BY 2
SQL;

        $rows = $this->connection->executeQuery($sql, [
            'dfm' => $dateFinMois,
            'annee' => $annee,
        ])->fetchAllAssociative();

        return $this->toCsv($rows);
    }

    private function toCsv(array $rows): string
    {
        $output = fopen('php://temp', 'r+');
        // En-têtes (selon alias de la requête)
        fputcsv($output, [
            'L_IDENT_REP','L_IDENT_INT','L_DEPCOM','L_ADRESSE','L_ETAGE','L_TYPECONST','L_NBPIECE',
            'L_SURFACEHAB','L_SURFACECOR','L_CONSTRUCT','L_WC','L_SANIT','L_CHAUF','L_ASC',
            'L_LOYER','L_SURLOYER','L_CHARGES','L_AIDES','L_DATELOYER','L_PERIODICITE','L_DATEENTREE','L_VACANT','L_DATESORTIE'
        ], ';');

        foreach ($rows as $row) {
            $line = [
                $row['L_IDENT_REP'] ?? '',
                $row['L_IDENT_INT'] ?? '',
                $row['L_DEPCOM'] ?? '',
                $row['L_ADRESSE'] ?? '',
                $row['L_ETAGE'] ?? '',
                $row['L_TYPECONST'] ?? '',
                $row['L_NBPIECE'] ?? '',
                $row['L_SURFACEHAB'] ?? '',
                $row['L_SURFACECOR'] ?? '',
                $row['L_CONSTRUCT'] ?? '',
                $row['L_WC'] ?? '',
                $row['L_SANIT'] ?? '',
                $row['L_CHAUF'] ?? '',
                $row['L_ASC'] ?? '',
                $row['L_LOYER'] ?? '',
                $row['L_SURLOYER'] ?? '',
                $row['L_CHARGES'] ?? '',
                $row['L_AIDES'] ?? '',
                $row['L_DATELOYER'] ?? '',
                $row['L_PERIODICITE'] ?? '',
                $row['L_DATEENTREE'] ?? '',
                $row['L_VACANT'] ?? '',
                $row['L_DATESORTIE'] ?? '',
            ];
            fputcsv($output, $line, ';');
        }

        rewind($output);
        return stream_get_contents($output) ?: '';
    }
}


