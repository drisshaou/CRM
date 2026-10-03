# CRM Rodium — vue CRM tabulaire

Vue CRM type tableur : chaque ligne est un contact, chaque colonne un champ typé.
Stack : NestJS 11 · PostgreSQL 16 · React 19 + Vite 7 · Docker Compose.

## Prérequis

- Docker Engine avec **Docker Compose v2.24 ou plus récent** (`docker compose version`)
- `make` (optionnel : chaque cible a son équivalent en commande Docker ci-dessous)

## Lancement rapide (mode production)

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
| http://localhost:8080/api/health | API NestJS, via nginx : `{"status":"ok"}` |

Seul le port 8080 est exposé : nginx sert le frontend et relaie `/api` vers le backend.
La base et le backend ne sont pas accessibles depuis l'hôte. Pour changer le port, modifier
`FRONTEND_PORT` dans `srcs/.env`.

## Mode développement

Dans `srcs/.env`, commenter le bloc **prod** et décommenter le bloc **dev** :

```dotenv
FRONTEND_PORT=5173
COMPOSE_FILE=docker-compose.yml:compose.dev.yml
```

puis relancer avec la même commande : `docker compose up -d --build`.

`COMPOSE_FILE` ajoute `compose.dev.yml` par-dessus la configuration de production :
code source monté depuis l'hôte (hot reload), serveur Vite, base et Adminer exposés en local.

| URL | Service |
|---|---|
| http://localhost:5173 | Frontend Vite (hot reload) |
| http://localhost:5173/api/health | API NestJS, via le proxy Vite |
| http://127.0.0.1:8081 | Adminer (système : PostgreSQL, serveur : `postgres`) |
| 127.0.0.1:`POSTGRES_PORT` | PostgreSQL pour les outils de l'hôte (psql, DBeaver) |

Commandes utiles en développement (depuis `srcs/`) :

```bash
docker compose logs -f backend                  # follow backend logs
docker compose exec backend npm install <pkg>   # add a dependency (updates package.json and lockfile)
```

## Commandes Make (raccourcis, depuis la racine)

| Cible | Équivalent (depuis `srcs/`) | Effet |
|---|---|---|
| `make` / `make up` | `docker compose up -d --build --renew-anon-volumes` | Construit et démarre les services |
| `make down` / `make clean` | `docker compose down --remove-orphans` | Arrête et supprime les conteneurs |
| `make start` / `make stop` | `docker compose start` / `stop` | Démarre / arrête sans supprimer |
| `make config` | `docker compose config` | Affiche la configuration finale fusionnée |
| `make fclean` | `docker compose down --remove-orphans --volumes --rmi all` | Supprime aussi la base, les images et les fichiers générés |
| `make re` | `fclean` puis `up` | Repart de zéro |

> `make fclean` efface les données de la base.

## Initialisation de la base et données de démonstration

_À compléter (migrations Prisma et seed de 500 contacts)._

## Tests

_À compléter._

## Choix techniques

_À compléter._

## Fonctionnalités

_À compléter : terminées / incomplètes._

## Limites connues et améliorations prioritaires

_À compléter._

## Outils d'IA utilisés

_À compléter._

## Temps consacré

_À compléter._