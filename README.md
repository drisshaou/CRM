# CRM Rodium — vue CRM tabulaire

Vue CRM type tableur sur une seule page : chaque ligne est un contact, chaque colonne un champ typé
(texte, nombre, date, téléphone). Tri, filtres et pagination sont appliqués côté serveur, sur toutes
les données.

**Stack** : NestJS 11 · PostgreSQL 16 · Prisma 6 · Zod 4 · React 19 · Vite 7 · TanStack Query 5 ·
CSS Modules · Docker Compose · nginx.

## Prérequis

- Docker Engine avec **Docker Compose v2.24 ou plus récent** (`docker compose version`)
- `make` (optionnel : chaque cible a son équivalent en commande Docker ci-dessous)

## Lancement (mode production)

```bash
cd srcs
cp .env.example .env        # Windows (PowerShell) : copy .env.example .env
docker compose up -d --build
```

Ouvrir **http://localhost:8080**.

Le fichier `srcs/.env` est **obligatoire**. `.env.example` contient des valeurs de démonstration
fonctionnelles, sans aucun secret réel.

| URL | Service |
|---|---|
| http://localhost:8080 | Application (nginx) |
| http://localhost:8080/api/health | API NestJS via nginx : `{"status":"ok","db":"ok"}` |

Seul le port 8080 est exposé : nginx sert le frontend compilé et relaie `/api` vers le backend.
La base et le backend ne sont pas accessibles depuis l'hôte. Pour changer de port, modifier
`FRONTEND_PORT` dans `srcs/.env`.

## Initialisation de la base et données de démonstration

Rien à faire : au démarrage, le conteneur backend exécute dans l'ordre

1. `prisma migrate deploy` : applique les migrations versionnées dans `prisma/migrations` ;
2. le **seed** (`node dist/seed`) : crée les 5 colonnes par défaut (Nom, Entreprise, Téléphone, Date,
   Score) et **500 contacts fictifs**, dans une seule transaction ;
3. l'application.

Le seed est **idempotent** : il ne fait rien si des colonnes existent déjà. Les colonnes par défaut
sont de simples lignes de la table `columns`, traitées comme n'importe quelle colonne.

```bash
make seed      # = docker compose exec backend node dist/seed  (depuis srcs/)
make re        # base vide, puis migrations + seed au redémarrage
```

## Mode développement

Dans `srcs/.env`, commenter le bloc **prod** et décommenter le bloc **dev** :

```dotenv
FRONTEND_PORT=5173
COMPOSE_FILE=docker-compose.yml:compose.dev.yml
```

puis relancer : `docker compose up -d --build`.

`COMPOSE_FILE` ajoute `compose.dev.yml` par-dessus la configuration de production : code source monté
depuis l'hôte (hot reload Nest et Vite), base et Adminer exposés en local.

| URL | Service |
|---|---|
| http://localhost:5173 | Frontend Vite (hot reload) |
| http://localhost:5173/api/health | API via le proxy Vite |
| http://127.0.0.1:8081 | Adminer (système PostgreSQL, serveur `postgres`) |
| 127.0.0.1:`POSTGRES_PORT` | PostgreSQL pour les outils de l'hôte |

## Commandes Make (raccourcis, depuis la racine)

| Cible | Équivalent (depuis `srcs/`) | Effet |
|---|---|---|
| `make` / `make up` | `docker compose up -d --build --renew-anon-volumes` | Construit et démarre |
| `make down` / `make clean` | `docker compose down --remove-orphans` | Arrête et supprime les conteneurs |
| `make start` / `make stop` | `docker compose start` / `stop` | Démarre / arrête sans supprimer |
| `make seed` | `docker compose exec backend node dist/seed` | Relance le seed (idempotent) |
| `make config` | `docker compose config` | Configuration finale fusionnée |
| `make fclean` | `docker compose down --remove-orphans --volumes --rmi all` | Supprime aussi la base et les images |
| `make re` | `fclean` puis `up` | Repart de zéro |

## Tests

Pas de tests automatisés à ce stade (voir « Améliorations prioritaires »). Vérifications manuelles
effectuées pendant le développement :

```bash
curl -s localhost:8080/api/columns
curl -s 'localhost:8080/api/contacts?limit=3'
curl -s -o /dev/null -w '%{http_code}\n' 'localhost:8080/api/contacts?limit=500'   # 400 (validation Zod)
```

Les requêtes SQL du tri, des filtres et de la mise à jour JSONB ont été exécutées sur PostgreSQL 16
pendant le développement (tri par nombre, date, texte, valeurs vides en fin de tri, filtres combinés,
caractères `%` et `_`, téléphones saisis au format national).

## Fonctionnalités

| Fonctionnalité | État |
|---|---|
| Grille de contacts, une seule page | ✅ |
| Scroll infini (chargement par pages de 50 côté serveur) | ✅ |
| Tri par colonne (une à la fois), côté serveur | ✅ |
| Filtres par colonne selon le type, combinés en ET, côté serveur | ✅ |
| Types texte, nombre, date, téléphone : affichage, édition, validation, tri, filtre | ✅ |
| Création, modification inline, suppression de contacts | ✅ |
| Persistance des contacts et des valeurs | ✅ |
| Seed idempotent de 500 contacts | ✅ |
| Colonnes dynamiques : ajouter, renommer, supprimer | ❌ non réalisé |
| Réordonner les colonnes (et persister l'ordre) | ❌ non réalisé (l'ordre est stocké, pas modifiable) |

## Choix techniques

**Modèle de données — JSONB indexé par l'id des colonnes.**
`columns(id uuid, name, type enum, position)` et `contacts(id, data jsonb)`, avec
`data = { "<columnId>": valeur }`. Une seule ligne par contact, donc une seule requête par page.
Les clés étant des UUID et non des noms, **renommer une colonne ne touche aucune donnée**.
Alternatives écartées : EAV (une jointure par colonne triée ou filtrée) et EAV typé (plus de tables
et de jointures pour un gain inutile à cette échelle).

**SQL brut isolé et paramétré.** Prisma ne sait ni trier ou filtrer sur une clé JSON avec un cast,
ni modifier une seule clé JSON. Ces requêtes sont regroupées dans un seul fichier,
`backend/app/src/contacts/contacts.query.ts`, construit avec `Prisma.sql` : chaque valeur est un
paramètre (`$1`, `$2`…). Les seuls fragments SQL variables (casts `::numeric`/`::date`,
opérateurs, `ASC`/`DESC`) viennent de constantes du code, choisies d'après le type lu en base.

**Le type d'une colonne vient toujours de la base**, jamais de la requête HTTP. Zod valide la forme
des entrées ; `contacts.values.ts` valide et normalise chaque valeur selon le type de sa colonne
(dates réelles `AAAA-MM-JJ`, nombres finis, téléphones normalisés en `+33XXXXXXXXX`). JSONB ne type
pas les clés : la cohérence est garantie à l'écriture.

**Tri stable et pagination par offset.** `ORDER BY <expression typée> NULLS LAST, id ASC` : l'id
départage les égalités, les cellules vides restent en fin de liste dans les deux sens. L'API lit
`limit + 1` lignes pour savoir s'il reste une page, sans `COUNT(*)`.

**Front.** TanStack Query gère le cache serveur : `useInfiniteQuery` pour le scroll infini, avec
tri et filtres dans la clé de requête (les changer repart de la page 0). Après une modification,
la réponse de l'API est écrite directement dans le cache au lieu de recharger : **une ligne éditée
ne saute pas** dans une colonne triée. Grille construite à la main (`<table>`), sans bibliothèque de
grille.

**Infrastructure.** Images Debian (`node:22-bookworm-slim`), multi-stage, utilisateur non-root.
Une seule origine : nginx (prod) et le proxy Vite (dev) relaient `/api`, donc pas de CORS et des
chemins relatifs côté front. `docker-compose.yml` décrit la production (images autonomes, aucun
volume de code) ; `compose.dev.yml` ajoute les montages et outils de dev, sélectionné par
`COMPOSE_FILE` dans `.env`.

## Limites connues

- **Pagination par offset** : une création ou suppression pendant le scroll peut provoquer un doublon
  ou un saut de ligne jusqu'au prochain tri ou filtre. Un curseur serait plus juste, mais complexe
  avec un tri dynamique typé.
- **Un contact créé apparaît en haut** même s'il ne correspond pas aux filtres actifs, jusqu'au
  rechargement de la liste.
- Filtres texte en `ILIKE '%…%'` **sans index** : suffisant pour quelques milliers de lignes.
- **Téléphones français uniquement** (normalisation maison, sans bibliothèque).
- Pas de virtualisation : toutes les lignes chargées restent dans le DOM.
- Tri et filtres ne sont pas conservés au rechargement de la page.
- Le seed considère la base initialisée dès qu'une colonne existe.
- `npm audit` signale une vulnérabilité `deepmerge-ts` via `@prisma/config` : elle ne concerne que la
  CLI Prisma avec une configuration statique, non exploitable ici. Le correctif proposé
  (`npm audit fix --force`) rétrograde Prisma : écarté.

## Améliorations prioritaires

1. **Colonnes dynamiques** : `POST/PATCH/DELETE /api/columns` (la suppression retire aussi la clé dans
   tous les contacts, `data - id`, dans une transaction), menu d'en-tête et bouton « + colonne ».
   Le type d'une colonne resterait non modifiable pour garantir la cohérence des valeurs existantes.
2. **Réordonnancement** : `PUT /api/columns/order` (positions mises à jour dans une transaction),
   glisser-déposer HTML5 natif sur les en-têtes.
3. **Tests** : tests unitaires de `contacts.values.ts` et `contacts.query.ts`, tests e2e de l'API
   sur une base de test.
4. Pagination par curseur, virtualisation, tri et filtres dans l'URL.

## Organisation du dépôt

```
Makefile                   raccourcis (optionnels)
srcs/
  docker-compose.yml       production
  compose.dev.yml          surcouche de développement
  .env.example             valeurs de démonstration
  requirements/
    backend/   Dockerfile, tools/entrypoint.{dev,prod}.sh, app/ (NestJS, Prisma)
    frontend/  Dockerfile, conf/nginx.conf, app/ (React, Vite)
    adminer/   Dockerfile (dev uniquement)
```

API (`/api`) : `GET /health`, `GET /columns`, `GET /contacts?limit&offset&sort&dir&filters`,
`POST /contacts`, `PATCH /contacts/:id`, `DELETE /contacts/:id`.

## Outils d'IA utilisés

**Claude (Anthropic)** utilisé comme binôme : discussion des choix d'architecture, plan par étapes,
revue de mes fichiers (Docker, Makefile), génération de code que j'ai relu, testé et adapté, et
explication des concepts NestJS, React et PostgreSQL que je découvrais.

Propositions corrigées ou rejetées en cours de route, par exemple :
- affirmation que le sujet imposait l'image `bookworm-slim` : fausse, c'était mon propre choix ;
- suppression de fichiers du scaffold Nest sans en expliquer les dépendances : a cassé la compilation ;
- commande `git checkout … 2>/dev/null || true` qui masquait l'échec dans le conteneur ;
- affirmation qu'un utilisateur non-root ne peut pas ouvrir un port < 1024 dans un conteneur : fausse
  depuis Docker 20.10.

## Temps consacré

**À compléter** : environ 7 h, hors vidéo. Une part importante a porté sur l'infrastructure Docker
(modes dev et prod, utilisateurs non-root, une seule origine) et sur la prise en main de NestJS,
React et PostgreSQL, que je n'avais jamais utilisés.