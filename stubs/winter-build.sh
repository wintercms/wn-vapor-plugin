#!/bin/bash

BUILD_DIR=$(cd $(dirname $(dirname "${0}")) && pwd)

if [ $# -eq 0 ]; then
    echo "Error: no env passed"
    exit 1
else
    DEPLOY_ENV=$1
fi

rm -rf "${BUILD_DIR}/public" && mkdir -p "${BUILD_DIR}/public"

# Mirror assets to local dir for vapor to find and upload
php "${BUILD_DIR}/artisan" vapor:mirror "public" \
    --copy \
    --ignore "/\/src\//" \
    --ignore "/(.*).php/" \
    --ignore "/.htaccess/" \
    --ignore "/\/storage\//"

# Remove assets to reduce bundle
# TODO: Fix this by adding exceptions for files required by the application
# php "${BUILD_DIR}/artisan" vapor:mirror "tmp" \
#      --delete \
#      --ignore "/(.*).php/" \
#      --ignore "/.htaccess/" \
#      --ignore "/\/storage\//" \
#      --ignore "/themes\/(.*)\/assets/"

# Remove the current storage directory
rm -rf "${BUILD_DIR}/storage" &&

# Make the storage directories if they don't already exist
mkdir -p "${BUILD_DIR}/storage/app/media" &&
mkdir -p "${BUILD_DIR}/storage/app/resized" &&
mkdir -p "${BUILD_DIR}/storage/app/uploads/public" &&
mkdir -p "${BUILD_DIR}/storage/cms/cache" &&
mkdir -p "${BUILD_DIR}/storage/cms/combiner" &&
mkdir -p "${BUILD_DIR}/storage/cms/twig" &&
mkdir -p "${BUILD_DIR}/storage/framework/cache" &&
mkdir -p "${BUILD_DIR}/storage/framework/sessions" &&
mkdir -p "${BUILD_DIR}/storage/framework/views" &&
mkdir -p "${BUILD_DIR}/storage/temp/public" &&
mkdir -p "${BUILD_DIR}/storage/logs" &&
mkdir -p "${BUILD_DIR}/storage/temp/public" &&

# Trigger Laravel package discovery
php "${BUILD_DIR}/artisan" package:discover &&

# Set the winter version for cache busting
php "${BUILD_DIR}/artisan" winter:version &&
php "${BUILD_DIR}/artisan" cache:clear
