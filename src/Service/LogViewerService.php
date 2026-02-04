<?php

namespace App\Service;

class LogViewerService
{
    private string $logsDir;

    public function __construct(string $kernelLogsDir)
    {
        $this->logsDir = $kernelLogsDir;
    }

    /**
     * Récupère les logs d'actions utilisateur
     */
    public function getUserActionLogs(int $limit = 100, ?string $userId = null): array
    {
        $logFile = $this->logsDir . '/user_actions.log';
        $logs = $this->parseLogFile($logFile, $limit);
        
        // Filtrer par utilisateur si spécifié (cherche dans la ligne brute pour inclure le contexte)
        if ($userId !== null && $userId !== '') {
            $logs = array_filter($logs, function($log) use ($userId) {
                return isset($log['raw']) && stripos($log['raw'], $userId) !== false;
            });
        }
        
        return $logs;
    }

    /**
     * Récupère les logs d'événements système
     */
    public function getSystemEventLogs(int $limit = 100, ?string $userId = null): array
    {
        $logFile = $this->logsDir . '/system_events.log';
        $logs = $this->parseLogFile($logFile, $limit);
        
        // Filtrer par utilisateur si spécifié (cherche dans la ligne brute pour inclure le contexte)
        if ($userId !== null && $userId !== '') {
            $logs = array_filter($logs, function($log) use ($userId) {
                return isset($log['raw']) && stripos($log['raw'], $userId) !== false;
            });
        }
        
        return $logs;
    }

    /**
     * Récupère les logs généraux
     */
    public function getGeneralLogs(int $limit = 100, ?string $userId = null): array
    {
        $logFile = $this->logsDir . '/dev.log';
        $logs = $this->parseLogFile($logFile, $limit);
        
        // Filtrer par utilisateur si spécifié (cherche dans la ligne brute pour inclure le contexte)
        if ($userId !== null && $userId !== '') {
            $logs = array_filter($logs, function($log) use ($userId) {
                return isset($log['raw']) && stripos($log['raw'], $userId) !== false;
            });
        }
        
        return $logs;
    }

    /**
     * Parse un fichier de log
     */
    private function parseLogFile(string $logFile, int $limit): array
    {
        if (!file_exists($logFile)) {
            return [];
        }

        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $lines = array_reverse($lines); // Plus récent en premier
        $lines = array_slice($lines, 0, $limit);

        $logs = [];
        foreach ($lines as $line) {
            $logs[] = $this->parseLogLine($line);
        }

        return $logs;
    }

    /**
     * Parse une ligne de log.
     * La date affichée est toujours celle de l'action (extraite de la ligne), jamais la date du jour.
     */
    private function parseLogLine(string $line): array
    {
        // Format Monolog: [timestamp] channel.LEVEL: message context
        if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+)\.(\w+): (.+)$/s', $line, $matches)) {
            return [
                'timestamp' => $matches[1],
                'channel' => $matches[2],
                'level' => $matches[3],
                'message' => $matches[4],
                'raw' => $line
            ];
        }

        // Ligne ne correspondant pas au format standard : extraire la date de l'action depuis la ligne (JSON ou [YYYY-MM-DD])
        $ts = $this->extractTimestampFromLine($line);
        if ($ts !== null) {
            $timestamp = date('Y-m-d H:i:s', $ts);
        } else {
            $context = $this->extractJsonContext($line, $line);
            $timestamp = $context['timestamp'] ?? null;
        }

        return [
            'timestamp' => $timestamp,
            'channel' => 'unknown',
            'level' => 'info',
            'message' => $line,
            'raw' => $line
        ];
    }

    /**
     * Historique filtré des actions utilisateur (par user, action, ip, période)
     */
    public function getUserActionHistory(
        ?string $userId,
        ?string $action,
        ?string $ip,
        ?string $fromDate,
        ?string $toDate,
        int $limit = 200,
        int $page = 1
    ): array
    {
        $logFile = $this->logsDir . '/user_actions.log';
        $emptyResult = [
            'data' => [],
            'total' => 0,
            'page' => max(1, $page),
            'limit' => $limit,
            'totalPages' => 0,
        ];
        if (!file_exists($logFile)) {
            return $emptyResult;
        }

        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return $emptyResult;
        }
        $lines = array_reverse($lines); // récents d'abord

        $fromTs = $fromDate ? strtotime($fromDate . ' 00:00:00') : null;
        $toTs = $toDate ? strtotime($toDate . ' 23:59:59') : null;
        $matched = [];

        foreach ($lines as $line) {
            $parsed = $this->parseLogLine($line);

            // Filtre période
            $ts = isset($parsed['timestamp']) ? strtotime($parsed['timestamp']) : null;
            if ($fromTs && $ts && $ts < $fromTs) {
                continue;
            }
            if ($toTs && $ts && $ts > $toTs) {
                continue;
            }

            $raw = $parsed['raw'] ?? '';
            $msg = strtolower((string)($parsed['message'] ?? ''));
            $rawLower = strtolower($raw);

            // Filtre user
            if ($userId !== null && $userId !== '' && stripos($raw, $userId) === false) {
                continue;
            }

            // Filtre action (recherche dans message brut et dans contexte)
            if ($action !== null && $action !== '') {
                $actionLower = strtolower($action);
                if (strpos($msg, $actionLower) === false && strpos($rawLower, $actionLower) === false) {
                    continue;
                }
            }

            // Filtre IP
            if ($ip !== null && $ip !== '' && strpos($rawLower, strtolower($ip)) === false) {
                continue;
            }

            // Enrichir avec des champs structurés (user_id, action, ip, route, entity, entity_id, summary)
            $enriched = $this->enrichParsedEntry($parsed);
            $matched[] = $enriched;
        }

        // Pagination
        $total = count($matched);
        $page = max(1, $page);
        $offset = ($page - 1) * $limit;
        $paged = array_slice($matched, $offset, $limit);

        return [
            'data' => $paged,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => (int) ceil($total / max(1, $limit)),
        ];
    }

    /**
     * Extrait des informations structurées depuis une entrée parsée.
     * Tente d'abord de parser le contexte JSON (Monolog) en fin de ligne.
     */
    private function enrichParsedEntry(array $parsed): array
    {
        $raw = (string)($parsed['raw'] ?? '');
        $message = (string)($parsed['message'] ?? '');

        $context = $this->extractJsonContext($raw, $message);

        $parsed['user_id'] = $context['user_id'] ?? $this->extractField($raw, ['user_id','user']);
        $parsed['ip'] = $context['ip_address'] ?? $context['ip'] ?? $this->extractField($raw, ['ip_address','ip','client_ip','remote_ip']) ?: $this->extractIpFallback($raw);
        $parsed['route'] = $context['route'] ?? $context['page'] ?? $this->extractField($raw, ['route','_route','path','page']) ?: $this->extractRouteFallback($raw, $message);
        $parsed['method'] = $context['context']['method'] ?? $context['method'] ?? $this->extractField($raw, ['method']);
        $parsed['entity'] = ($context['context']['entity'] ?? null) ?? $this->extractField($raw, ['entity','target','resource']);
        $parsed['entity_id'] = ($context['context']['id'] ?? $context['context']['entity_id'] ?? null) ?? $this->extractField($raw, ['id','entity_id','target_id']);
        $parsed['action'] = $context['action'] ?? $this->extractField($raw, ['action','event','verb']) ?: $this->guessActionFromText($message);

        return $parsed;
    }

    /**
     * Tente d'extraire un objet JSON en fin de ligne (contexte Monolog).
     */
    private function extractJsonContext(string $raw, string $message): array
    {
        foreach ([$raw, $message] as $str) {
            $pos = strrpos($str, '{');
            if ($pos === false) {
                continue;
            }
            $json = substr($str, $pos);
            $decoded = json_decode($json, true);
            if (\is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }

    private function extractField(string $text, array $keys): ?string
    {
        foreach ($keys as $key) {
            // JSON-like: "key":"value"
            if (preg_match('/"' . preg_quote($key, '/') . '"\s*:\s*"([^"]+)"/i', $text, $m)) {
                return $m[1];
            }
            // JSON-like numeric: "key": 123
            if (preg_match('/"' . preg_quote($key, '/') . '"\s*:\s*([0-9A-Za-z_\-\.]+)/i', $text, $m)) {
                return $m[1];
            }
            // key=value
            if (preg_match('/\b' . preg_quote($key, '/') . '=([^\s,;]+)/i', $text, $m)) {
                return trim($m[1], '\"');
            }
        }
        return null;
    }

    private function guessActionFromText(string $text): ?string
    {
        $l = strtolower($text);
        if (str_contains($l, 'create') || str_contains($l, 'création') || str_contains($l, 'created')) return 'create';
        if (str_contains($l, 'update') || str_contains($l, 'modification') || str_contains($l, 'updated')) return 'update';
        if (str_contains($l, 'delete') || str_contains($l, 'suppression') || str_contains($l, 'deleted')) return 'delete';
        if (str_contains($l, 'view') || str_contains($l, 'consultation') || str_contains($l, 'access')) return 'view';
        return null;
    }

    private function buildSummary(string $message): string
    {
        // Supprimer les gros blocs JSON/contexts pour un aperçu court
        $clean = preg_replace('/\{.*\}$/s', '', $message) ?? $message;
        $clean = trim($clean);
        return strlen($clean) > 200 ? substr($clean, 0, 200) . '…' : $clean;
    }

    private function extractIpFallback(string $text): ?string
    {
        if (preg_match('/\b(\d{1,3}\.){3}\d{1,3}\b/', $text, $m)) {
            return $m[0];
        }
        return null;
    }

    private function extractRouteFallback(string $raw, string $message): ?string
    {
        // Chercher un motif de route Symfony typique admin_xxx
        if (preg_match('/\b(admin_[a-z0-9_\-]+)/i', $raw, $m)) {
            return $m[1];
        }
        if (preg_match('/\b(admin_[a-z0-9_\-]+)/i', $message, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Récupère les statistiques des logs
     */
    public function getLogStats(): array
    {
        $userActionsFile = $this->logsDir . '/user_actions.log';
        $systemEventsFile = $this->logsDir . '/system_events.log';
        $generalFile = $this->logsDir . '/dev.log';

        return [
            'user_actions' => [
                'exists' => file_exists($userActionsFile),
                'size' => file_exists($userActionsFile) ? filesize($userActionsFile) : 0,
                'lines' => file_exists($userActionsFile) ? count(file($userActionsFile)) : 0
            ],
            'system_events' => [
                'exists' => file_exists($systemEventsFile),
                'size' => file_exists($systemEventsFile) ? filesize($systemEventsFile) : 0,
                'lines' => file_exists($systemEventsFile) ? count(file($systemEventsFile)) : 0
            ],
            'general' => [
                'exists' => file_exists($generalFile),
                'size' => file_exists($generalFile) ? filesize($generalFile) : 0,
                'lines' => file_exists($generalFile) ? count(file($generalFile)) : 0
            ]
        ];
    }

    /**
     * Récupère la liste des utilisateurs uniques dans les logs
     */
    public function getUniqueUsers(): array
    {
        $users = [];
        
        // Analyser les logs d'actions utilisateur
        $userActionsFile = $this->logsDir . '/user_actions.log';
        if (file_exists($userActionsFile)) {
            $lines = file($userActionsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                // Extraire les user_id des logs avec plusieurs patterns
                if (preg_match('/"user_id":"([^"]+)"/', $line, $matches)) {
                    $users[] = $matches[1];
                } elseif (preg_match('/"user_id":\s*"([^"]+)"/', $line, $matches)) {
                    $users[] = $matches[1];
                } elseif (preg_match('/user_id.*?([A-Z0-9_]+)/', $line, $matches)) {
                    $users[] = $matches[1];
                }
            }
        }
        
        // Analyser les logs système
        $systemEventsFile = $this->logsDir . '/system_events.log';
        if (file_exists($systemEventsFile)) {
            $lines = file($systemEventsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                // Extraire les user_id des logs système
                if (preg_match('/"user_id":"([^"]+)"/', $line, $matches)) {
                    $users[] = $matches[1];
                } elseif (preg_match('/"user_id":\s*"([^"]+)"/', $line, $matches)) {
                    $users[] = $matches[1];
                } elseif (preg_match('/user_id.*?([A-Z0-9_]+)/', $line, $matches)) {
                    $users[] = $matches[1];
                }
            }
        }
        
        // Analyser les logs généraux pour les connexions
        $generalFile = $this->logsDir . '/dev.log';
        if (file_exists($generalFile)) {
            $lines = file($generalFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                // Chercher les patterns de connexion
                if (preg_match('/logUserLogin.*?([A-Z0-9_]+)/', $line, $matches)) {
                    $users[] = $matches[1];
                } elseif (preg_match('/user_id.*?([A-Z0-9_]+)/', $line, $matches)) {
                    $users[] = $matches[1];
                }
            }
        }
        
        // Retourner les utilisateurs uniques triés
        $uniqueUsers = array_unique($users);
        sort($uniqueUsers);
        
        return $uniqueUsers;
    }

    /**
     * Purge les lignes de user_actions.log antérieures à $days jours.
     * Retourne un tableau avec total, kept, purged.
     */
    public function purgeUserActionsOlderThan(int $days): array
    {
        $logFile = $this->logsDir . '/user_actions.log';
        if (!file_exists($logFile) || $days <= 0) {
            return ['total' => 0, 'kept' => 0, 'purged' => 0];
        }

        $threshold = strtotime(sprintf('-%d days', $days));
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $total = count($lines);
        $keptLines = [];

        foreach ($lines as $line) {
            $ts = $this->extractTimestampFromLine($line);
            if ($ts === null || $ts >= $threshold) {
                $keptLines[] = $line;
            }
        }

        file_put_contents($logFile, implode(PHP_EOL, $keptLines) . (empty($keptLines) ? '' : PHP_EOL));

        $kept = count($keptLines);
        return [
            'total' => $total,
            'kept' => $kept,
            'purged' => max(0, $total - $kept),
        ];
    }

    private function extractTimestampFromLine(string $line): ?int
    {
        // Format monolog par défaut: [YYYY-MM-DD HH:MM:SS] channel.level: message
        if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $m)) {
            $ts = strtotime($m[1]);
            return $ts === false ? null : $ts;
        }
        // JSON-like context with "timestamp":"YYYY-MM-DD HH:MM:SS"
        if (preg_match('/\"timestamp\"\s*:\s*\"(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\"/', $line, $m)) {
            $ts = strtotime($m[1]);
            return $ts === false ? null : $ts;
        }
        return null;
    }
}
