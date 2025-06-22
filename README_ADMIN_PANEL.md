# 🔧 Panel Admin Symfony - Documentation

## 📋 Description

Panel admin léger et moderne pour Symfony 7+, permettant de consulter, rechercher et exporter des données d'entités Doctrine sans authentification. Compatible avec Oracle 19c et autres bases de données.

## ✨ Fonctionnalités

- **📊 Consultation des données** : Affichage tabulaire de toutes les entités Doctrine
- **🔍 Recherche instantanée** : Filtrage en temps réel des données
- **📄 Export multi-format** : Export CSV et JSON des données filtrées
- **📱 Interface responsive** : Compatible mobile et desktop
- **⚡ Performance optimisée** : Pagination et chargement asynchrone
- **🎨 Design moderne** : Interface utilisateur intuitive et esthétique
- **🔧 Configuration flexible** : Compatible avec n'importe quelle entité Doctrine

## 🚀 Installation

### 1. Prérequis

- PHP 8.2+
- Symfony 7.2+
- Doctrine ORM
- Base de données (Oracle 19c, MySQL, PostgreSQL, etc.)

### 2. Configuration de la base de données

#### Pour Oracle 19c

Ajoutez dans votre fichier `.env` :

```env
# Oracle 19c
DATABASE_URL="oci8://username:password@hostname:1521/service_name?charset=AL32UTF8"
```

#### Pour MySQL/PostgreSQL

```env
# MySQL
DATABASE_URL="mysql://username:password@hostname:3306/database_name?serverVersion=8.0"

# PostgreSQL
DATABASE_URL="postgresql://username:password@hostname:5432/database_name?serverVersion=15"
```

### 3. Installation des dépendances

```bash
composer install
```

### 4. Création des tables

```bash
# Créer les migrations
php bin/console make:migration

# Exécuter les migrations
php bin/console doctrine:migrations:migrate
```

### 5. Chargement des données de test (optionnel)

```bash
# Installer les fixtures
composer require --dev doctrine/doctrine-fixtures-bundle fakerphp/faker

# Charger les données de test
php bin/console doctrine:fixtures:load
```

## 🎯 Utilisation

### Accès au panel

1. Démarrez le serveur Symfony :
```bash
symfony server:start
```

2. Accédez au panel admin :
```
http://localhost:8000/admin
```

### Navigation

1. **Dashboard** (`/admin`) : Vue d'ensemble des entités disponibles
2. **Vue entité** (`/admin/entity/{entityName}`) : Consultation des données d'une entité
3. **Recherche** : Barre de recherche en temps réel
4. **Export** : Boutons d'export CSV/JSON

### Fonctionnalités détaillées

#### 🔍 Recherche

- **Recherche instantanée** : Tapez dans la barre de recherche pour filtrer automatiquement
- **Champs recherchables** : Automatiquement détectés (types string/text)
- **Recherche globale** : Recherche dans tous les champs textuels de l'entité

#### 📄 Export

- **Export CSV** : Format compatible Excel avec séparateur point-virgule
- **Export JSON** : Format structuré pour traitement automatisé
- **Export filtré** : Export uniquement des données visibles (après recherche)

#### 📊 Pagination

- **Navigation fluide** : Boutons première/précédente/suivante/dernière
- **Affichage intelligent** : 5 pages maximum affichées autour de la page courante
- **Compteur** : Affichage du nombre total d'enregistrements

## 🏗️ Architecture

### Structure des fichiers

```
src/
├── Controller/
│   └── AdminController.php          # Contrôleur principal
├── Service/
│   └── AdminDataService.php         # Service de gestion des données
└── Entity/
    ├── User.php                     # Entité exemple
    └── Product.php                  # Entité exemple

templates/admin/
├── base.html.twig                   # Template de base
├── dashboard.html.twig              # Dashboard principal
└── entity_view.html.twig            # Vue des données d'entité
```

### Services

#### AdminDataService

Service principal gérant :
- Récupération des données avec pagination
- Recherche dans les entités
- Export CSV/JSON
- Métadonnées des entités

#### Méthodes principales

```php
// Récupérer toutes les données
getAllData(string $entityClass, int $page = 1, int $limit = 50): array

// Rechercher des données
searchData(string $entityClass, string $searchTerm, int $page = 1, int $limit = 50): array

// Récupérer les métadonnées
getEntityMetadata(string $entityClass): array

// Exporter en CSV
exportToCsv(string $entityClass, array $data): string

// Exporter en JSON
exportToJson(string $entityClass, array $data): string
```

### Contrôleur

#### AdminController

Gère les routes :
- `GET /admin` : Dashboard principal
- `GET /admin/entity/{entityName}` : Vue d'une entité
- `GET /admin/entity/{entityName}/search` : Recherche AJAX
- `GET /admin/entity/{entityName}/export/{format}` : Export

## 🔧 Configuration

### Ajouter une nouvelle entité

1. **Créer l'entité** dans `src/Entity/`
2. **Ajouter au contrôleur** dans `AdminController::dashboard()`

```php
$availableEntities = [
    'User' => [
        'class' => User::class,
        'label' => 'Utilisateurs',
        'icon' => '👥'
    ],
    'Product' => [
        'class' => Product::class,
        'label' => 'Produits',
        'icon' => '📦'
    ],
    // Ajouter votre nouvelle entité ici
    'YourEntity' => [
        'class' => YourEntity::class,
        'label' => 'Votre Entité',
        'icon' => '🎯'
    ]
];
```

3. **Ajouter le mapping** dans `AdminController::getEntityClass()`

```php
private function getEntityClass(string $entityName): ?string
{
    $entityMap = [
        'user' => User::class,
        'users' => User::class,
        'product' => Product::class,
        'products' => Product::class,
        // Ajouter votre mapping
        'yourentity' => YourEntity::class,
        'yourentities' => YourEntity::class,
    ];

    return $entityMap[strtolower($entityName)] ?? null;
}
```

### Personnalisation de l'interface

#### Modifier le style

Éditez le fichier `templates/admin/base.html.twig` pour personnaliser :
- Couleurs et thème
- Typographie
- Layout responsive
- Animations

#### Modifier les templates

- `dashboard.html.twig` : Page d'accueil
- `entity_view.html.twig` : Affichage des données
- `base.html.twig` : Template de base

## 🛡️ Sécurité

⚠️ **Important** : Ce panel n'a pas d'authentification et est destiné à un usage interne uniquement.

### Recommandations de sécurité

1. **Restreindre l'accès** par IP dans votre serveur web
2. **Utiliser HTTPS** en production
3. **Configurer un reverse proxy** avec authentification
4. **Limiter les permissions** de la base de données

### Exemple de restriction IP (Apache)

```apache
<Location "/admin">
    Order Deny,Allow
    Deny from all
    Allow from 192.168.1.0/24
    Allow from 10.0.0.0/8
</Location>
```

### Exemple de restriction IP (Nginx)

```nginx
location /admin {
    allow 192.168.1.0/24;
    allow 10.0.0.0/8;
    deny all;
}
```

## 🚀 Déploiement

### Environnement de production

1. **Optimiser les performances** :
```bash
# Vider le cache
php bin/console cache:clear --env=prod

# Optimiser les autoloaders
composer install --optimize-autoloader --no-dev
```

2. **Configurer la base de données** :
```env
# .env.local
DATABASE_URL="votre_url_de_production"
```

3. **Configurer le serveur web** :
- Apache : Activer mod_rewrite
- Nginx : Configurer le proxy vers Symfony

### Variables d'environnement

```env
# Base de données
DATABASE_URL="votre_url_de_base_de_donnees"

# Environnement
APP_ENV=prod
APP_DEBUG=false

# Cache
CACHE_DRIVER=redis
```

## 🐛 Dépannage

### Problèmes courants

#### Erreur de connexion à la base de données

```bash
# Vérifier la configuration
php bin/console doctrine:database:create --if-not-exists

# Tester la connexion
php bin/console doctrine:query:sql "SELECT 1"
```

#### Erreur de permissions

```bash
# Vérifier les permissions des dossiers
chmod -R 755 var/
chmod -R 755 public/
```

#### Problème d'export

- Vérifier que l'extension `php_oci8` est installée pour Oracle
- Vérifier les permissions d'écriture du dossier temporaire

### Logs

```bash
# Voir les logs Symfony
tail -f var/log/dev.log

# Voir les logs d'erreur
tail -f var/log/error.log
```

## 📈 Performance

### Optimisations recommandées

1. **Index de base de données** :
```sql
-- Pour les champs recherchés fréquemment
CREATE INDEX idx_user_email ON users(email);
CREATE INDEX idx_user_name ON users(first_name, last_name);
```

2. **Cache Doctrine** :
```yaml
# config/packages/doctrine.yaml
doctrine:
    orm:
        query_cache_driver:
            type: pool
            pool: doctrine.system_cache_pool
```

3. **Pagination optimisée** :
- Limite par défaut : 50 enregistrements
- Ajustable via le paramètre `limit`

## 🤝 Contribution

### Ajouter une fonctionnalité

1. Fork le projet
2. Créer une branche feature
3. Implémenter la fonctionnalité
4. Ajouter les tests
5. Créer une pull request

### Standards de code

- PSR-12 pour le PHP
- ESLint pour le JavaScript
- Documentation en français

## 📄 Licence

Ce projet est sous licence MIT. Voir le fichier LICENSE pour plus de détails.

## 🆘 Support

Pour toute question ou problème :

1. Consulter cette documentation
2. Vérifier les logs d'erreur
3. Créer une issue sur GitHub
4. Contacter l'équipe de développement

---

**Panel Admin Symfony** - Développé avec ❤️ pour une gestion efficace des données 