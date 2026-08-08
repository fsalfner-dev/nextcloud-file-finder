# How to run integration tests

## Local running of integration tests

## Start the Nextcloud containers
1. `export NEXTCLOUD_VERSION=34`
1. `docker compose -f tests/integration/compose.yml up -d`

### Build the app in a separate container
1. `docker build -f tests/integration/Dockerfile.debug -t php-debug .`
1. `docker run -it -v ".:/app" --network integration_default php-debug bash`
    1. `cd /app`
    1. `composer install && composer require --dev phpunit/phpunit && composer require --dev guzzlehttp/guzzle`
    1. `npm i`
    1. `npm run build` 

### Run the Nextcloud setup in Docker
1. `bash tests/integration/init.sh`

### Run PHPUnit Tests (in the php-debug container)
1. `export NEXTCLOUD_URL='http://nextcloud'`
1. `./vendor/bin/phpunit -c tests/phpintegration.xml --testsuite integration_fulltext --display-warnings`

### Disable Fulltextsearch (outside php-debug)
1. `docker compose -f tests/integration/compose.yml exec -T --user www-data nextcloud php occ app:disable fulltextsearch`

### run Files Testsuite in php-debug container
1. `export NEXTCLOUD_URL='http://nextcloud'`
1. `./vendor/bin/phpunit -c tests/phpintegration.xml --testsuite integration_files --display-warnings`

