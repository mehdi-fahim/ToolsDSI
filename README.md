# 🛠️ Outil DSI - Panel Admin Symfony

## 📚 Documentation complète

La documentation détaillée du projet est organisée dans le dossier `docs/` :

- [**README_ADMIN_PANEL.md**](docs/README_ADMIN_PANEL.md) : Guide complet d’utilisation, d’installation et d’architecture du panel admin.
- [**README_AJOUT_PAGE.md**](docs/README_AJOUT_PAGE.md) : Tutoriel pas à pas pour ajouter une nouvelle page/module, la rendre visible dans la navigation et la page d’administration, et gérer les droits d’accès.

Seul ce fichier `README.md` principal reste à la racine du projet pour un affichage rapide sur GitHub ou dans l’explorateur de projet.

---

Ce projet est un outil d’administration moderne pour Symfony, pensé pour la gestion bureautique et la visualisation de données, avec une interface personnalisée Plaine Commune Habitat.

---

## Fonctionnalités principales

- **Sidebar moderne** avec logo Plaine Commune Habitat, navigation Accueil, Document BI, Utilisateurs, Administration
- **Dashboard d’accueil** : description du site, fonctionnalités illustrées
- **Tableaux dynamiques** pour Document BI (données Oracle) et Utilisateurs (nom, prénom, email, poste, modules)
- **Page de connexion** design (logo, police Bahnschrift, responsive)
- **Page Administration** : saisie d’un ID utilisateur, gestion des droits d’accès par module via cases à cocher
- **Design responsive** et personnalisable (couleurs, police, logo)

---

## Installation

Voir instructions détaillées plus bas (prérequis, installation, configuration BDD, etc.).

---

## Navigation & Pages

- **Accueil** : Présentation de l’outil, fonctionnalités principales
- **Document BI** : Consultation des éditions bureautiques (requête Oracle), export CSV/JSON
- **Utilisateurs** : Liste des utilisateurs (nom, prénom, email, poste, modules)
- **Administration** : Saisie d’un ID utilisateur, gestion des droits d’accès (checkbox par module)
- **Connexion** : Page de login moderne (logo, Bahnschrift)

---

## Personnalisation graphique

- **Logo** : modifiable dans `public/images/logo-pch.png` (affiché en haut de la sidebar)
- **Police** : Bahnschrift (avec fallback), modifiable dans `templates/admin/base.html.twig`
- **Couleurs** : thème principal bleu nuit et orange, modifiable dans les styles inline ou CSS
- **Sidebar** : liens et copyright personnalisables dans `templates/admin/base.html.twig`

---

## Gestion des droits utilisateurs

- Accès via le lien “Administration” dans la sidebar
- Saisie de l’ID utilisateur (ex : `jdupont`)
- Affichage et modification des modules/pages accessibles via cases à cocher (Document BI, Utilisateurs, Administration)
- (Simulation, à brancher sur la base réelle selon vos besoins)

---

## Installation & utilisation

(Instructions d’installation, configuration, démarrage, ajout d’entités, dépannage : voir sections existantes ci-dessous)

---

## 1. Prérequis

Avant de commencer, assurez-vous d'avoir les éléments suivants installés sur votre machine :

- **PHP 8.2+**
  - Vérifiez votre version : `php -v`
  - Extensions PHP requises : `ctype`, `iconv`, `pdo`, `pdo_sqlite` (pour le mode démo), et l'extension de votre base de données (ex: `oci8` pour Oracle, `pdo_mysql` pour MySQL).

- **Composer**
  - Outil de gestion de dépendances pour PHP.
  - Vérifiez votre installation : `composer --version`
  - [Télécharger Composer](https://getcomposer.org/)

- **Symfony CLI (Recommandé)**
  - Outil pour faciliter le développement Symfony.
  - Vérifiez votre installation : `symfony --version`
  - [Installer Symfony CLI](https://symfony.com/download)

- **Git**
  - Système de contrôle de version pour cloner le projet.
  - Vérifiez votre installation : `git --version`

- **Base de données**
  - Le projet est compatible avec Oracle, MySQL, PostgreSQL, et SQLite.

---

## 2. Installation

Vous avez deux méthodes pour installer le projet :

- **Méthode 1 (Recommandée)** : Utiliser le script d'installation interactif.
- **Méthode 2 (Manuelle)** : Suivre les étapes pas à pas.

---

### Méthode 1 : Script d'installation (Recommandé)

Ce script vous guidera à travers toutes les étapes de configuration.

**Quand le lancer ?** Juste après avoir cloné le projet.

1.  **Cloner le projet**
    ```bash
    git clone <URL_DU_PROJET>
    cd <NOM_DU_DOSSIER>
    ```
    *Remplacez `<URL_DU_PROJET>` et `<NOM_DU_DOSSIER>` par les vôtres.*

2.  **Rendre le script exécutable (pour Linux/macOS)**
    ```bash
    chmod +x install_admin_panel.sh
    ```

3.  **Lancer le script**
    ```bash
    # Sur Linux/macOS
    ./install_admin_panel.sh

    # Sur Windows avec Git Bash ou WSL
    bash install_admin_panel.sh
    ```

**Que fait le script ?**
- ✅ Vérifie les prérequis (PHP, Composer).
- 📦 Installe les dépendances (`composer install`).
- ⚙️ Vous aide à configurer votre base de données de manière interactive (Oracle, MySQL, etc.).
- 🧱 Crée la base de données et applique les migrations.
- 📊 Propose de charger les données de test (fixtures).
- 🔒 Configure les permissions des dossiers.

Une fois le script terminé, vous pouvez passer directement à la section **[3. Démarrer l'application](#3-démarrer-lapplication)**.

---

### Méthode 2 : Manuelle (Pas à pas)

Suivez ces étapes si vous préférez une configuration manuelle ou si le script ne fonctionne pas sur votre environnement.

**a. Cloner le projet**
```bash
git clone <URL_DU_PROJET>
cd <NOM_DU_DOSSIER>
```

**b. Installer les dépendances**
```bash
composer install
```

**c. Configurer l'environnement**
Ouvrez le fichier `.env` et modifiez la ligne `DATABASE_URL`.
*Si le fichier n'existe pas, copiez `.env.example` en `.env`.*

- **Pour SQLite (par défaut, rien à faire)** :
  ```env
  DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"
  ```
- **Pour MySQL** :
  ```env
  DATABASE_URL="mysql://user:password@127.0.0.1:3306/dbname?serverVersion=8.0"
  ```
- **Pour PostgreSQL** :
  ```env
  DATABASE_URL="postgresql://user:password@127.0.0.1:5432/dbname?serverVersion=15"
  ```
- **Pour Oracle** :
  ```env
  DATABASE_URL="oci8://user:password@host:port/service_name?charset=AL32UTF8"
  ```

**d. Mettre en place la base de données**
1.  **Créer la base de données** (si elle n'existe pas) :
    ```bash
    php bin/console doctrine:database:create
    ```
2.  **Appliquer les migrations** (créer les tables) :
    ```bash
    php bin/console doctrine:migrations:migrate
    ```

**e. Charger les données de test (optionnel)**
```bash
php bin/console doctrine:fixtures:load
```

---

## 3. Démarrer l'application

Une fois l'installation terminée, vous pouvez lancer le serveur web Symfony.

**Avec Symfony CLI (recommandé) :**
```bash
symfony server:start
```

**Sans Symfony CLI :**
Utilisez le serveur web intégré de PHP.
```bash
php -S 127.0.0.1:8000 -t public
```

Le serveur sera accessible à l'adresse **http://127.0.0.1:8000**.

Pour accéder au panel admin, rendez-vous sur :
**http://127.0.0.1:8000/admin**

---

## 4. Ajouter une nouvelle entité au panel

Pour que votre propre entité apparaisse dans le panel, suivez ces 3 étapes :

1.  **Créer votre entité Doctrine** (ex: `src/Entity/MonEntite.php`).

2.  **Ajouter l'entité au dashboard** :
    Dans `src/Controller/AdminController.php`, ajoutez votre entité au tableau `$availableEntities`.
    ```php
    // src/Controller/AdminController.php
    
    // ...
    use App\Entity\MonEntite; // Ajouter le use

    class AdminController extends AbstractController
    {
        #[Route('', name: 'admin_dashboard', methods: ['GET'])]
        public function dashboard(): Response
        {
            $availableEntities = [
                // ... (autres entités)
                'MonEntite' => [
                    'class' => MonEntite::class,
                    'label' => 'Mes Entités',
                    'icon' => '⭐'
                ]
            ];
            // ...
        }
    }
    ```

3.  **Ajouter l'entité au mapping de la route** :
    Dans `src/Controller/AdminController.php`, mettez à jour la méthode `getEntityClass()` pour reconnaître le nom de votre entité dans l'URL.
    ```php
    // src/Controller/AdminController.php
    
    // ...
    private function getEntityClass(string $entityName): ?string
    {
        $entityMap = [
            // ... (autres mappings)
            'monentite' => MonEntite::class, // Ajouter cette ligne
        ];

        return $entityMap[strtolower($entityName)] ?? null;
    }
    ```

4.  **Vider le cache** (recommandé) :
    ```bash
    php bin/console cache:clear
    ```

Votre nouvelle entité est maintenant visible et gérable depuis le panel admin !

---

## 5. Dépannage

-   **`"The service ... has a dependency on a non-existent service"`** :  
    Videz le cache : `php bin/console cache:clear` et vérifiez que votre `DATABASE_URL` est correcte.

-   **Erreur de connexion à la BDD** :  
    Vérifiez vos identifiants dans `DATABASE_URL` et que votre serveur de base de données est bien démarré.

-   **`"Function name must be an identifier"` dans un template Twig** :  
    Vérifiez que vous n'appelez pas de méthodes dynamiquement. Accédez aux propriétés directement (ex: `entity.nom`) ou via des `if/else` comme dans `templates/admin/entity_view.html.twig`. 