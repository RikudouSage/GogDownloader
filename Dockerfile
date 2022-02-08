FROM php:8.1-alpine

ENV CONFIG_DIRECTORY=/Configs
ENV DOWNLOAD_DIRECTORY=/Downloads

RUN mkdir -p $CONFIG_DIRECTORY $DOWNLOAD_DIRECTORY \
    && chmod 0777 $CONFIG_DIRECTORY \
    && apk add libxml2-dev \
    && docker-php-ext-install simplexml \
    && docker-php-ext-install pcntl

COPY gog-downloader /app/gog-downloader
RUN chmod +x /app/gog-downloader
WORKDIR /app

ENTRYPOINT ["/app/gog-downloader"]
