<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

class DetailedUserActionLogger
{
    public function __construct(
        private LoggerInterface $userActionsLogger
    ) {}

    /**
     * Log une action spécifique de l'utilisateur
     */
    public function logUserAction(string $action, array $context = [], ?string $userId = null, ?string $ipAddress = null): void
    {
        $logData = [
            'action' => $action,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'timestamp' => date('Y-m-d H:i:s'),
            'context' => $context
        ];

        $this->userActionsLogger->info("User Action: {$action}", $logData);
    }

    /**
     * Log l'accès à une page
     */
    public function logPageAccess(string $page, ?string $userId = null, ?string $ipAddress = null): void
    {
        $this->logUserAction('PAGE_ACCESS', [
            'page' => $page,
            'action_type' => 'navigation'
        ], $userId, $ipAddress);
    }

    /**
     * Log un clic sur un bouton
     */
    public function logButtonClick(string $buttonName, string $page, ?string $userId = null, ?string $ipAddress = null): void
    {
        $this->logUserAction('BUTTON_CLICK', [
            'button_name' => $buttonName,
            'page' => $page,
            'action_type' => 'interaction'
        ], $userId, $ipAddress);
    }

    /**
     * Log l'envoi d'un formulaire
     */
    public function logFormSubmit(string $formName, string $page, array $formData = [], ?string $userId = null, ?string $ipAddress = null): void
    {
        $this->logUserAction('FORM_SUBMIT', [
            'form_name' => $formName,
            'page' => $page,
            'form_data' => $formData,
            'action_type' => 'form_submission'
        ], $userId, $ipAddress);
    }

    /**
     * Log une recherche
     */
    public function logSearch(string $searchTerm, string $searchType, ?string $userId = null, ?string $ipAddress = null): void
    {
        $this->logUserAction('SEARCH', [
            'search_term' => $searchTerm,
            'search_type' => $searchType,
            'action_type' => 'search'
        ], $userId, $ipAddress);
    }

    /**
     * Log une modification de données
     */
    public function logDataModification(string $entity, string $action, array $data, ?string $userId = null, ?string $ipAddress = null): void
    {
        $this->logUserAction('DATA_MODIFICATION', [
            'entity' => $entity,
            'modification_type' => $action,
            'data' => $data,
            'action_type' => 'data_change'
        ], $userId, $ipAddress);
    }

    /**
     * Log une action système critique
     */
    public function logSystemAction(string $action, array $context = [], ?string $userId = null, ?string $ipAddress = null): void
    {
        $this->logUserAction('SYSTEM_ACTION', [
            'system_action' => $action,
            'context' => $context,
            'action_type' => 'system'
        ], $userId, $ipAddress);
    }

    /**
     * Log une erreur utilisateur
     */
    public function logUserError(string $error, string $page, array $context = [], ?string $userId = null, ?string $ipAddress = null): void
    {
        $this->logUserAction('USER_ERROR', [
            'error_message' => $error,
            'page' => $page,
            'context' => $context,
            'action_type' => 'error'
        ], $userId, $ipAddress);
    }
}
