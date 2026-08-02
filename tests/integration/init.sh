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
docker cp . integration_nextcloud:/var/www/html/apps/filefinder
docker compose -f tests/integration/compose.yml exec -T nextcloud chown -R www-data:www-data /var/www/html/apps/filefinder
docker compose -f tests/integration/compose.yml exec -T --user www-data nextcloud php occ app:enable filefinder



