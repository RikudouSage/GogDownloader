FROM php:8.1-alpine

RUN apk add libxml2-dev \
    && docker-php-ext-install simplexml

COPY gog-downloader /app/gog-downloader
RUN chmod +x /app/gog-downloader
WORKDIR /app

CMD ["/app/gog-downloader"]
