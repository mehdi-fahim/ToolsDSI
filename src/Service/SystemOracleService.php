<?php

namespace App\Service;

class SystemOracleService
{
    private function getOciConnection()
    {
        $host = '172.22.0.30';
        $port = 1521;
        $service = 'OPULISE';
        $username = 'SYS';
        $password = 'PCH93200';
        $connStr = "//{$host}:{$port}/{$service}";

        $conn = @oci_connect($username, $password, $connStr, 'AL32UTF8', OCI_SYSDBA);
        if (!$conn) {
            $e = oci_error();
            $message = is_array($e) && isset($e['message']) ? (string) $e['message'] : 'Connexion SYSDBA impossible';
            throw new \RuntimeException($message);
        }
        return $conn;
    }

    private function fetchAllAssociative(string $sql): array
    {
        $conn = $this->getOciConnection();
        $stid = @oci_parse($conn, $sql);
        if (!$stid) {
            $e = oci_error($conn);
            oci_close($conn);
            throw new \RuntimeException(is_array($e) && isset($e['message']) ? (string) $e['message'] : 'Erreur de préparation SQL');
        }

        if (!@oci_execute($stid)) {
            $e = oci_error($stid);
            oci_free_statement($stid);
            oci_close($conn);
            throw new \RuntimeException(is_array($e) && isset($e['message']) ? (string) $e['message'] : 'Erreur d’exécution SQL');
        }

        $rows = [];
        while (($row = oci_fetch_assoc($stid)) !== false) {
            $normalized = [];
            foreach ($row as $k => $v) {
                $normalized[strtolower($k)] = $v;
            }
            $rows[] = $normalized;
        }

        oci_free_statement($stid);
        oci_close($conn);
        return $rows;
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
        return $this->fetchAllAssociative($sql);
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
        return $this->fetchAllAssociative($sql);
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
        return $this->fetchAllAssociative($sql);
    }

    /**
     * Mode auto (essaie successivement GV+DBA, V+DBA, V+ALL)
     */
    public function getLockedTables(): array
    {
        // If a custom view is configured, try it first
        $custom = getenv('LOCKS_VIEW');
        if ($custom) {
            try {
                $rows = $this->getLockedTablesFromCustomView($custom);
                if (!empty($rows)) { return $rows; }
            } catch (\Throwable $e) {
                // continue with fallbacks
            }
        }

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
     * Utilise une vue personnalisée fournie par la DSI (paramètre env LOCKS_VIEW)
     * La vue doit exposer au minimum: SID, SERIAL#, OBJECT_NAME, OSUSER, STATUS
     */
    public function getLockedTablesFromCustomView(string $viewName): array
    {
        $sql = "SELECT sid, serial# AS serial, object_name, osuser, status FROM " . $viewName;
        return $this->fetchAllAssociative($sql);
    }

    /**
     * Tue une session spécifique
     */
    public function killSession(int $sid, int $serial): bool
    {
        try {
            $conn = $this->getOciConnection();
            $sql = "ALTER SYSTEM KILL SESSION '" . (int) $sid . "," . (int) $serial . "' IMMEDIATE";
            $stid = @oci_parse($conn, $sql);
            if (!$stid || !@oci_execute($stid)) {
                if ($stid) {
                    oci_free_statement($stid);
                }
                oci_close($conn);
                return false;
            }
            oci_free_statement($stid);
            oci_close($conn);
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
