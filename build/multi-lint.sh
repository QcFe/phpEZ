#!/usr/bin/env bash
# Lints phpez.php with php -l across PHP 8.1 to 8.4 using Docker.

cd "$(dirname "${BASH_SOURCE[0]}")/.."

TARGET_FILE="phpez.php"
VERSIONS=(8.3 8.4 8.5)

LATEST_VER=$(curl -s https://api.github.com/repos/QcFe/phpEZ/releases/latest | grep '"tag_name":' | sed -E 's/.*"([^"]+)".*/\1/')

wget https://github.com/QcFe/phpEZ/releases/download/${LATEST_VER}/phpez.php -O "$TARGET_FILE"

for version in "${VERSIONS[@]}"; do
    echo "== PHP ${version} =="
    IMG="php:${version}-cli-alpine"
    docker pull --quiet "$IMG"
    OUT=$(docker run --rm --quiet -v "$PWD:/app" -w /app "$IMG" php -l "$TARGET_FILE")
    if [ $? -ne 0 ]; then
        echo "Linting failed for PHP ${version}: $OUT"
    else
        echo "Linting passed for PHP ${version}"
    fi
done
