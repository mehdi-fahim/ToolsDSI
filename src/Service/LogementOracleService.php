<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use App\Service\DatabaseConnectionResolver;

class LogementOracleService
{
    public function __construct(private DatabaseConnectionResolver $connectionResolver)
    {
    }

    private function getConnection(): Connection
    {
        return $this->connectionResolver->getConnection();
    }

    /**
     * Recherche les informations principales d'une demande par numéro
     */
    public function getDemandeByNumero(string $numeroDemande): ?array
    {
        $sql = "SELECT ACDOS_NUM, ACDEM_ETA FROM ACDEM WHERE ACDOS_NUM = :num";
        $row = $this->getConnection()->executeQuery($sql, ['num' => $numeroDemande])->fetchAssociative();
        return $row ?: null;
    }

    /**
     * Met à jour l'état de la demande
     */
    public function updateEtatDemande(string $numeroDemande, string $etat): int
    {
        $sql = "UPDATE ACDEM SET ACDEM_ETA = :etat WHERE ACDOS_NUM = :num";
        return $this->getConnection()->executeStatement($sql, [
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
        $val = $this->getConnection()->executeQuery($sql, ['num' => $numeroDemande, 'role' => $role])->fetchOne();
        return $val !== false ? (string) $val : null;
    }

    /**
     * Récupère les dates début/fin (ACPAR_DTDDPA / ACPAR_DTFPAR) pour un rôle (CAND/CODEM)
     */
    public function getDatesByRole(string $numeroDemande, string $role): array
    {
        $sqlDebut = "SELECT ACPAR_DTDDPA FROM ACPAR WHERE ACDOS_NUM = :num AND ACTPA_COD = :role";
        $sqlFin = "SELECT ACPAR_DTFPAR FROM ACPAR WHERE ACDOS_NUM = :num AND ACTPA_COD = :role";
        $debut = $this->getConnection()->executeQuery($sqlDebut, ['num' => $numeroDemande, 'role' => $role])->fetchOne();
        $fin = $this->getConnection()->executeQuery($sqlFin, ['num' => $numeroDemande, 'role' => $role])->fetchOne();
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
        // Par sécurité, exiger un tiers pour cibler une seule ligne et éviter les violations de contrainte unique
        if ($tiers === null || $tiers === '') {
            throw new \InvalidArgumentException('Le code tiers est requis pour mettre à jour ce rôle.');
        }

        // Cibler explicitement la ligne du rôle ET du tiers pour ne pas modifier plusieurs enregistrements
        $sql = "UPDATE ACPAR
                SET TOTIE_COD = :tiers,
                    ACPAR_DTDDPA = :dateDebut,
                    ACPAR_DTFPAR = :dateFin
                WHERE ACDOS_NUM = :num
                  AND ACTPA_COD = :role
                  AND TOTIE_COD = :tiers";

        return $this->getConnection()->executeStatement($sql, [
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
        return $this->getConnection()->executeStatement($sql, [
            'num' => $numeroDemande,
            'tiers' => $tiers,
        ]);
    }

    /**
     * Quand on supprime la date de fin d'un CAND, clôturer les autres CAND de la même demande
     * et garder uniquement ce tiers sans date de fin.
     */
    public function closeOtherCandidatesAndKeepOneOpen(string $numeroDemande, string $tiersToKeep): void
    {
        // Ouvrir le tiers sélectionné (date fin NULL)
        $this->getConnection()->executeStatement(
            "UPDATE ACPAR SET ACPAR_DTFPAR = NULL WHERE ACDOS_NUM = :num AND ACTPA_COD = 'CAND' AND TOTIE_COD = :tiers",
            ['num' => $numeroDemande, 'tiers' => $tiersToKeep]
        );

        // Clôturer les autres candidats (si pas déjà clôturés)
        $this->getConnection()->executeStatement(
            "UPDATE ACPAR SET ACPAR_DTFPAR = SYSDATE WHERE ACDOS_NUM = :num AND ACTPA_COD = 'CAND' AND TOTIE_COD <> :tiers AND (ACPAR_DTFPAR IS NULL OR ACPAR_DTFPAR > SYSDATE)",
            ['num' => $numeroDemande, 'tiers' => $tiersToKeep]
        );
    }
}


