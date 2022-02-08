FROM php:8.1-alpine

ENV CONFIG_DIRECTORY=/Configs
ENV DOWNLOAD_DIRECTORY=/Downloads

RUN mkdir -p $CONFIG_DIRECTORY $DOWNLOAD_DIRECTORY \
    && apk add libxml2-dev \
    && docker-php-ext-install simplexml

COPY gog-downloader /app/gog-downloader
RUN chmod +x /app/gog-downloader
WORKDIR /app

CMD ["/app/gog-downloader"]
