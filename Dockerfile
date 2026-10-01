# syntax=docker/dockerfile:1

FROM serversideup/php:8.4-fpm-nginx AS base

# compose.yaml runs the stack on the host network, where the image's defaults
# would put nginx and unauthenticated FastCGI on every interface. Bind both to
# loopback; the grep fails the build if the nginx template ever stops matching.
# Delete this block when the stack moves to bridged networking.
USER root
RUN printf '[www]\nlisten = 127.0.0.1:9000\n' > /usr/local/etc/php-fpm.d/zz-loopback.conf \
    && sed -i -e '/listen \[::\]/d' -e 's/listen \${NGINX_HTTP_PORT}/listen 127.0.0.1:${NGINX_HTTP_PORT}/' \
        /etc/nginx/site-opts.d/http.conf.template \
    && grep -q 'listen 127.0.0.1:' /etc/nginx/site-opts.d/http.conf.template
USER www-data

ENV PHP_OPCACHE_ENABLE=1


FROM base AS build

USER root
COPY --from=node:22-slim /usr/local/bin/node /usr/local/bin/
COPY --from=node:22-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction

COPY package.json package-lock.json .npmrc ./
RUN npm ci

COPY . .

# The Wayfinder Vite plugin calls `php artisan` during the build, which is why
# the assets are built here and not in a plain Node stage.
ENV VITE_APP_NAME=Inkspire
RUN composer dump-autoload --optimize --no-dev \
    && npm run build \
    && rm -rf node_modules


FROM base

COPY --from=build --chown=www-data:www-data /var/www/html /var/www/html
