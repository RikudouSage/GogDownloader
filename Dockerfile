FROM php:8.2-alpine

ENV CONFIG_DIRECTORY=/Configs
ENV DOWNLOAD_DIRECTORY=/Downloads

RUN mkdir -p $CONFIG_DIRECTORY $DOWNLOAD_DIRECTORY \
    && chmod 0777 $CONFIG_DIRECTORY \
    && apk add --no-cache libxml2-dev sqlite-dev \
    && docker-php-ext-install simplexml pcntl pdo_sqlite \
    && echo 'memory_limit = -1' >> /usr/local/etc/php/conf.d/docker-php-memory-limit.ini

COPY gog-downloader /app/gog-downloader
RUN chmod +x /app/gog-downloader
WORKDIR /app

ENTRYPOINT ["/app/gog-downloader"]
