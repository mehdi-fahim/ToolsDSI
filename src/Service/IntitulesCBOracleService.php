<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class IntitulesCBOracleService
{
    private Connection $connection;

    public function __construct(Connection $etudesConnection)
    {
        // This argument is autowired from doctrine.dbal.etudes_connection
        $this->connection = $etudesConnection;
    }

    public function generateCsvForToday(): string
    {
        // Appel de la procédure stockée INS_INTERNET_INTITULE
        // La procédure ne prend pas de paramètres utiles ici ("" dans le code VB)
        $this->connection->executeStatement('BEGIN INS_INTERNET_INTITULE(:p); END;', [
            'p' => ''
        ]);

        $sql = "SELECT caint_num AS NO_INTITULE, nom, mdp AS CLE FROM internet_intitules WHERE TRUNC(datecre) = TRUNC(SYSDATE) ORDER BY caint_num";
        $rows = $this->connection->executeQuery($sql)->fetchAllAssociative();
        return $this->toCsv($rows);
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


