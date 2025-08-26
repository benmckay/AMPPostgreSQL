FROM php:8.2-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY . /app

# Set only non-sensitive defaults; pass sensitive values at runtime via environment variables or Docker secrets
ENV DB_DSN=pgsql:host=db;port=5432;dbname=amp \
    DB_USER=postgres
# DB_PASS and JWT_SECRET should be set at runtime for security

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "php/public"]


