<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use App\Service\DatabaseConnectionResolver;

class PropositionOracleService
{
    public function __construct(private DatabaseConnectionResolver $connectionResolver)
    {
    }

    private function getConnection(): Connection
    {
        return $this->connectionResolver->getConnection();
    }

    public function getCandidatesByProposition(int $numeroProposition): array
    {
        $sql = "SELECT TODPP_PRE AS PRENOM,
                        TODPP_NOM AS NOM,
                        TODPP_DATNAI AS DATE_NAISSANCE,
                        ACPRS_NUM AS NUMERO_PROPOSITION,
                        TOTIE_COD AS NUMERO_TIERS,
                        ACDOS_NUM AS NUMERO_DOSSIER,
                        ACPRC_DATCRE AS DATE_CREATION
                FROM ACPRC
                WHERE ACPRS_NUM = :num";

        return $this->getConnection()->executeQuery($sql, ['num' => $numeroProposition])->fetchAllAssociative();
    }

    public function getProposition(int $numeroProposition): ?array
    {
        $sql = "SELECT 
                    ACPRS_NUM,
                    TOTIE_COD_RES,
                    TOTIE_LIB,
                    PAESI_CODEXT,
                    PANES_COD,
                    MGADR_LIB,
                    MGADR_CODPOS,
                    MGADR_LIBCOM,
                    TOTIE_LIB_GAR,
                    ACPRS_DATCRE
                FROM ACPRS 
                WHERE ACPRS_NUM = :num";
        $row = $this->getConnection()->fetchAssociative($sql, ['num' => $numeroProposition]);
        return $row ?: null;
    }

    public function deleteCandidate(int $numeroProposition, string $numeroTiers, string $numeroDossier): int
    {
        $sql = "DELETE FROM ACPRC WHERE ACPRS_NUM = :num AND TOTIE_COD = :tiers AND ACDOS_NUM = :dossier";
        return $this->getConnection()->executeStatement($sql, [
            'num' => $numeroProposition,
            'tiers' => $numeroTiers,
            'dossier' => $numeroDossier,
        ]);
    }

    public function deleteAllCandidates(int $numeroProposition): int
    {
        $sql = "DELETE FROM ACPRC WHERE ACPRS_NUM = :num";
        return $this->getConnection()->executeStatement($sql, ['num' => $numeroProposition]);
    }

    public function deleteProposition(int $numeroProposition): int
    {
        $sql = "DELETE FROM ACPRS WHERE ACPRS_NUM = :num";
        return $this->getConnection()->executeStatement($sql, ['num' => $numeroProposition]);
    }
}


