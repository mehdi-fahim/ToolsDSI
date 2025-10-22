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
     * Parse une ligne de log
     */
    private function parseLogLine(string $line): array
    {
        // Format Monolog: [timestamp] channel.LEVEL: message context
        if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+)\.(\w+): (.+)$/', $line, $matches)) {
            return [
                'timestamp' => $matches[1],
                'channel' => $matches[2],
                'level' => $matches[3],
                'message' => $matches[4],
                'raw' => $line
            ];
        }

        // Format simple
        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'channel' => 'unknown',
            'level' => 'info',
            'message' => $line,
            'raw' => $line
        ];
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
}
