# How to run integration tests

## Test development

1. `docker compose -f tests/integration/compose.yml up -d`
1. `bash tests/integration/init.sh`
1. 


```
docker run -it -v "./:/app/" --network integration_default ubuntu:latest
apt-get update && apt-get install -y curl wget php-cli php-mbstring php-xml php-curl unzip
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
cd /app
composer install 
composer require --dev phpunit/phpunit
composer require --dev guzzlehttp/guzzle
export NEXTCLOUD_URL='http://nextcloud'
./vendor/bin/phpunit -c tests/phpintegration.xml --testsuite integration
```

