# To use a Docker Hub mirror, build with:
#   docker build --build-arg PHP_IMAGE=your-mirror/php:8.3-cli ...
ARG PHP_IMAGE=php:8.4-cli

FROM composer:latest AS composer

FROM ${PHP_IMAGE}

RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    git \
    unzip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /app

ENTRYPOINT ["php"]
