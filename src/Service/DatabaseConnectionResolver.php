<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

/**
 * Retourne la connexion Oracle correspondant à l'environnement choisi en session
 * (Prod / Préprod / Test), pour permettre le changement d'environnement via le bouton admin.
 */
class DatabaseConnectionResolver
{
    public function __construct(
        private EnvironmentContext $environmentContext,
        private Connection $prodConnection,
        private Connection $preprodConnection,
        private Connection $testConnection,
    ) {
    }

    /**
     * Connexion à utiliser pour la requête courante (selon la session).
     */
    public function getConnection(): Connection
    {
        return match ($this->environmentContext->getCurrentEnvironment()) {
            'preprod' => $this->preprodConnection,
            'test' => $this->testConnection,
            default => $this->prodConnection,
        };
    }
}
