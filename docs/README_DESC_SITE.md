# 🛠️ Outil DSI - Documentation du Panel Admin

## 📋 Description

Outil d’administration pour Symfony 6, pensé pour la gestion bureautique et la visualisation de données, avec une interface personnalisée Plaine Commune Habitat.

---

## ✨ Fonctionnalités principales

- Sidebar moderne avec logo, navigation Accueil, Document BI, Utilisateurs, Débloquer MDP, Administration (liens dynamiques selon droits)
- Dashboard d’accueil avec description et fonctionnalités illustrées
- Tableaux dynamiques pour Document BI (données Oracle) et Utilisateurs (nom, prénom, email, poste, modules)
- Page de connexion design (logo, police Bahnschrift, responsive)
- Page Administration : gestion des droits d’accès par utilisateur (checkbox)
- Page Débloquer MDP : modification ou réinitialisation du mot de passe d’un utilisateur
- Design responsive et personnalisable (couleurs, police, logo)

---

## 🚀 Installation

### Prérequis
- PHP 8.1
- Symfony 6.4
- Doctrine ORM
- Base de données (Oracle)
### Installation rapide
1. Cloner le projet
2. Installer les dépendances : `composer install`
3. Configurer `.env` (voir exemple pour Oracle, MySQL, etc.)
4. Créer la base de données et appliquer les migrations
5. (Optionnel) Charger les fixtures de test
6. Lancer le serveur Symfony : `symfony server:start`

---

## 🧭 Navigation & Pages

- **Accueil** : Présentation de l’outil, fonctionnalités principales
- **Document BI** : Consultation des éditions bureautiques (requête Oracle), export CSV/JSON
- **Utilisateurs** : Liste des utilisateurs (nom, prénom, email, poste, modules)
- **Débloquer MDP** : Modification/réinitialisation du mot de passe d’un utilisateur
- **Administration** : Saisie d’un ID utilisateur, gestion des droits d’accès (checkbox par module)
- **Connexion/Déconnexion** : Authentification simple (compte admin en dur)

---

## 🏗️ Structure du projet

- `src/Controller/AdminController.php` : Contrôleur principal, routes admin, gestion des droits
- `src/Service/` : Services métiers (ex : accès Oracle)
- `templates/admin/` : Templates Twig (base, dashboard, entity_view, user_access, unlock_password, etc.)
- `public/images/logo-pch.png` : Logo Plaine Commune Habitat
- `docs/` : Documentation détaillée
