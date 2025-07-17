# 🛠️ Outil DSI - Panel Admin Symfony

## 📚 Documentation complète

La documentation détaillée du projet est organisée dans le dossier `docs/` :

- [**Caractéristique du site**](docs/README_DESC_SITE.md) : Guide complet d’utilisation et d’architecture du panel admin.
- [**Ajout d'un page**](docs/README_AJOUT_PAGE.md) : Tutoriel pas à pas pour ajouter une nouvelle page/module, la rendre visible dans la navigation et la page d’administration, et gérer les droits d’accès.

Seul ce fichier `README.md` principal reste à la racine du projet pour un affichage rapide sur GitHub ou dans l’explorateur de projet.

---

Ce projet est un outil d’administration moderne pour Symfony, pensé pour la gestion bureautique et la visualisation de données, avec une interface personnalisée Plaine Commune Habitat.

---

## Installation

Voir instructions détaillées plus bas (prérequis, installation, configuration BDD, etc.).

---

## 1. Prérequis

Avant de commencer, assurez-vous d'avoir les éléments suivants installés sur votre machine :

- **PHP 8.1+**
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
  - Le projet est compatible avec Oracle

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

**Que fait le script ?**
- ✅ Vérifie les prérequis (PHP, Composer).
- 📦 Installe les dépendances (`composer install`).
- ⚙️ Vous aide à configurer votre base de données de manière interactive (Oracle, MySQL, etc.).
- 🧱 Crée la base de données et applique les migrations.
- 📊 Propose de charger les données de test (fixtures).
- 🔒 Configure les permissions des dossiers.
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

---

## 3. Démarrer l'application

Une fois l'installation terminée, vous pouvez lancer le serveur web Symfony.

**Sans Symfony CLI :**
Utilisez le serveur web intégré de PHP.
```bash
php -S 127.0.0.1:8000 -t public
```

Le serveur sera accessible à l'adresse **http://127.0.0.1:8000/admin**.

---

## 5. Dépannage

-   **`"The service ... has a dependency on a non-existent service"`** :  
    Videz le cache : `php bin/console cache:clear` et vérifiez que votre `DATABASE_URL` est correcte.

-   **Erreur de connexion à la BDD** :  
    Vérifiez vos identifiants dans `DATABASE_URL` et que votre serveur de base de données est bien démarré.

-   **`"Function name must be an identifier"` dans un template Twig** :  
    Vérifiez que vous n'appelez pas de méthodes dynamiquement. Accédez aux propriétés directement (ex: `entity.nom`) ou via des `if/else` comme dans `templates/admin/entity_view.html.twig`. 