<?php
namespace App\Tests\Controller;

use PHPUnit\Framework\TestCase;
use App\Controller\AdminController;
use App\Service\EditionBureautiqueOracleService;
use App\Service\UtilisateurOracleService;
use App\Service\MotDePasseOracleService;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class AdminControllerTest extends TestCase
{
    public function testIsAuthenticatedAcceptsZeroUserId(): void
    {
        $editionService = $this->createMock(EditionBureautiqueOracleService::class);
        $utilisateurService = $this->createMock(UtilisateurOracleService::class);
        $motDePasseService = $this->createMock(MotDePasseOracleService::class);

        $controller = new AdminController($editionService, $utilisateurService, $motDePasseService);

        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->willReturnMap([
            ['is_admin', null, true],
            ['user_id', null, '0'],
        ]);

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('isAuthenticated');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($controller, $session));
    }
}
