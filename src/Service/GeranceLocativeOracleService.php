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
}
