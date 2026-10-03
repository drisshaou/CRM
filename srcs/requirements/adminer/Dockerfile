FROM debian:bookworm-slim

ARG DEBIAN_FRONTEND=noninteractive

# PHP CLI (built-in web server) + PostgreSQL driver for Adminer
RUN apt-get update \
    && apt-get install -y --no-install-recommends php-cli php-pgsql php-mbstring \
    && rm -rf /var/lib/apt/lists/*

# Adminer picks up adminer.css automatically when it sits next to it (custom theme + logo)
COPY ./tools/adminer.php /var/www/html/index.php
COPY ./tools/adminer.css ./tools/logo.png /var/www/html/

# Read-only files are enough: run the PHP server without root
USER www-data

EXPOSE 8081
CMD ["php", "-S", "0.0.0.0:8081", "-t", "/var/www/html"]