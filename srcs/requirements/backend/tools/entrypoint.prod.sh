#!/bin/sh
set -e

# Apply committed migrations not yet in the database (never resets data)
npx prisma migrate deploy

exec "$@"