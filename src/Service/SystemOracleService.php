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
        SELECT
            s.sid,
            s.serial# AS serial,
            NVL(s.username, 'SYS') AS username,
            s.osuser,
            s.status,
            s.machine,
            s.program,
            o.owner,
            o.object_name,
            l.type,
            l.lmode,
            l.request
        FROM v$lock l
        JOIN v$session s ON l.sid = s.sid
        LEFT JOIN dba_objects o ON o.object_id = l.id1
        WHERE l.block != 0 OR l.request != 0
        ORDER BY s.sid
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
