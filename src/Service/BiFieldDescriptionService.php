<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class BiFieldDescriptionService
{
    private Connection $defaultConnection;

    public function __construct(Connection $defaultConnection)
    {
        $this->defaultConnection = $defaultConnection;
    }

    /**
     * Retourne la liste des champs (nom + libellé) pour un document BI donné.
     *
     * @param string $documentName Nom du document (ICTRS_NOM)
     * @return array<int, array{ICVAR_NOM: string|null, ICVAR_LIB: string|null}>
     */
    public function getFieldsForDocument(string $documentName): array
    {
        if ($documentName === '') {
            return [];
        }

        $sql = <<<SQL
SELECT ICVAR_NOM, ICVAR_LIB
FROM ICVAR
WHERE ICTRS_NOM = :document
ORDER BY ICVAR_NOM
SQL;

        try {
            return $this->defaultConnection
                ->executeQuery($sql, ['document' => $documentName])
                ->fetchAllAssociative();
        } catch (\Throwable $e) {
            throw new \RuntimeException('Erreur lors de la récupération des champs BI: ' . $e->getMessage(), previous: $e);
        }
    }
}


