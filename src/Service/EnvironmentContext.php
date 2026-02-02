<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Gestion de l'environnement de travail (Prod / Preprod / Test)
 * stocké en session, afin d'être partagé entre contrôleurs et services.
 *
 * Remarque : par défaut, l'environnement est "prod".
 */
class EnvironmentContext
{
    private const SESSION_KEY = 'db_environment';

    /**
     * Environnements autorisés.
     */
    private const ALLOWED_ENVIRONMENTS = ['prod', 'preprod', 'test'];

    public function __construct(private RequestStack $requestStack)
    {
    }

    /**
     * Retourne l'environnement courant (prod / preprod / test).
     */
    public function getCurrentEnvironment(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return 'prod';
        }

        $session = $request->getSession();
        if (!$session) {
            return 'prod';
        }

        $env = $session->get(self::SESSION_KEY, 'prod');

        if (!in_array($env, self::ALLOWED_ENVIRONMENTS, true)) {
            return 'prod';
        }

        return $env;
    }

    /**
     * Change l'environnement courant (stocké en session).
     */
    public function setEnvironment(string $environment): void
    {
        $environment = strtolower($environment);

        if (!in_array($environment, self::ALLOWED_ENVIRONMENTS, true)) {
            $environment = 'prod';
        }

        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        $session = $request->getSession();
        if (!$session) {
            return;
        }

        $session->set(self::SESSION_KEY, $environment);
    }
}

