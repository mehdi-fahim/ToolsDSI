<?php

namespace App\Tests;

use App\Entity\User;
use App\Entity\Product;
use App\Service\AdminDataService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminPanelTest extends WebTestCase
{
    public function testAdminDashboardIsAccessible(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Panel Admin');
    }

    public function testEntityViewIsAccessible(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/admin/entity/user');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'User');
    }

    public function testSearchEndpoint(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/admin/entity/user/search?q=test');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
    }

    public function testExportEndpoint(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/admin/entity/user/export/csv');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'text/csv; charset=utf-8');
    }

    public function testAdminDataService(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        
        $adminDataService = $container->get(AdminDataService::class);
        
        $this->assertInstanceOf(AdminDataService::class, $adminDataService);
        
        // Test de récupération des métadonnées
        $metadata = $adminDataService->getEntityMetadata(User::class);
        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('columns', $metadata);
        $this->assertArrayHasKey('entityName', $metadata);
    }

    public function testEntityNotFound(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/admin/entity/nonexistent');

        $this->assertResponseStatusCodeSame(404);
    }
} 