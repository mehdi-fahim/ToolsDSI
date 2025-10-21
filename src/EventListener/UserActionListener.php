<?php

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 1000)]
#[AsEventListener(event: KernelEvents::RESPONSE, priority: -1000)]
class UserActionListener
{
    private ?string $requestId = null;
    private ?float $startTime = null;

    public function __construct(
        private LoggerInterface $userActionsLogger
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $this->requestId = uniqid('req_', true);
        $this->startTime = microtime(true);

        // Ignorer les requêtes statiques et les profiler
        $path = $request->getPathInfo();
        if (str_contains($path, '/_wdt/') || 
            str_contains($path, '/_profiler/') ||
            str_contains($path, '/favicon.ico') ||
            str_contains($path, '/css/') ||
            str_contains($path, '/js/') ||
            str_contains($path, '/images/')) {
            return;
        }

        $session = $request->getSession();
        $userId = $session->get('user_id', 'ANONYMOUS');
        $userName = $session->get('user_nom', '') . ' ' . $session->get('user_prenom', '');
        $userName = trim($userName) ?: 'Utilisateur inconnu';

        $actionData = [
            'request_id' => $this->requestId,
            'user_id' => $userId,
            'user_name' => $userName,
            'ip_address' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'method' => $request->getMethod(),
            'url' => $request->getUri(),
            'path' => $path,
            'controller' => $request->attributes->get('_controller'),
            'route' => $request->attributes->get('_route'),
            'timestamp' => date('Y-m-d H:i:s'),
            'action_type' => $this->determineActionType($request)
        ];

        // Log de l'action
        $this->userActionsLogger->info("User Action: {$actionData['action_type']}", $actionData);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->requestId) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();
        
        // Ignorer les requêtes statiques
        $path = $request->getPathInfo();
        if (str_contains($path, '/_wdt/') || 
            str_contains($path, '/_profiler/') ||
            str_contains($path, '/favicon.ico') ||
            str_contains($path, '/css/') ||
            str_contains($path, '/js/') ||
            str_contains($path, '/images/')) {
            return;
        }

        $session = $request->getSession();
        $userId = $session->get('user_id', 'ANONYMOUS');
        $duration = $this->startTime ? round((microtime(true) - $this->startTime) * 1000, 2) : 0;

        $responseData = [
            'request_id' => $this->requestId,
            'user_id' => $userId,
            'status_code' => $response->getStatusCode(),
            'duration_ms' => $duration,
            'timestamp' => date('Y-m-d H:i:s'),
            'action_type' => 'RESPONSE'
        ];

        // Log de la réponse
        $this->userActionsLogger->info("Response: {$response->getStatusCode()}", $responseData);
    }

    private function determineActionType($request): string
    {
        $path = $request->getPathInfo();
        $method = $request->getMethod();

        // Actions de connexion
        if (str_contains($path, '/login')) {
            return $method === 'POST' ? 'LOGIN_ATTEMPT' : 'LOGIN_PAGE_ACCESS';
        }

        if (str_contains($path, '/logout')) {
            return 'LOGOUT';
        }

        // Actions d'administration
        if (str_contains($path, '/admin/')) {
            if ($method === 'POST') {
                return 'ADMIN_ACTION';
            }
            return 'ADMIN_PAGE_ACCESS';
        }

        // Actions de dashboard
        if (str_contains($path, '/dashboard')) {
            return 'DASHBOARD_ACCESS';
        }

        // Actions de fichiers
        if (str_contains($path, '/files')) {
            return $method === 'POST' ? 'FILE_UPLOAD' : 'FILE_ACCESS';
        }

        if (str_contains($path, '/upload')) {
            return 'UPLOAD_PAGE_ACCESS';
        }

        // Actions par défaut
        if ($method === 'POST') {
            return 'FORM_SUBMIT';
        }

        return 'PAGE_ACCESS';
    }
}
