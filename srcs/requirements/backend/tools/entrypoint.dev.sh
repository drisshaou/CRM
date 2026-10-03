#!/bin/sh

set -e

# echo "[entrypoint] database not found. Running migrations to create it..."
# npx prisma migrate dev --schema=/app/src/prisma/schema.prisma --name dev-init # --skip-seed --skip-generate
# npx prisma migrate reset --schema=/app/src/prisma/schema.prisma --force


exec "$@"