# API REST — Annuaire des gardes et astreintes

API REST (Symfony 7.4) qui expose l'annuaire du personnel de garde, de ses numéros de garde et des numéros d'urgence.
La lecture est publique ; la création, la modification et la suppression exigent un JWT obtenu en se connectant avec
un compte Active Directory. Chaque écriture est journalisée (table `trace`).

## Prérequis

- PHP ≥ 8.2 avec les extensions `ldap`, `openssl`, `ctype`, `iconv`, `pdo_sqlsrv` et `sqlsrv`
- [Composer](https://getcomposer.org/)
- SQL Server (2022 en dev via Docker, voir plus bas) et le pilote ODBC « Microsoft ODBC Driver 18 »
- Accès à l'Active Directory pour la connexion (`immdom.local`)

## Installation

```bash
composer install
```

### 1. Configuration (`.env.local`)

Le fichier `.env` est ignoré par git : les valeurs ci-dessous sont à mettre dans un fichier `.env.local`
(ignoré aussi, ne jamais le commiter).

| Variable | Rôle | Exemple |
|---|---|---|
| `APP_ENV` | Environnement | `dev` |
| `APP_SECRET` | Secret Symfony (chaîne aléatoire) | `4adf91…` |
| `DATABASE_URL` | Connexion SQL Server | `pdo-sqlsrv://utilisateur:motdepasse@127.0.0.1:1433/ANNUAIRE_IMM_DEV?charset=utf8&TrustServerCertificate=true` |
| `JWT_SECRET_KEY` / `JWT_PUBLIC_KEY` | Chemins des clés JWT | `%kernel.project_dir%/config/jwt/private.pem` / `public.pem` |
| `JWT_PASSPHRASE` | Phrase secrète de la clé privée | (aléatoire) |
| `CORS_ALLOW_ORIGIN` | Origines autorisées (regex) | `^https?://(localhost\|127\.0\.0\.1)(:[0-9]+)?$` |
| `LDAP_HOST` / `LDAP_PORT` / `LDAP_ENCRYPTION` | Serveur AD | `immdom.local` / `389` / `none` (`ssl` ou `tls` en prod) |
| `LDAP_BASE_DN` | Où chercher les utilisateurs | `OU=Comptes,OU=IMM,DC=immdom,DC=local` |
| `LDAP_SEARCH_DN` / `LDAP_SEARCH_PASSWORD` | Compte technique de recherche | |
| `LDAP_USER_QUERY` | Filtre de recherche de l'utilisateur | `(sAMAccountName={username})` |
| `LDAP_DIRECTORY_DN` / `LDAP_DIRECTORY_SCOPE` | Unité d'organisation lue pour l'annuaire du personnel (comptes actifs ; `one` = directement dans l'OU, `sub` = avec ses sous-OU) | `OU=Utilisateurs,OU=Comptes,OU=IMM,DC=immdom,DC=local` / `one` |
| `LDAP_ADMIN_GROUP_DN` | DN du groupe AD autorisé à écrire. **Vide = tout compte AD valide est admin** | `CN=GSG_APP_ANNUAIRE_ADMIN,OU=Applications,OU=Groupes,OU=IMM,DC=immdom,DC=local` |

### 2. Clés JWT

```bash
php bin/console lexik:jwt:generate-keypair
```

Sous Windows, si OpenSSL ne trouve pas sa configuration, définir `OPENSSL_CONF` (ex. `C:/php/extras/ssl/openssl.cnf`).

### 3. Base de données

Une instance SQL Server de développement est fournie :

```bash
docker compose --env-file .env.local up -d database   # SQL Server sur 127.0.0.1:1433
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

**Le mot de passe `sa` du conteneur doit être le même que celui de `DATABASE_URL`.** Ajouter dans `.env.local` :

```
MSSQL_SA_PASSWORD=le_meme_mot_de_passe_que_dans_DATABASE_URL
```

Docker Compose ne lit pas `.env.local` tout seul : d'où l'option `--env-file .env.local` (ou définir la variable dans le shell avant de lancer la commande). Sans elle, le conteneur démarre avec le mot de passe par défaut de `compose.yaml`, reste « unhealthy » et l'API échoue avec `Login failed for user 'sa'`.

Le mot de passe `sa` n'est pris en compte qu'à la **première** création du volume `database_data`. Si le volume existe déjà avec un autre mot de passe, il faut le supprimer (`docker compose down -v`, **efface les données**) ou utiliser l'ancien mot de passe.

#### Utilisateur applicatif dédié (optionnel)

Par défaut l'API se connecte avec `sa`. Pour utiliser un login dédié (`app`, rôle `db_owner`), exécuter `docker/mssql/init.sql`. Il crée la base et le login, et prend ses valeurs en variables `sqlcmd` (`DB_NAME`, `APP_PASSWORD`) :

```bash
docker compose --env-file .env.local exec database /opt/mssql-tools18/bin/sqlcmd -C -S localhost -U sa -P "$MSSQL_SA_PASSWORD" -v DB_NAME=ANNUAIRE_IMM_DEV APP_PASSWORD="<mot_de_passe_app>" -i /docker/init.sql
```

Puis adapter `DATABASE_URL` dans `.env.local` : `pdo-sqlsrv://app:<mot_de_passe_app>@127.0.0.1:1433/ANNUAIRE_IMM_DEV?charset=utf8&TrustServerCertificate=true`.

### 4. Lancer l'API

```bash
symfony serve            # ou : php -S 127.0.0.1:8000 -t public
```

L'API répond alors sur `http://127.0.0.1:8000/api`.

## Authentification

1. `POST /api/login` avec `{"username": "...", "password": "..."}` (identifiant et mot de passe AD).
2. La réponse contient `{"token": "<jwt>"}` (valable 1 heure).
3. Envoyer ensuite `Authorization: Bearer <jwt>` sur les routes protégées.

| Code | Signification |
|---|---|
| 401 | Identifiant inconnu, mot de passe incorrect ou JWT absent / invalide |
| 429 | Trop de tentatives de connexion (5 par IP et identifiant, 30 par IP, sur 15 minutes) |
| 503 | Annuaire LDAP indisponible |

## Routes

`GET` = public. Écritures et `/api/traces` = JWT admin. Les `{id}` sont des entiers.

| Ressource | Routes |
|---|---|
| Numéros d'urgence (`libelle`, `numero`) | `/api/numeros-urgence` (mêmes 5 routes) |
| Personnel de garde (`username` = identifiant AD ; libellé, service et métier lus dans l'AD) | `GET /api/personnel`, `GET /api/personnel/{id}`, `POST /api/personnel`, `PUT\|PATCH /api/personnel/{id}`, `DELETE /api/personnel/{id}` ; `GET /api/personnel/services` et `/metiers` listent les valeurs en usage, pour les filtres |
| Annuaire du personnel, **lu dans l'AD, lecture seule** | `GET /api/personnes` (recherche, voir plus bas), `GET /api/personnes/services`, `GET /api/personnes/metiers`, `GET /api/personnes/{identifiant AD}` |
| Numéros de garde (`numero`, `type`, `personnelDeGardeId`) | `/api/numeros-garde` (mêmes 5 routes) |
| Gardes (`personnelDeGardeId`, `dateDebut`, `dateFin` en `AAAA-MM-JJ`, bornes incluses) | `/api/gardes` (mêmes 5 routes) ; `GET /api/gardes?date=AAAA-MM-JJ` ne renvoie que les gardes couvrant ce jour (la « garde en cours » de l'interface) |
| Recherche dans l'annuaire AD, pour choisir un personnel de garde (JWT admin, `q` de 2 à 50 caractères) | `GET /api/ad/recherche?q=dupont` : 20 comptes au plus, `[{ username, prenom, nom, email, libelle }]` |
| Traces (journal d'audit, lecture seule) | `GET /api/traces`, `GET /api/traces/{id}` |
| Connexion | `POST /api/login` |

- `PUT` et `PATCH` sont équivalents : seuls les champs envoyés sont modifiés.
- Les relations se donnent par identifiant (`personnelDeGardeId`) ; un id inconnu renvoie 400.
- À la création d'un personnel de garde, on envoie l'identifiant AD : un identifiant inconnu renvoie 422, un identifiant déjà enregistré 422, un AD injoignable 503. Le libellé n'est pas modifiable à la main.
- Supprimer un personnel supprime ses numéros de garde et ses gardes.
- Le détail de chaque route (accès, champs, codes de retour) est dans le commentaire au-dessus de la route,
  dans `src/Controller/Api/`.

### Recherche du personnel — `GET /api/personnel`

| Paramètre | Description |
|---|---|
| `q` | Mots recherchés (50 caractères max), insensible à la casse, dans l'identifiant, le libellé, le service et le métier du personnel de garde. Pour `/api/personnes` (annuaire AD, insensible aussi aux accents) : identifiant, nom, prénom, e-mail, service, poste et numéros |
| `serviceId`, `metierId` | Filtres sur la valeur exacte du service et du métier (libellés de l'AD ; listes dans `/api/personnel/services` et `/metiers`, ou `/api/personnes/services` et `/metiers` pour l'annuaire) |
| `page` | Numéro de page (défaut 1) |
| `limit` | Taille de page (défaut 20, max 100) |

La pagination est renvoyée dans les en-têtes `X-Total-Count`, `X-Page`, `X-Per-Page`, `X-Total-Pages`.
`GET /api/traces` est paginé de la même façon (défaut 50, max 200), du plus récent au plus ancien. Filtres facultatifs : `username` (contient, insensible à la casse), `action` (`Création`, `Modification` ou `Suppression`), `from` et `to` (`AAAA-MM-JJ`, bornes incluses) ; `400` si une valeur est invalide.

### Format des erreurs

```json
{ "message": "Ressource introuvable." }
```

Erreur de validation (422) :

```json
{ "message": "Données invalides.", "errors": { "libelle": ["This value should not be blank."] } }
```

Autres codes : 400 (JSON ou type invalide, relation inconnue), 401, 404, 409, 503 (AD injoignable), 500 (message générique hors mode debug).

## Tests

```bash
php bin/console doctrine:database:create --env=test   # une seule fois : crée ANNUAIRE_IMM_DEV_test
php bin/console doctrine:migrations:migrate --env=test
php bin/phpunit
```

Les tests utilisent une base séparée (suffixe `_test`), vidée avant chaque test, et un JWT généré localement ;
la connexion LDAP est testée avec un faux annuaire, sans réseau.

## Modèle de données

```mermaid
erDiagram
    PERSONNEL_DE_GARDE ||--o{ NUMERO_GARDE : "joignable par"
    PERSONNEL_DE_GARDE ||--o{ GARDE : "assure"

    PERSONNEL_DE_GARDE {
        int id PK
        string username "compte AD, unique"
        string libelle "copie de l'AD"
        string service "copie de l'AD (department)"
        string metier "copie de l'AD (title)"
    }
    NUMERO_GARDE {
        int id PK
        string numero
        string type "Fixe, DECT…"
        int personnel_de_garde_id FK
    }
    GARDE {
        int id PK
        date date_debut
        date date_fin "incluse"
        int personnel_de_garde_id FK
    }
    NUMERO_URGENCE {
        int id PK
        string libelle
        string numero
    }
    TRACE {
        int id PK
        string username "compte AD"
        datetime date_action
        string action_realise
    }
```

- L'annuaire du personnel n'a pas de table : il est lu en direct dans l'AD (voir « Annuaire du personnel et AD »). `PERSONNEL_DE_GARDE` est la seule population enregistrée en base, pour la page de garde.
- Supprimer un personnel de garde supprime ses numéros et ses gardes.
- `NUMERO_URGENCE` et `TRACE` sont autonomes. `TRACE` est le journal des actions d'administration ; le compte y est conservé en texte, car les comptes viennent de l'AD.
- **Tout vient de l'AD.** `PERSONNEL_DE_GARDE` ne stocke que l'identifiant du compte (`username`) ; le libellé « Prénom Nom », le service (`department`) et le métier (`title`) sont lus dans l'AD à la création, puis recopiés en base pour que la recherche, le tri et les filtres restent en SQL (voir `app:ldap:sync`). Il n'y a plus de tables `service` ni `metier`.
- Écarts avec le MCD de base : les entités `SERVICE` et `METIER` (et `username` sur `METIER`) n'existent plus, le service et le métier étant des libellés lus dans l'AD ; `GARDE` a été ajoutée ; l'annuaire du personnel vient de l'AD ; `SERVICE.numero_service` n'est pas implémenté.

## Annuaire du personnel et AD

**Annuaire du personnel (`/api/personnes`).** Il n'est pas saisi : il est lu dans l'AD, avec le compte technique `LDAP_SEARCH_DN`, sur les comptes **actifs** de l'unité d'organisation `LDAP_DIRECTORY_DN` (comptes désactivés exclus). Pour chaque compte : nom, prénom, e-mail, service (`department`), poste (`title`) et **tous les numéros, quel que soit leur type** (`telephoneNumber`, `mobile`, `ipPhone`, `pager`, fax et leurs variantes « autres » ; un même numéro n'apparaît qu'une fois ; `homePhone`, numéro personnel, n'est jamais lu). Les modifier = modifier l'AD.

L'AD est lu une fois puis gardé **en cache une heure** (`DirectoryCatalog`) : la recherche, les filtres, le tri et la pagination se font en mémoire, sans requête LDAP à chaque frappe (environ 0,4 s pour charger 1 700 comptes, puis quelques millisecondes). Les changements de l'AD apparaissent au plus tard après une heure, ou tout de suite après `app:ldap:sync`.

> L'annuaire est **public** (lecture sans connexion), comme avant : noms, e-mails et numéros de ~1 700 personnes sont donc visibles de tout poste qui atteint l'application. À restreindre (JWT) si l'application sort du réseau interne.

**Personnel de garde.** Seule population enregistrée en base : on saisit un identifiant AD (avec recherche dans l'annuaire AD), le libellé « Prénom Nom », le service et le métier sont lus dans l'AD et recopiés. Pour que les changements faits ensuite dans l'AD (mariage, etc.) s'y retrouvent :

```bash
php bin/console app:ldap:sync --dry-run   # affiche ce qui changerait
php bin/console app:ldap:sync             # met à jour les libellés et rafraîchit l'annuaire du personnel
```

Un compte introuvable dans l'AD (supprimé ou renommé) est signalé mais **jamais supprimé** de la base. À planifier (tâche planifiée Windows ou cron) en production, par exemple chaque nuit.

La migration `Version20261001170000` conserve les services et métiers déjà saisis (copiés en texte sur `personnel_de_garde`) avant de supprimer les tables `service` et `metier` ; ils sont remplacés par ceux de l'AD au prochain `app:ldap:sync`. Les migrations `Version20261001122453` (identifiant AD sur `personnel_de_garde`) et `Version20261001150000` (suppression de la table `personne`) refusent de s'appliquer si ces tables contiennent des lignes : les vider d'abord, puis ressaisir le personnel de garde par son identifiant AD.

## Structure

```
src/Controller/Api/   Contrôleurs CRUD (un par ressource) + AbstractApiController
src/Entity/           Entités Doctrine et groupes de sérialisation
src/Repository/       Requêtes (recherche du personnel, traces paginées)
src/Security/         LdapAuthenticator (login), JwtUserProvider, AdminUser
src/Service/          TraceLogger (journal d'audit transactionnel)
src/EventSubscriber/  ExceptionSubscriber (réponses d'erreur JSON)
migrations/           Schéma de base de données
tests/                Tests fonctionnels (Api/) et unitaires (Security/)
```

## Avant une mise en production

- Vérifier `LDAP_ADMIN_GROUP_DN` (groupe `GSG_APP_ANNUAIRE_ADMIN`) sur l'environnement cible : s'il est vide, tout compte AD valide obtient les droits d'écriture.
- Passer LDAP en `ssl`/`tls` : en `none`, les mots de passe circulent en clair.
- Définir `APP_ENV=prod`, un `APP_SECRET` et un `JWT_PASSPHRASE` propres, et `CORS_ALLOW_ORIGIN` pour l'URL du front.
- Utiliser un compte SQL Server à privilèges limités (pas `sa`) et retirer `TrustServerCertificate` si le certificat est valide.
