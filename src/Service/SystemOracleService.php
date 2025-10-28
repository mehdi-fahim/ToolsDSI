<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class SystemOracleService
{
    public function __construct(private Connection $defaultConnection)
    {
    }

    /**
     * Récupère la liste des verrous (sessions et objets potentiellement lockés)
     */
    public function getLockedTables(): array
    {
        $sql = <<<SQL
        SELECT DISTINCT
            s.sid,
            s.serial# AS serial,
            NVL(s.username, 'SYS') AS username,
            s.osuser,
            DECODE(l.block, 0, 'Not Blocking', 1, 'Blocking', 2, 'Global') AS status,
            s.machine,
            s.program,
            d.owner,
            d.object_name
        FROM gv$locked_object v,
             dba_objects d,
             gv$lock l,
             gv$session s
        WHERE v.object_id = d.object_id
          AND v.object_id = l.id1
          AND v.session_id = s.sid
          AND d.object_type = 'TABLE'
        ORDER BY d.object_name
        SQL;

        try {
            return $this->defaultConnection->executeQuery($sql)->fetchAllAssociative();
        } catch (\Throwable $e) {
            // En cas d'erreur (droits manquants), renvoyer tableau vide
            return [];
        }
    }

    /**
     * Tue une session spécifique
     */
    public function killSession(int $sid, int $serial): bool
    {
        $sql = "ALTER SYSTEM KILL SESSION :sess IMMEDIATE";
        $sessionId = $sid . ',' . $serial;
        try {
            $this->defaultConnection->executeStatement($sql, ['sess' => $sessionId]);
            return true;
        } catch (\Throwable $e) {
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
            $sid = (int)($session['sid'] ?? 0);
            $serial = (int)($session['serial'] ?? ($session['serial#'] ?? 0));
            $success = $this->killSession($sid, $serial);
            $results[] = [
                'sid' => $sid,
                'serial' => $serial,
                'object_name' => $session['object_name'] ?? null,
                'success' => $success
            ];
        }
        return $results;
    }
}
