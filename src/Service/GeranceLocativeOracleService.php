<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

/**
 * Service Oracle pour la Gérance Locative (CALIG, procédure ADD_DG_CPT_DG).
 */
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
