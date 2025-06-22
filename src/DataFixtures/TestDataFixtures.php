<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Product;
use App\Entity\EditionBureautique;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class TestDataFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        // Créer des utilisateurs de test
        for ($i = 0; $i < 50; $i++) {
            $user = new User();
            $user->setEmail($faker->email());
            $user->setFirstName($faker->firstName());
            $user->setLastName($faker->lastName());
            $user->setCreatedAt($faker->dateTimeBetween('-1 year', 'now'));
            $user->setIsActive($faker->boolean(80)); // 80% de chance d'être actif

            $manager->persist($user);
        }

        // Créer des produits de test
        $productNames = [
            'Ordinateur portable', 'Smartphone', 'Tablette', 'Écran 4K', 'Clavier mécanique',
            'Souris gaming', 'Casque audio', 'Webcam HD', 'Disque dur externe', 'Clé USB',
            'Chargeur sans fil', 'Support téléphone', 'Câble HDMI', 'Adaptateur USB-C',
            'Haut-parleur Bluetooth', 'Microphone USB', 'Scanner document', 'Imprimante laser',
            'Routeur WiFi', 'Switch réseau'
        ];

        for ($i = 0; $i < 100; $i++) {
            $product = new Product();
            $product->setName($faker->randomElement($productNames) . ' ' . $faker->word());
            $product->setDescription($faker->paragraph(3));
            $product->setPrice($faker->randomFloat(2, 10, 2000));
            $product->setStock($faker->numberBetween(0, 500));
            $product->setCreatedAt($faker->dateTimeBetween('-6 months', 'now'));
            $product->setIsAvailable($faker->boolean(90)); // 90% de chance d'être disponible

            $manager->persist($product);
        }

        // Créer des éditions bureautiques de test
        $editionsUsers = [
            [
                'nom' => 'Liste des utilisateurs actifs',
                'nomDocument' => 'users_actifs.pdf',
                'description' => 'Rapport détaillé de tous les utilisateurs actifs du système avec leurs informations de contact et date de création.',
                'champs' => 'ID, Email, Prénom, Nom, Date de création, Statut',
                'requete' => 'SELECT id, email, first_name, last_name, created_at, is_active FROM users WHERE is_active = 1 ORDER BY created_at DESC',
                'categorie' => 'Users'
            ],
            [
                'nom' => 'Statistiques utilisateurs par mois',
                'nomDocument' => 'stats_users_mensuel.pdf',
                'description' => 'Analyse mensuelle des nouveaux utilisateurs inscrits avec graphiques et tendances.',
                'champs' => 'Mois, Nombre d\'inscriptions, Utilisateurs actifs, Taux de conversion',
                'requete' => 'SELECT DATE_FORMAT(created_at, "%Y-%m") as mois, COUNT(*) as inscriptions, SUM(is_active) as actifs FROM users GROUP BY DATE_FORMAT(created_at, "%Y-%m") ORDER BY mois DESC',
                'categorie' => 'Users'
            ],
            [
                'nom' => 'Utilisateurs inactifs',
                'nomDocument' => 'users_inactifs.pdf',
                'description' => 'Liste des utilisateurs inactifs depuis plus de 30 jours pour nettoyage de la base.',
                'champs' => 'ID, Email, Nom complet, Dernière connexion, Jours d\'inactivité',
                'requete' => 'SELECT id, email, CONCAT(first_name, " ", last_name) as nom_complet, created_at, DATEDIFF(NOW(), created_at) as jours_inactif FROM users WHERE is_active = 0 AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)',
                'categorie' => 'Users'
            ],
            [
                'nom' => 'Répartition géographique des utilisateurs',
                'nomDocument' => 'users_geo.pdf',
                'description' => 'Analyse de la répartition géographique des utilisateurs basée sur leur adresse email.',
                'champs' => 'Domaine email, Nombre d\'utilisateurs, Pourcentage',
                'requete' => 'SELECT SUBSTRING_INDEX(email, "@", -1) as domaine, COUNT(*) as nombre, ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM users), 2) as pourcentage FROM users GROUP BY domaine ORDER BY nombre DESC',
                'categorie' => 'Users'
            ]
        ];

        $editionsProducts = [
            [
                'nom' => 'Catalogue produits complet',
                'nomDocument' => 'catalogue_produits.pdf',
                'description' => 'Catalogue complet de tous les produits disponibles avec descriptions détaillées et prix.',
                'champs' => 'ID, Nom, Description, Prix, Stock, Disponibilité',
                'requete' => 'SELECT id, name, description, price, stock, is_available FROM products WHERE is_available = 1 ORDER BY name ASC',
                'categorie' => 'Products'
            ],
            [
                'nom' => 'Produits en rupture de stock',
                'nomDocument' => 'rupture_stock.pdf',
                'description' => 'Liste des produits en rupture de stock nécessitant une réapprovisionnement urgent.',
                'champs' => 'ID, Nom, Prix, Stock actuel, Date de création',
                'requete' => 'SELECT id, name, price, stock, created_at FROM products WHERE stock = 0 AND is_available = 1 ORDER BY created_at DESC',
                'categorie' => 'Products'
            ],
            [
                'nom' => 'Analyse des prix par catégorie',
                'nomDocument' => 'analyse_prix.pdf',
                'description' => 'Analyse statistique des prix par catégorie de produits avec moyennes et écarts.',
                'champs' => 'Catégorie, Nombre de produits, Prix moyen, Prix min, Prix max',
                'requete' => 'SELECT CASE WHEN name LIKE "%ordinateur%" THEN "Informatique" WHEN name LIKE "%smartphone%" THEN "Mobile" WHEN name LIKE "%audio%" THEN "Audio" ELSE "Autre" END as categorie, COUNT(*) as nombre, AVG(price) as prix_moyen, MIN(price) as prix_min, MAX(price) as prix_max FROM products GROUP BY categorie',
                'categorie' => 'Products'
            ],
            [
                'nom' => 'Produits les plus chers',
                'nomDocument' => 'produits_chers.pdf',
                'description' => 'Liste des 20 produits les plus chers du catalogue pour analyse des gammes premium.',
                'champs' => 'Rang, Nom, Prix, Stock, Disponibilité',
                'requete' => 'SELECT ROW_NUMBER() OVER (ORDER BY price DESC) as rang, name, price, stock, is_available FROM products ORDER BY price DESC LIMIT 20',
                'categorie' => 'Products'
            ],
            [
                'nom' => 'Nouveaux produits du mois',
                'nomDocument' => 'nouveaux_produits.pdf',
                'description' => 'Liste des nouveaux produits ajoutés au catalogue dans le mois en cours.',
                'champs' => 'Nom, Description, Prix, Date d\'ajout',
                'requete' => 'SELECT name, description, price, created_at FROM products WHERE created_at >= DATE_FORMAT(NOW(), "%Y-%m-01") ORDER BY created_at DESC',
                'categorie' => 'Products'
            ]
        ];

        // Créer les éditions pour Users
        foreach ($editionsUsers as $edition) {
            $editionBureautique = new EditionBureautique();
            $editionBureautique->setNom($edition['nom']);
            $editionBureautique->setNomDocument($edition['nomDocument']);
            $editionBureautique->setDescription($edition['description']);
            $editionBureautique->setChamps($edition['champs']);
            $editionBureautique->setRequete($edition['requete']);
            $editionBureautique->setCategorie($edition['categorie']);
            $editionBureautique->setCreatedAt($faker->dateTimeBetween('-3 months', 'now'));
            $editionBureautique->setIsActive(true);

            $manager->persist($editionBureautique);
        }

        // Créer les éditions pour Products
        foreach ($editionsProducts as $edition) {
            $editionBureautique = new EditionBureautique();
            $editionBureautique->setNom($edition['nom']);
            $editionBureautique->setNomDocument($edition['nomDocument']);
            $editionBureautique->setDescription($edition['description']);
            $editionBureautique->setChamps($edition['champs']);
            $editionBureautique->setRequete($edition['requete']);
            $editionBureautique->setCategorie($edition['categorie']);
            $editionBureautique->setCreatedAt($faker->dateTimeBetween('-3 months', 'now'));
            $editionBureautique->setIsActive(true);

            $manager->persist($editionBureautique);
        }

        $manager->flush();
    }
} 