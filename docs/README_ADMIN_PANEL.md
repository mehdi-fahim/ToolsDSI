# 🛠️ Outil DSI - Documentation du Panel Admin

## 📋 Description

Outil d’administration moderne pour Symfony 7+, pensé pour la gestion bureautique et la visualisation de données, avec une interface personnalisée Plaine Commune Habitat.

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
- PHP 8.2+
- Symfony 7.2+
- Doctrine ORM
- Base de données (Oracle, MySQL, PostgreSQL, etc.)

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

---

## 🔐 Gestion des droits utilisateurs

- Accès via le lien “Administration” dans la sidebar (visible uniquement pour l’admin)
- Saisie de l’ID utilisateur (ex : `jdupont`)
- Affichage et modification des modules/pages accessibles via cases à cocher (Document BI, Utilisateurs, Administration, etc.)
- (Simulation, à brancher sur la base réelle selon vos besoins)

---

## 🎨 Personnalisation graphique

- **Logo** : modifiable dans `public/images/logo-pch.png` (affiché en haut de la sidebar)
- **Police** : Bahnschrift (avec fallback), modifiable dans `templates/admin/base.html.twig`
- **Couleurs** : thème principal bleu nuit et orange, modifiable dans les styles inline ou CSS
- **Sidebar** : liens et copyright personnalisables dans `templates/admin/base.html.twig`

---

## 🛡️ Sécurité & bonnes pratiques

- Authentification simple (compte admin en dur : ID `PCH`, mot de passe `Ulis93200`)
- Les pages sensibles (Administration, Débloquer MDP) sont accessibles uniquement à l’admin
- Pour un usage en production, brancher la gestion des droits et des utilisateurs sur une base réelle et renforcer la sécurité (voir recommandations dans le code)

---

## 📂 Voir aussi

- [Ajout d’une nouvelle page/module : guide pas à pas](README_AJOUT_PAGE.md) 