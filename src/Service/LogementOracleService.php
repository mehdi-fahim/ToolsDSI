<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class LogementOracleService
{
    public function __construct(private Connection $defaultConnection)
    {
    }

    /**
     * Recherche les informations principales d'une demande par numéro
     */
    public function getDemandeByNumero(string $numeroDemande): ?array
    {
        $sql = "SELECT ACDOS_NUM, ACDEM_ETA FROM ACDEM WHERE ACDOS_NUM = :num";
        $row = $this->defaultConnection->fetchAssociative($sql, ['num' => $numeroDemande]);
        return $row ?: null;
    }

    /**
     * Met à jour l'état de la demande
     */
    public function updateEtatDemande(string $numeroDemande, string $etat): int
    {
        $sql = "UPDATE ACDEM SET ACDEM_ETA = :etat WHERE ACDOS_NUM = :num";
        return $this->defaultConnection->executeStatement($sql, [
            'etat' => $etat,
            'num' => $numeroDemande,
        ]);
    }

    /**
     * Récupère le code tiers (TOTIE_COD) pour un rôle donné (CAND = demandeur, CODEM = co-demandeur)
     */
    public function getTiersByRole(string $numeroDemande, string $role): ?string
    {
        $sql = "SELECT TOTIE_COD FROM ACPAR WHERE ACDOS_NUM = :num AND ACTPA_COD = :role";
        $val = $this->defaultConnection->fetchOne($sql, ['num' => $numeroDemande, 'role' => $role]);
        return $val !== false ? (string) $val : null;
    }

    /**
     * Récupère les dates début/fin (ACPAR_DTDDPA / ACPAR_DTFPAR) pour un rôle (CAND/CODEM)
     */
    public function getDatesByRole(string $numeroDemande, string $role): array
    {
        $sqlDebut = "SELECT ACPAR_DTDDPA FROM ACPAR WHERE ACDOS_NUM = :num AND ACTPA_COD = :role";
        $sqlFin = "SELECT ACPAR_DTFPAR FROM ACPAR WHERE ACDOS_NUM = :num AND ACTPA_COD = :role";
        $debut = $this->defaultConnection->fetchOne($sqlDebut, ['num' => $numeroDemande, 'role' => $role]);
        $fin = $this->defaultConnection->fetchOne($sqlFin, ['num' => $numeroDemande, 'role' => $role]);
        return [
            'debut' => $debut !== false ? (string) $debut : null,
            'fin' => $fin !== false ? (string) $fin : null,
        ];
    }

    /**
     * Met à jour les infos d'un rôle (code tiers et dates)
     */
    public function updateRole(string $numeroDemande, string $role, ?string $tiers, ?string $dateDebut, ?string $dateFin): int
    {
        $sql = "UPDATE ACPAR SET TOTIE_COD = :tiers, ACPAR_DTDDPA = :dateDebut, ACPAR_DTFPAR = :dateFin WHERE ACDOS_NUM = :num AND ACTPA_COD = :role";
        return $this->defaultConnection->executeStatement($sql, [
            'tiers' => $tiers,
            'dateDebut' => $dateDebut,
            'dateFin' => $dateFin,
            'num' => $numeroDemande,
            'role' => $role,
        ]);
    }

    /**
     * Supprime le co-demandeur pour une demande
     */
    public function deleteCoDemandeur(string $numeroDemande, string $tiers): int
    {
        $sql = "DELETE FROM ACPAR WHERE ACDOS_NUM = :num AND TOTIE_COD = :tiers AND ACTPA_COD = 'CODEM'";
        return $this->defaultConnection->executeStatement($sql, [
            'num' => $numeroDemande,
            'tiers' => $tiers,
        ]);
    }
}


