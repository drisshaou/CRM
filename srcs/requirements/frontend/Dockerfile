# ----------------------------
#        DEPS STAGE
# ----------------------------
FROM node:22-bookworm-slim AS deps

ARG DEBIAN_FRONTEND=noninteractive

# curl: used by the compose healthcheck in dev
RUN apt-get update && apt-get install -y --no-install-recommends procps curl \
    && rm -rf /var/lib/apt/lists/*

# Unprivileged "node" user (UID 1000): files created through the dev
# bind mount belong to the host user, not root
WORKDIR /app
RUN chown node:node /app
USER node

COPY --chown=node:node app/package*.json ./
RUN npm ci

COPY --chown=node:node app .


# ----------------------------
#        DEV STAGE
# ----------------------------
FROM deps AS dev

# package.json "dev" script: vite --host 0.0.0.0 --port 5173 --strictPort
CMD ["npm", "run", "dev"]


# ----------------------------
#        BUILD STAGE
# ----------------------------
FROM deps AS build

RUN npm run build


# ----------------------------
#        PROD STAGE (last = default target)
# ----------------------------
FROM nginx:stable-trixie AS prod

COPY conf/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html

CMD ["nginx", "-g", "daemon off;"]