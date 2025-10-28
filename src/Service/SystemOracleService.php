<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class SystemOracleService
{
    public function __construct(private Connection $defaultConnection)
    {
    }

    /**
     * Récupère la liste des verrous (GV$ + DBA_OBJECTS)
     */
    public function getLockedTablesGV_DBA(): array
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
        return $this->defaultConnection->executeQuery($sql)->fetchAllAssociative();
    }

    /**
     * Récupère la liste des verrous (V$ + DBA_OBJECTS)
     */
    public function getLockedTablesV_DBA(): array
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
        FROM v$locked_object v,
             dba_objects d,
             v$lock l,
             v$session s
        WHERE v.object_id = d.object_id
          AND v.object_id = l.id1
          AND v.session_id = s.sid
          AND d.object_type = 'TABLE'
        ORDER BY d.object_name
        SQL;
        return $this->defaultConnection->executeQuery($sql)->fetchAllAssociative();
    }

    /**
     * Récupère la liste des verrous (V$ + ALL_OBJECTS)
     */
    public function getLockedTablesV_ALL(): array
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
        FROM v$locked_object v,
             all_objects d,
             v$lock l,
             v$session s
        WHERE v.object_id = d.object_id
          AND v.object_id = l.id1
          AND v.session_id = s.sid
          AND d.object_type = 'TABLE'
        ORDER BY d.object_name
        SQL;
        return $this->defaultConnection->executeQuery($sql)->fetchAllAssociative();
    }

    /**
     * Mode auto (essaie successivement GV+DBA, V+DBA, V+ALL)
     */
    public function getLockedTables(): array
    {
        foreach (['GV_DBA', 'V_DBA', 'V_ALL'] as $variant) {
            try {
                $rows = match ($variant) {
                    'GV_DBA' => $this->getLockedTablesGV_DBA(),
                    'V_DBA' => $this->getLockedTablesV_DBA(),
                    default => $this->getLockedTablesV_ALL(),
                };
                if (!empty($rows)) {
                    return $rows;
                }
            } catch (\Throwable $e) {
                // continue to next variant
            }
        }
        return [];
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
