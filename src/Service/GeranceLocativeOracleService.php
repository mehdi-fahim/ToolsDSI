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
}
