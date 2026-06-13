# Certificate Studio — PHP + GD + cURL on Render
FROM php:8.3-cli

# GD with FreeType/JPEG/WEBP so imagettftext() can draw text on backgrounds.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd zip \
    && rm -rf /var/lib/apt/lists/*
# (ext-curl, openssl and mbstring are already bundled in the official image.)

WORKDIR /app
COPY . /app

# Writable runtime dirs for uploads / font cache / generated PDFs.
RUN mkdir -p uploads fonts output && chmod -R 0777 uploads fonts output

# Render injects $PORT; the PHP built-in server serves the app directly.
ENV PORT=10000
EXPOSE 10000
CMD ["sh", "-c", "php -d upload_max_filesize=16M -d post_max_size=20M -S 0.0.0.0:${PORT} -t /app"]
