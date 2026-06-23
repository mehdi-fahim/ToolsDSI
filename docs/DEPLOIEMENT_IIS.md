# Déploiement ToolsDSI sur IIS (Windows Server 2019)

## Prérequis sur le serveur `172.22.2.106`

1. **IIS** avec :
   - CGI / FastCGI
   - **URL Rewrite** ([télécharger](https://www.iis.net/downloads/microsoft/url-rewrite))
2. **PHP 8.1+** (NTS x64 recommandé pour IIS)
3. **Oracle Instant Client** (ex. `C:\instantclient_19`) dans le **PATH** système
4. **Composer** (pour l’installation initiale)
5. Accès réseau Oracle : `172.22.0.30:1521` (VPN/réseau PCH)

---

## 1. Installer PHP pour IIS

1. Copier PHP dans `C:\php` (ou autre dossier fixe)
2. Dans `php.ini` **avant** `extension=oci8_19` :

```ini
oci8.privileged_connect=1
extension=oci8_19
extension=curl
extension=mbstring
extension=openssl
extension=intl
extension=zip
```

3. IIS → **Gestionnaire des modules** → **Mapper un module** → FastCGI → `C:\php\php-cgi.exe`
4. Associer l’extension `.php` au handler FastCGI

Vérifier :

```powershell
C:\php\php.exe -m | findstr oci8
C:\php\php.exe -i | findstr privileged_connect
```

---

## 2. Déployer l’application

Exemple de chemin sur le serveur :

```
C:\inetpub\ToolsDSI\
```

Copier le projet (Git, ZIP, etc.) **sans** `var/cache` du poste de dev si possible.

Sur le serveur :

```powershell
cd C:\inetpub\ToolsDSI
composer install --no-dev --optimize-autoloader
C:\php\php.exe bin\console importmap:install
```

---

## 3. Fichiers à configurer (sur le serveur)

| Fichier | Action |
|---------|--------|
| `.env.local` | **À créer** (ne pas committer) — secrets et prod |
| `public\web.config` | Déjà fourni — réécriture vers `index.php` |
| `C:\php\php.ini` | OCI8 + `privileged_connect` |
| IIS (site) | Racine physique = dossier **`public`** |

### Exemple `.env.local` sur le serveur

```env
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=generer_une_cle_aleatoire_32_caracteres_minimum

DATABASE_URL="oci8://OPULISE:OPULISE@172.22.0.30:1521/OPULISE"
DATABASE_URL_PROD="oci8://OPULISE:OPULISE@172.22.0.30:1521/OPULISE"
DATABASE_URL_PREPROD="oci8://OPULISPP:OPULISPP@172.22.0.9:1521/OPULISPP"
DATABASE_URL_TEST="oci8://OPULISE:OPULISE@172.21.41.168:1521/OPULIST"
```

Générer `APP_SECRET` :

```powershell
C:\php\php.exe -r "echo bin2hex(random_bytes(16));"
```

---

## 4. Créer le site IIS

1. **Gestionnaire IIS** → Sites → **Ajouter un site**
2. Nom : `ToolsDSI`
3. **Chemin physique** : `C:\inetpub\ToolsDSI\public` (important : le dossier `public`, pas la racine du projet)
4. Liaison :
   - Port **8000** : `http`, IP `172.22.2.106`, port `8000`  
     → URL : `http://172.22.2.106:8000`
   - Ou port **80** : `http://172.22.2.106` (standard)

5. Pool d’applications : **No Managed Code** (PHP via FastCGI)

---

## 5. Permissions

Donner à `IIS_IUSRS` (et au compte du pool si différent) :

- **Lecture** sur tout le projet
- **Modification** sur :
  - `var\`
  - `var\cache\`
  - `var\log\`

```powershell
icacls "C:\inetpub\ToolsDSI\var" /grant "IIS_IUSRS:(OI)(CI)M" /T
```

---

## 6. Cache Symfony (prod)

```powershell
cd C:\inetpub\ToolsDSI
C:\php\php.exe bin\console cache:clear --env=prod --no-debug
C:\php\php.exe bin\console cache:warmup --env=prod --no-debug
C:\php\php.exe bin\console importmap:install
```

---

## 7. Pare-feu Windows

Si port 8000 :

```powershell
New-NetFirewallRule -DisplayName "ToolsDSI HTTP 8000" -Direction Inbound -Protocol TCP -LocalPort 8000 -Action Allow
```

---

## 8. Tests

- `http://172.22.2.106:8000/login`
- Connexion utilisateur
- Page **Système** (tables locker — nécessite SYS + `privileged_connect`)

---

## Fichiers du projet : rien à « réécrire » pour l’IP

L’IP `172.22.2.106` est celle du **serveur web**, pas d’Oracle.  
Les URLs de base de données restent dans `.env.local` (`172.22.0.30`, etc.).

Le code `SystemOracleService` (connexion SYS pour les verrous) pointe déjà vers `172.22.0.30` — à modifier **uniquement** si l’instance Oracle change, pas selon l’IP IIS.

---

## Dépannage rapide

| Symptôme | Piste |
|----------|--------|
| 404 sur `/login` | URL Rewrite installé ? `web.config` dans `public` ? |
| 500 générique | `var\log\prod.log`, activer temporairement `APP_DEBUG=1` en local uniquement |
| 500 asset / importmap / stimulus | `php bin\console importmap:install` puis vider le cache prod |
| 500 « Bad file descriptor » / écriture log | Monolog : ne pas utiliser `php://stderr` sous IIS (voir `config/packages/monolog.yaml`) |
| Connexion PCH OK, autre utilisateur 500 | Tester Oracle **via le navigateur** (pas seulement en CLI) ; lire `var\log\prod.log` |
| Oracle / OCI8 | `php.ini`, Instant Client dans PATH **système**, redémarrer IIS (`iisreset`) |
| Tables locker | `oci8.privileged_connect=1` **avant** `extension=oci8` |
| Permission denied sur cache | Droits `var\` pour IIS_IUSRS |

Redémarrer IIS après changement PHP :

```powershell
iisreset
```
