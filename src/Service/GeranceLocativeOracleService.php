<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class GeranceLocativeOracleService
{
    public function __construct(private DatabaseConnectionResolver $connectionResolver)
    {
    }

    private function getConnection(): Connection
    {
        return $this->connectionResolver->getConnection();
    }

    /**
     * Retourne les rubriques du contrat avec information de validité (GLRUC_TEMVAL).
     * Équivalent VB :
     *   SELECT GLRUB_COD, GLRUC_TEMVAL
     *   FROM GLRUC a
     *   WHERE GLCON_NUM = :contrat
     *     AND GLRUC_DTF IS NULL
     *     AND GLCON_NUMVER = (SELECT MAX(GLCON_NUMVER) FROM GLCON b WHERE b.GLCON_NUM = a.GLCON_NUM)
     *   ORDER BY 1
     *
     * On renvoie un tableau de ['code' => string, 'selected' => bool].
     */
    public function getRubriquesByContrat(string $contrat): array
    {
        $sql = <<<SQL
SELECT GLRUB_COD, GLRUC_TEMVAL
FROM opulise.GLRUC a
WHERE a.GLCON_NUM = :contrat
  AND a.GLRUC_DTF IS NULL
  AND a.GLCON_NUMVER = (
    SELECT MAX(b.GLCON_NUMVER)
    FROM opulise.GLCON b
    WHERE b.GLCON_NUM = a.GLCON_NUM
  )
ORDER BY GLRUB_COD
SQL;

        $rows = $this->getConnection()->executeQuery($sql, [
            'contrat' => trim($contrat),
        ])->fetchAllAssociative();

        $rubriques = [];
        foreach ($rows as $row) {
            $rubriques[] = [
                'code' => (string) ($row['GLRUB_COD'] ?? ''),
                'selected' => isset($row['GLRUC_TEMVAL']) && strtoupper((string) $row['GLRUC_TEMVAL']) === 'T',
            ];
        }

        return $rubriques;
    }

    /**
     * Recherche les lignes GLRUC pour un contrat + une rubrique donnée
     * (décoche/récoche des rubriques de contrat).
     */
    public function findLignesRubrique(string $contrat, string $rubrique): array
    {
        $sql = <<<SQL
SELECT GLRUC_NUM, GLCON_NUM, PAESI_NUM, GLRUB_COD, GLTAR_COD, GLRUC_MNT, GLRUC_TEMVAL
FROM opulise.GLRUC
WHERE GLCON_NUM = :contrat
  AND GLRUB_COD = :rubrique
ORDER BY GLRUC_NUM
SQL;

        return $this->getConnection()->executeQuery($sql, [
            'contrat' => trim($contrat),
            'rubrique' => trim($rubrique),
        ])->fetchAllAssociative();
    }

    /**
     * Met à jour GLRUC_TEMVAL (T / F) pour une ligne identifiée par GLRUC_NUM.
     */
    public function updateTemvalForLigne(int $glrucNum, bool $checked): void
    {
        $sql = "UPDATE opulise.GLRUC SET GLRUC_TEMVAL = :temval WHERE GLRUC_NUM = :id";
        $this->getConnection()->executeStatement($sql, [
            'temval' => $checked ? 'T' : 'F',
            'id' => $glrucNum,
        ]);
    }

    /**
     * Met à jour CALIG sur l'ancien intitulé : CARUB_COD = 'MIG' où CARUB_COD = 'D019' et CACTR_NUM = ancien contrat.
     */
    public function updateCaligMig(string $ancienContrat): int
    {
        $sql = "UPDATE opulise.CALIG SET CARUB_COD = 'MIG' WHERE CARUB_COD = 'D019' AND CACTR_NUM = :contrat";
        return $this->getConnection()->executeStatement($sql, ['contrat' => trim($ancienContrat)]);
    }

    /**
     * Appelle la procédure opulise.ADD_DG_CPT_DG(nouveau_contrat, version, montant, sysdate).
     */
    public function callAddDgCptDg(string $nouveauContrat, string $version, string $montant): void
    {
        $sql = "BEGIN opulise.ADD_DG_CPT_DG(:p1, :p2, :p3, SYSDATE); END;";
        $this->getConnection()->executeStatement($sql, [
            'p1' => trim($nouveauContrat),
            'p2' => trim($version),
            'p3' => trim($montant),
        ]);
    }

    /**
     * Diagnostic Liquidation (pb eau) pour un contrat :
     * - Date de fin de contrat
     * - Relevés eau (ECREL)
     * - Consommations (ECCON)
     * - Rubriques GLRUC (C009, C011, C115, C116)
     * - Liste des groupes de prix de l'ESI (GLGEL)
     */
    public function getDiagnosticLiquidation(string $contrat): array
    {
        $contrat = trim($contrat);

        // 1. Date de fin de contrat (avec valeur de repli 1901-01-01 pour le calcul d'année)
        $dtfString = $this->getConnection()->executeQuery(
            "SELECT TO_CHAR(NVL(TRUNC(GLCON_DTF), TO_DATE('1901-01-01','YYYY-MM-DD')), 'YYYY-MM-DD') AS DTF
             FROM opulise.GLCON
             WHERE GLCON_NUM = :contrat",
            ['contrat' => $contrat]
        )->fetchOne();

        $finContrat = null;
        if ($dtfString) {
            $finContrat = new \DateTimeImmutable($dtfString);
        }
        $annee = $finContrat ? (int) $finContrat->format('Y') : (int) date('Y');

        $fromDate = sprintf('%04d-01-01', $annee);
        $toDate = sprintf('%04d-12-31', $annee);

        // 2. Relevés eau (ECREL)
        $releves = $this->getConnection()->executeQuery(
            "SELECT PAINS_COD AS COMPTEUR,
                    ECOBU_COD AS OBSERVATION,
                    ECREL_VAL AS INDEX_RLV,
                    TRUNC(ECREL_DATREL) AS DATE_RLV
             FROM opulise.ECREL
             WHERE GLCON_NUM = :contrat
               AND ECREL_DATREL >= TO_DATE(:fromDate, 'YYYY-MM-DD')
               AND PAITY_COD IN ('CPTDF', 'CPTDC')
             ORDER BY PAINS_COD, ECREL_DATREL DESC",
            [
                'contrat' => $contrat,
                'fromDate' => $fromDate,
            ]
        )->fetchAllAssociative();

        // 3. Consommations (ECCON)
        $consommations = $this->getConnection()->executeQuery(
            "SELECT PAINS_COD AS COMPTEUR,
                    ECCON_VAL AS CONSOMMATION,
                    ECCON_DATREL AS DATE_RLV,
                    GLDDC_NUM AS NO_LIQUIDATION
             FROM opulise.ECCON
             WHERE GLCON_NUM = :contrat
               AND PAITY_COD IN ('CPTDF', 'CPTDC')
               AND ECCON_DATREL >= TO_DATE(:fromDate, 'YYYY-MM-DD')
             ORDER BY PAINS_COD, ECCON_DATREL DESC",
            [
                'contrat' => $contrat,
                'fromDate' => $fromDate,
            ]
        )->fetchAllAssociative();

        // 4. Rubriques (GLRUC)
        $rubriques = $this->getConnection()->executeQuery(
            "SELECT GLRUB_COD,
                    GLGPR_COD,
                    GLRUC_DTD,
                    GLRUC_DTF,
                    GLRUC_MNT
             FROM opulise.GLRUC
             WHERE GLCON_NUM = :contrat
               AND (GLRUC_DTF IS NULL
                    OR GLRUC_DTF BETWEEN TO_DATE(:fromDate, 'YYYY-MM-DD') AND TO_DATE(:toDate, 'YYYY-MM-DD'))
               AND GLRUB_COD IN ('C009', 'C011', 'C115', 'C116')",
            [
                'contrat' => $contrat,
                'fromDate' => $fromDate,
                'toDate' => $toDate,
            ]
        )->fetchAllAssociative();

        // 5. Groupes de prix de l'ESI (GLGEL)
        $gpePrix = $this->getConnection()->executeQuery(
            "SELECT
                GLGPR_COD || '|' || TO_CHAR(GLGPR_DTD, 'YYYY-MM-DD') AS VALUE,
                GLGPR_COD || '-' || TO_CHAR(GLGPR_DTD, 'DD/MM/YYYY') AS LABEL
             FROM opulise.GLGEL
             WHERE PAESI_NUM = FGETINFOESI(:contrat, 0, 'P')
               AND GLGEL_DTF IS NULL
             ORDER BY GLGPR_COD",
            ['contrat' => $contrat]
        )->fetchAllAssociative();

        return [
            'finContrat' => $finContrat,
            'annee' => $annee,
            'releves' => $releves,
            'consommations' => $consommations,
            'rubriques' => $rubriques,
            'gpePrix' => $gpePrix,
        ];
    }

    /**
     * Applique un groupe de prix (code + date) aux rubriques GLRUC du contrat
     * pour l'année de fin de contrat (même logique que le code VB).
     */
    public function appliquerGroupePrix(string $contrat, string $groupePrixCode, string $groupePrixDateIso): void
    {
        $contrat = trim($contrat);

        // On recalcule l'année de référence à partir de la date de fin de contrat
        $dtfString = $this->getConnection()->executeQuery(
            "SELECT TO_CHAR(NVL(TRUNC(GLCON_DTF), TO_DATE('1901-01-01','YYYY-MM-DD')), 'YYYY-MM-DD') AS DTF
             FROM opulise.GLCON
             WHERE GLCON_NUM = :contrat",
            ['contrat' => $contrat]
        )->fetchOne();

        $finContrat = $dtfString ? new \DateTimeImmutable($dtfString) : null;
        $annee = $finContrat ? (int) $finContrat->format('Y') : (int) date('Y');

        $fromDate = sprintf('%04d-01-01', $annee);
        $toDate = sprintf('%04d-12-31', $annee);

        $sql = "UPDATE opulise.GLRUC
                SET GLGPR_COD = :code,
                    GLGPR_DTD = TO_DATE(:dtd, 'YYYY-MM-DD')
                WHERE GLCON_NUM = :contrat
                  AND (GLRUC_DTF BETWEEN TO_DATE(:fromDate, 'YYYY-MM-DD')
                                    AND TO_DATE(:toDate, 'YYYY-MM-DD')
                       OR GLRUC_DTF IS NULL)
                  AND GLRUB_COD IN ('C009', 'C011', 'C115', 'C116')";

        $this->getConnection()->executeStatement($sql, [
            'code' => trim($groupePrixCode),
            'dtd' => $groupePrixDateIso,
            'contrat' => $contrat,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
        ]);
    }
}
