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
        $sql = "SELECT
                    DISTINCT s.sid,
                    s.serial#,
                    object_name,
                    s.osuser,
                    DECODE(l.block, 0, 'Not Blocking', 1, 'Blocking', 2, 'Global') STATUS
                FROM
                    gv\$locked_object v,
                    dba_objects d,
                    gv\$lock l,
                    gv\$session s
                WHERE
                    v.object_id = d.object_id
                    AND (v.object_id = l.id1)
                    AND v.session_id = s.sid
                    AND object_type = 'TABLE'
                ORDER BY
                    object_name";

        try {
            $result = $this->defaultConnection->fetchAllAssociative($sql);
            return $result ?: [];
        } catch (\Exception $e) {
            return [];
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
