#!/bin/sh
set -e

# Apply committed migrations not yet in the database (never resets data)
npx prisma migrate deploy
# Insert default columns and demo contacts (skipped if data exists)
node dist/seed

exec "$@"