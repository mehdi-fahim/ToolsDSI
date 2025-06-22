#!/bin/bash

# Script d'installation du Panel Admin Symfony
# Compatible avec Oracle 19c et autres bases de données

echo "🔧 Installation du Panel Admin Symfony"
echo "======================================"

# Couleurs pour l'affichage
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Fonction pour afficher les messages
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Vérifier si Composer est installé
if ! command -v composer &> /dev/null; then
    print_error "Composer n'est pas installé. Veuillez l'installer d'abord."
    exit 1
fi

print_status "Vérification de Composer... OK"

# Vérifier si PHP est installé
if ! command -v php &> /dev/null; then
    print_error "PHP n'est pas installé. Veuillez l'installer d'abord."
    exit 1
fi

print_status "Vérification de PHP... OK"

# Vérifier la version de PHP
PHP_VERSION=$(php -r "echo PHP_VERSION;")
PHP_MAJOR=$(echo $PHP_VERSION | cut -d. -f1)
PHP_MINOR=$(echo $PHP_VERSION | cut -d. -f2)

if [ "$PHP_MAJOR" -lt 8 ] || ([ "$PHP_MAJOR" -eq 8 ] && [ "$PHP_MINOR" -lt 2 ]); then
    print_error "PHP 8.2+ est requis. Version actuelle: $PHP_VERSION"
    exit 1
fi

print_success "Version PHP: $PHP_VERSION"

# Installation des dépendances
print_status "Installation des dépendances Composer..."
composer install

if [ $? -ne 0 ]; then
    print_error "Erreur lors de l'installation des dépendances"
    exit 1
fi

print_success "Dépendances installées avec succès"

# Vérifier si le fichier .env existe
if [ ! -f ".env" ]; then
    print_warning "Fichier .env non trouvé. Création d'un fichier .env.example..."
    cp .env.example .env 2>/dev/null || echo "DATABASE_URL=\"mysql://user:password@localhost:3306/database_name\"" > .env
fi

# Configuration de la base de données
print_status "Configuration de la base de données..."

echo ""
echo "Choisissez votre base de données :"
echo "1) Oracle 19c"
echo "2) MySQL"
echo "3) PostgreSQL"
echo "4) SQLite"
echo "5) Autre (configuration manuelle)"
echo ""

read -p "Votre choix (1-5): " DB_CHOICE

case $DB_CHOICE in
    1)
        echo ""
        print_status "Configuration Oracle 19c"
        read -p "Hostname: " ORACLE_HOST
        read -p "Port (1521): " ORACLE_PORT
        ORACLE_PORT=${ORACLE_PORT:-1521}
        read -p "Service name: " ORACLE_SERVICE
        read -p "Username: " ORACLE_USER
        read -s -p "Password: " ORACLE_PASS
        echo ""
        
        DATABASE_URL="oci8://$ORACLE_USER:$ORACLE_PASS@$ORACLE_HOST:$ORACLE_PORT/$ORACLE_SERVICE?charset=AL32UTF8"
        ;;
    2)
        echo ""
        print_status "Configuration MySQL"
        read -p "Hostname (localhost): " MYSQL_HOST
        MYSQL_HOST=${MYSQL_HOST:-localhost}
        read -p "Port (3306): " MYSQL_PORT
        MYSQL_PORT=${MYSQL_PORT:-3306}
        read -p "Database name: " MYSQL_DB
        read -p "Username: " MYSQL_USER
        read -s -p "Password: " MYSQL_PASS
        echo ""
        
        DATABASE_URL="mysql://$MYSQL_USER:$MYSQL_PASS@$MYSQL_HOST:$MYSQL_PORT/$MYSQL_DB?serverVersion=8.0"
        ;;
    3)
        echo ""
        print_status "Configuration PostgreSQL"
        read -p "Hostname (localhost): " PGSQL_HOST
        PGSQL_HOST=${PGSQL_HOST:-localhost}
        read -p "Port (5432): " PGSQL_PORT
        PGSQL_PORT=${PGSQL_PORT:-5432}
        read -p "Database name: " PGSQL_DB
        read -p "Username: " PGSQL_USER
        read -s -p "Password: " PGSQL_PASS
        echo ""
        
        DATABASE_URL="postgresql://$PGSQL_USER:$PGSQL_PASS@$PGSQL_HOST:$PGSQL_PORT/$PGSQL_DB?serverVersion=15"
        ;;
    4)
        print_status "Configuration SQLite"
        DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"
        ;;
    5)
        print_warning "Configuration manuelle requise"
        echo "Veuillez configurer manuellement votre DATABASE_URL dans le fichier .env"
        ;;
    *)
        print_error "Choix invalide"
        exit 1
        ;;
esac

# Mettre à jour le fichier .env
if [ ! -z "$DATABASE_URL" ]; then
    if [[ "$OSTYPE" == "darwin"* ]]; then
        # macOS
        sed -i '' "s|DATABASE_URL=.*|DATABASE_URL=\"$DATABASE_URL\"|" .env
    else
        # Linux
        sed -i "s|DATABASE_URL=.*|DATABASE_URL=\"$DATABASE_URL\"|" .env
    fi
    print_success "Configuration de la base de données mise à jour"
fi

# Créer les dossiers nécessaires
print_status "Création des dossiers nécessaires..."
mkdir -p var/cache
mkdir -p var/log
mkdir -p var/data

# Vider le cache
print_status "Vidage du cache..."
php bin/console cache:clear

# Créer la base de données si elle n'existe pas
print_status "Création de la base de données..."
php bin/console doctrine:database:create --if-not-exists

if [ $? -ne 0 ]; then
    print_warning "Impossible de créer la base de données. Vérifiez votre configuration."
fi

# Créer les migrations
print_status "Création des migrations..."
php bin/console make:migration

# Exécuter les migrations
print_status "Exécution des migrations..."
php bin/console doctrine:migrations:migrate --no-interaction

if [ $? -ne 0 ]; then
    print_warning "Erreur lors de l'exécution des migrations"
fi

# Charger les données de test
echo ""
read -p "Voulez-vous charger des données de test ? (y/N): " LOAD_FIXTURES

if [[ $LOAD_FIXTURES =~ ^[Yy]$ ]]; then
    print_status "Chargement des données de test..."
    php bin/console doctrine:fixtures:load --no-interaction
    
    if [ $? -eq 0 ]; then
        print_success "Données de test chargées avec succès"
    else
        print_warning "Erreur lors du chargement des données de test"
    fi
fi

# Vérifier les permissions
print_status "Configuration des permissions..."
chmod -R 755 var/
chmod -R 755 public/

# Test de connexion
print_status "Test de connexion à la base de données..."
php bin/console doctrine:query:sql "SELECT 1" > /dev/null 2>&1

if [ $? -eq 0 ]; then
    print_success "Connexion à la base de données réussie"
else
    print_warning "Problème de connexion à la base de données"
fi

echo ""
echo "🎉 Installation terminée !"
echo "=========================="
echo ""
echo "Pour démarrer le serveur de développement :"
echo "  symfony server:start"
echo ""
echo "Pour accéder au panel admin :"
echo "  http://localhost:8000/admin"
echo ""
echo "📚 Documentation complète : README_ADMIN_PANEL.md"
echo ""
print_warning "⚠️  N'oubliez pas de configurer la sécurité pour la production !"
echo "" 