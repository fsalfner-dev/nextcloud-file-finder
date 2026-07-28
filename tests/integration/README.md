# How to run integration tests

## Local running of integration tests

### Run the Nextcloud setup in Docker
1. `export NEXTCLOUD_VERSION=33`
1. `docker compose -f tests/integration/compose.yml up -d`
1. `bash tests/integration/init.sh`

### Run PHPUnit Tests in a separate container
1. `docker build -f tests/integration/Dockerfile.debug -t php-debug .`
1. `docker run -it -v ".:/app" --network integration_default php-debug bash`
1. `cd /app && composer install && composer require --dev phpunit/phpunit && composer require --dev guzzlehttp/guzzle`
1. `export NEXTCLOUD_URL='http://nextcloud'`
1. `./vendor/bin/phpunit -c tests/phpintegration.xml --testsuite integration --display-warnings`

