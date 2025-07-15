<?php
namespace App\Service;

use Doctrine\DBAL\Connection;

class EditionBureautiqueOracleService
{
    private Connection $connection;

    public function __construct(Connection $defaultConnection)
    {
        // Utilise la connexion par défaut (Oracle, selon DATABASE_URL)
        $this->connection = $defaultConnection;
    }

    public function fetchEditions(): array
    {
        $sql = <<<SQL
SELECT
    NOMBRE,
	ANNEE,
	NOM_BI,
	DESCRIPTION_BI,
	UTILISATEUR,
	DERNIERE_UTILISATION
FROM
	STATS_BI
WHERE
	ROWNUM <= 5
SQL;
        return $this->connection->fetchAllAssociative($sql);
    }
} 