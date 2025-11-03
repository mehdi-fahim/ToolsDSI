<?php

namespace App\EventListener;

use App\Service\DetailedUserActionLogger;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

#[AsEventListener(event: KernelEvents::CONTROLLER, method: 'onController')]
class UserActionRequestListener
{
    public function __construct(private DetailedUserActionLogger $logger)
    {
    }

    public function onController(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        // Ne loguer que les routes admin
        $route = (string) $request->attributes->get('_route', '');
        if ($route === '' || !str_starts_with($route, 'admin_')) {
            return;
        }

        // Infos de base
        $ip = $this->extractClientIp($request);
        /** @var SessionInterface|null $session */
        $session = $request->getSession();
        $userId = $session ? (string) strtoupper((string) $session->get('user_id', '')) : '';

        // Eviter de loguer la même page plusieurs fois pour les assets internes
        // Ici, on logue chaque appel controller admin_*
        $context = [
            'route' => $route,
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'query' => $request->query->all(),
        ];

        $this->logger->logPageAccess($route, $userId ?: null, $ip);

        // Pour les requêtes mutantes, loguer une action plus explicite
        if (in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $action = match ($request->getMethod()) {
                'POST' => 'create',
                'PUT', 'PATCH' => 'update',
                'DELETE' => 'delete',
                default => 'update'
            };
            $this->logger->logUserAction(strtoupper($action), [
                'route' => $route,
                'path' => $request->getPathInfo(),
                'method' => $request->getMethod(),
            ], $userId ?: null, $ip);
        }
    }

    private function extractClientIp(Request $request): ?string
    {
        $ip = $request->headers->get('X-Forwarded-For') ?: $request->getClientIp();
        if (is_array($ip)) {
            $ip = $ip[0] ?? null;
        }
        return $ip ?: null;
    }
}


