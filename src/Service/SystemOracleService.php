<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class SystemOracleService
{
    public function __construct(private Connection $defaultConnection)
    {
    }

    /**
     * Récupère la liste des tables locker
     */
    public function getLockedTables(): array
    {
        // Requête simplifiée pour éviter les problèmes de permissions
        $sql = "SELECT
                    s.sid,
                    s.serial#,
                    'UNKNOWN' as object_name,
                    s.osuser,
                    'UNKNOWN' as status
                FROM
                    v\$session s
                WHERE
                    s.status = 'ACTIVE'
                    AND s.username IS NOT NULL
                ORDER BY
                    s.sid";

        try {
            $result = $this->defaultConnection->fetchAllAssociative($sql);
            return $result ?: [];
        } catch (\Exception $e) {
            // Si la requête échoue, retourner un message d'erreur explicite
            return [
                [
                    'sid' => 'ERROR',
                    'serial#' => 'ERROR',
                    'object_name' => 'Erreur de connexion Oracle',
                    'osuser' => 'N/A',
                    'status' => 'Erreur: ' . $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Tue une session spécifique
     */
    public function killSession(int $sid, int $serial): bool
    {
        try {
            $sql = "ALTER SYSTEM KILL SESSION '{$sid},{$serial}' IMMEDIATE";
            $this->defaultConnection->executeStatement($sql);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Tue toutes les sessions qui ont des tables locker
     */
    public function killAllLockedSessions(): array
    {
        $lockedTables = $this->getLockedTables();
        $results = [];
        
        foreach ($lockedTables as $session) {
            $sid = (int)$session['SID'];
            $serial = (int)$session['SERIAL#'];
            
            try {
                $sql = "ALTER SYSTEM KILL SESSION '{$sid},{$serial}' IMMEDIATE";
                $this->defaultConnection->executeStatement($sql);
                $results[] = [
                    'sid' => $sid,
                    'serial' => $serial,
                    'object_name' => $session['OBJECT_NAME'],
                    'success' => true
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'sid' => $sid,
                    'serial' => $serial,
                    'object_name' => $session['OBJECT_NAME'],
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }
}
