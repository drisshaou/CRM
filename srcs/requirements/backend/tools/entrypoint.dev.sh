#!/bin/sh
set -e

# node_modules comes from a fresh anonymous volume: regenerate the client from the schema
npx prisma generate
# Apply committed migrations not yet in the database (never resets data)
npx prisma migrate deploy
# Compile once so dist/seed.js exists, then seed (skipped if data exists)
npm run build
npm run seed

exec "$@"