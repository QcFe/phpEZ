#!/usr/bin/env bash
# Lints phpez.php with php -l across PHP 8.1 to 8.4 using Docker.

cd "$(dirname "${BASH_SOURCE[0]}")/.."

TARGET_FILE="/tmp/phpez.php"
VERSIONS=(8.3 8.4 8.5)

php build/package.php "$TARGET_FILE"

for version in "${VERSIONS[@]}"; do
    echo "== PHP ${version} =="
    IMG="php:${version}-cli-alpine"
    docker pull --quiet "$IMG"
    OUT=$(docker run --rm --quiet -v "$TARGET_FILE:$TARGET_FILE" -w /app "$IMG" php -l "$TARGET_FILE")
    if [ $? -ne 0 ]; then
        echo "Linting failed for PHP ${version}: $OUT"
    else
        echo "Linting passed for PHP ${version}"
    fi
done
