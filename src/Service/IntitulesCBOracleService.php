<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class IntitulesCBOracleService
{
    private Connection $defaultConnection;
    private Connection $etudesConnection;

    public function __construct(Connection $defaultConnection, Connection $etudesConnection)
    {
        $this->defaultConnection = $defaultConnection;
        $this->etudesConnection = $etudesConnection;
    }

    public function generateCsvForToday(): string
    {
        $sql = "SELECT caint_num AS NO_INTITULE, nom, mdp AS CLE FROM internet_intitules WHERE TRUNC(datecre) = TRUNC(SYSDATE) ORDER BY caint_num";

        // Appeler la procédure avec un argument vide
        try {
            $this->defaultConnection->executeStatement('BEGIN INS_INTERNET_INTITULE(:p); END;', ['p' => '']);
            $rows = $this->defaultConnection->executeQuery($sql)->fetchAllAssociative();
            return $this->toCsv($rows);
        } catch (\Throwable $e) {
            // Fallback: connexion ETUDES
            try {
                $this->etudesConnection->executeStatement('BEGIN INS_INTERNET_INTITULE(:p); END;', ['p' => '']);
                $rows = $this->etudesConnection->executeQuery($sql)->fetchAllAssociative();
                return $this->toCsv($rows);
            } catch (\Throwable $e2) {
                // Dernier essai: qualifier le schéma ETUDES
                $this->etudesConnection->executeStatement('BEGIN ETUDES.INS_INTERNET_INTITULE(:p); END;', ['p' => '']);
                $rows = $this->etudesConnection->executeQuery($sql)->fetchAllAssociative();
                return $this->toCsv($rows);
            }
        }
    }

    private function toCsv(array $rows): string
    {
        $output = fopen('php://temp', 'r+');
        // En-têtes
        fputcsv($output, ['NO_INTITULE', 'NOM', 'CLE'], ';');
        foreach ($rows as $row) {
            fputcsv($output, [
                $row['NO_INTITULE'] ?? '',
                $row['NOM'] ?? '',
                $row['CLE'] ?? '',
            ], ';');
        }
        rewind($output);
        return stream_get_contents($output) ?: '';
    }
}


