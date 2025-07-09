#!/bin/bash

# Chemin vers PHP (à adapter si besoin)
PHP_BIN="/c/xampp/php/php.exe"

# Nom du fichier Composer local
COMPOSER="./composer.phar"

# Vérifie que composer.phar existe
if [ ! -f "$COMPOSER" ]; then
  echo "❌ composer.phar introuvable dans le dossier courant."
  echo "Téléchargement de composer.phar..."
  curl -sS https://getcomposer.org/composer.phar -o composer.phar || {
    echo "❌ Échec du téléchargement de Composer."
    exit 1
  }
fi

# Exécution de la mise à jour
echo "🚀 Mise à jour des dépendances Symfony avec Composer..."
"$PHP_BIN" "$COMPOSER" update

echo "✅ Terminé."