# 📄 Ajouter une nouvelle page/module à Outil DSI (de A à Z)

Ce guide explique comment ajouter une nouvelle page (ou module) à l’outil DSI, pour qu’elle apparaisse dans la sidebar, dans la page d’administration (gestion des droits), et soit accessible selon les droits de l’utilisateur.

---

## 1. Créer le contrôleur et la route

Dans `src/Controller/AdminController.php` (ou un autre contrôleur), ajoute une méthode pour ta nouvelle page :

```php
// Exemple : page Statistiques
#[Route('/admin/statistiques', name: 'admin_statistiques', methods: ['GET'])]
public function statistiques(Request $request, SessionInterface $session): Response
{
    // Vérification des droits (voir étape 6)
    if (!$this->hasModuleAccess($session, 'Statistiques')) {
        return $this->redirectToRoute('login');
    }
    return $this->render('admin/statistiques.html.twig');
}
```

---

## 2. Créer le template Twig

Dans `templates/admin/`, crée le fichier `statistiques.html.twig` :

```twig
{% extends 'admin/base.html.twig' %}

{% block title %}Statistiques - Outil DSI{% endblock %}

{% block content %}
    <div style="background: #fff; border-radius: 18px; box-shadow: 0 4px 24px rgba(35,41,70,0.07); padding: 56px 56px; max-width: 900px; margin: 0 auto;">
        <h2 style="font-size: 1.5rem; color: #232946; font-weight: bold; margin-bottom: 18px;">Statistiques</h2>
        <p>Contenu de la page Statistiques...</p>
    </div>
{% endblock %}
```

---

## 3. Ajouter le module dans la méthode getAllModules()

Dans `AdminController.php`, ajoute le nom de ta page/module dans la méthode `getAllModules()` :

```php
private function getAllModules(): array
{
    return [
        'Accueil',
        'Document BI',
        'Utilisateurs',
        'Débloquer MDP',
        'Administration',
        'Statistiques', // <-- Ajout ici
    ];
}
```

---

## 4. Ajouter le lien dans la sidebar (optionnel)

Dans `templates/admin/base.html.twig`, ajoute un lien dans la sidebar si tu veux que la page soit accessible directement :

```twig
<li><a href="{{ path('admin_statistiques') }}" style="display: flex; align-items: center; gap: 12px; color: #fff; text-decoration: none; padding: 14px 32px; font-size: 1.1rem; transition: background 0.2s;">📊 <span>Statistiques</span></a></li>
```
Ajoute ce bloc à l’endroit souhaité dans la liste des liens (et éventuellement une icône).

---

## 5. Gestion de l’accès via la page d’administration

La page d’administration affichera automatiquement une case à cocher pour le module “Statistiques” (ou le nom que tu as mis dans `getAllModules()`).

- Pour chaque utilisateur, tu peux cocher/décocher l’accès à ce module.
- La logique de sauvegarde réelle des droits est à brancher selon tes besoins (actuellement simulation).

---

## 6. Restriction d’accès selon les droits

Dans ton contrôleur, ajoute une méthode utilitaire pour vérifier l’accès à un module :

```php
private function hasModuleAccess(SessionInterface $session, string $module): bool
{
    // Simulation : à remplacer par ta logique réelle
    $userId = $session->get('user_id');
    $fakeAccess = [
        'jdupont' => ['Document BI', 'Utilisateurs', 'Administration', 'Statistiques'],
        'smartin' => ['Document BI'],
    ];
    return isset($fakeAccess[$userId]) && in_array($module, $fakeAccess[$userId]);
}
```

Utilise cette méthode dans chaque page à accès restreint :

```php
if (!$this->hasModuleAccess($session, 'Statistiques')) {
    return $this->redirectToRoute('login');
}
```

---

## 7. Conseils de personnalisation

- **Nom du module** : doit être identique dans `getAllModules()` et dans la vérification d’accès.
- **Icône** : ajoute une icône dans la sidebar pour plus de clarté.
- **Ordre** : place le module où tu veux dans la liste pour organiser la navigation.
- **Droits** : adapte la logique de gestion des droits selon ta base réelle.

---

## 8. Exemple complet

Supposons que tu veux ajouter une page “Statistiques” :

1. Ajoute la route et la méthode dans le contrôleur.
2. Crée le template Twig.
3. Ajoute “Statistiques” dans `getAllModules()`.
4. Ajoute le lien dans la sidebar (optionnel).
5. La page d’administration affichera la case à cocher automatiquement.
6. Utilise la méthode de vérification d’accès dans le contrôleur.

---

Tu peux maintenant ajouter autant de pages/modules que tu veux, tout en gardant une gestion centralisée et dynamique des droits d’accès dans Outil DSI ! 