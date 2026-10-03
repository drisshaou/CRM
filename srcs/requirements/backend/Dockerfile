# ----------------------------
#        DEPS STAGE
# ----------------------------
FROM node:22-bookworm-slim AS deps

ARG DEBIAN_FRONTEND=noninteractive

# openssl: required by the Prisma engine; curl: used by the compose healthcheck
RUN apt-get update && apt-get install -y --no-install-recommends openssl curl procps \
    && rm -rf /var/lib/apt/lists/*

# Work as the unprivileged "node" user (UID 1000): files created through
# the dev bind mount then belong to the host user, not root
WORKDIR /app
RUN chown node:node /app
USER node

# Exact install from the lockfile (dev + prod dependencies)
COPY --chown=node:node app/package*.json ./
RUN npm ci

COPY --chown=node:node app .


# ----------------------------
#        DEV STAGE
# ----------------------------
FROM deps AS dev

COPY --chmod=755 tools/entrypoint.dev.sh /usr/local/bin/entrypoint.sh

EXPOSE 3000
ENTRYPOINT ["entrypoint.sh"]
CMD ["npm", "run", "dev"]


# ----------------------------
#        BUILD STAGE
# ----------------------------
FROM deps AS build

# # future step: Prisma client goes into node_modules
# RUN npx prisma generate

# keep node_modules (and the generated client), drop dev deps
RUN npm run build && npm prune --omit=dev


# ----------------------------
#        PROD STAGE (last = default target)
# ----------------------------
FROM node:22-bookworm-slim AS prod

ARG DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y --no-install-recommends openssl curl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Everything comes from the build stage, already owned by node
COPY --from=build --chown=node:node /app/package.json ./
COPY --from=build --chown=node:node /app/node_modules ./node_modules
COPY --from=build --chown=node:node /app/dist ./dist
# # future step : schema + migrations
# COPY --from=build --chown=node:node /app/prisma ./prisma

COPY --chmod=755 tools/entrypoint.prod.sh /usr/local/bin/entrypoint.sh

USER node
EXPOSE 3000
ENTRYPOINT ["entrypoint.sh"]
# Node directly (no npm in between) so docker stop reaches the process
CMD ["node", "dist/main"]