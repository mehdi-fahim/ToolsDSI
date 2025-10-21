<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

class UserActionLogger
{
    public function __construct(
        private LoggerInterface $userActionsLogger,
        private LoggerInterface $systemEventsLogger
    ) {}

    /**
     * Log une action utilisateur
     */
    public function logUserAction(string $action, array $context = [], ?string $userId = null): void
    {
        $logData = [
            'action' => $action,
            'user_id' => $userId,
            'timestamp' => date('Y-m-d H:i:s'),
            'context' => $context
        ];

        $this->userActionsLogger->info("User Action: {$action}", $logData);
    }

    /**
     * Log une action système
     */
    public function logSystemEvent(string $event, array $context = []): void
    {
        $logData = [
            'event' => $event,
            'timestamp' => date('Y-m-d H:i:s'),
            'context' => $context
        ];

        $this->systemEventsLogger->info("System Event: {$event}", $logData);
    }

    /**
     * Log une connexion utilisateur
     */
    public function logUserLogin(string $userId, string $ipAddress, bool $success = true): void
    {
        $this->logUserAction('LOGIN', [
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'success' => $success
        ], $userId);
    }

    /**
     * Log une déconnexion utilisateur
     */
    public function logUserLogout(string $userId): void
    {
        $this->logUserAction('LOGOUT', [], $userId);
    }

    /**
     * Log une modification de données
     */
    public function logDataModification(string $entity, string $action, array $data, ?string $userId = null): void
    {
        $this->logUserAction("DATA_{$action}", [
            'entity' => $entity,
            'data' => $data
        ], $userId);
    }

    /**
     * Log une action système critique
     */
    public function logCriticalSystemAction(string $action, array $context = []): void
    {
        $this->logSystemEvent("CRITICAL_{$action}", $context);
    }

    /**
     * Log une erreur système
     */
    public function logSystemError(string $error, array $context = []): void
    {
        $this->logSystemEvent("ERROR", [
            'error' => $error,
            'context' => $context
        ]);
    }
}
