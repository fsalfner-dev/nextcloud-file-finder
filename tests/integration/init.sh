docker compose -f tests/integration/compose.yml exec -T --user www-data nextcloud \
            php occ maintenance:install \
              --database mysql \
              --database-host db \
              --database-name nextcloud \
              --database-user nextcloud \
              --database-pass nextcloud \
              --admin-user admin \
              --admin-pass admin

docker compose -f tests/integration/compose.yml exec -T --user www-data nextcloud php occ config:system:set trusted_domains 1 --value=nextcloud
docker compose -f tests/integration/compose.yml run php_debug sh cd /app && composer install && composer require --dev phpunit/phpunit && composer require --dev guzzlehttp/guzzle && bash

