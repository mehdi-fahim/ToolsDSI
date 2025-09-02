<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class PropositionOracleService
{
    public function __construct(private Connection $defaultConnection)
    {
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

        return $this->defaultConnection->fetchAllAssociative($sql, ['num' => $numeroProposition]);
    }

    public function getProposition(int $numeroProposition): ?array
    {
        $sql = "SELECT * FROM ACPRS WHERE ACPRS_NUM = :num";
        $row = $this->defaultConnection->fetchAssociative($sql, ['num' => $numeroProposition]);
        return $row ?: null;
    }

    public function deleteCandidate(int $numeroProposition, string $numeroTiers, string $numeroDossier): int
    {
        $sql = "DELETE FROM ACPRC WHERE ACPRS_NUM = :num AND TOTIE_COD = :tiers AND ACDOS_NUM = :dossier";
        return $this->defaultConnection->executeStatement($sql, [
            'num' => $numeroProposition,
            'tiers' => $numeroTiers,
            'dossier' => $numeroDossier,
        ]);
    }

    public function deleteAllCandidates(int $numeroProposition): int
    {
        $sql = "DELETE FROM ACPRC WHERE ACPRS_NUM = :num";
        return $this->defaultConnection->executeStatement($sql, ['num' => $numeroProposition]);
    }

    public function deleteProposition(int $numeroProposition): int
    {
        $sql = "DELETE FROM ACPRS WHERE ACPRS_NUM = :num";
        return $this->defaultConnection->executeStatement($sql, ['num' => $numeroProposition]);
    }
}


