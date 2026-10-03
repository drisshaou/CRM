#!/bin/sh

set -e

# npx prisma migrate deploy --schema=/app/src/prisma/schema.prisma > /dev/null 2>&1

# start with gosu node to run as non-root user
exec "$@"