# --- configure the Nextcloud instance
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

# --- Setup fulltext search
docker compose -f tests/integration/compose.yml exec -T --user www-data nextcloud php occ app:install fulltextsearch
docker compose -f tests/integration/compose.yml exec -T --user www-data nextcloud php occ app:install fulltextsearch_elasticsearch
docker compose -f tests/integration/compose.yml exec -T --user www-data nextcloud php occ app:install files_fulltextsearch

# temporary fix for a bug in fulltextsearch
docker compose -f tests/integration/compose.yml exec -T --user www-data nextcloud sed -i 's/setAppValueBool($k, $save\[$k/setAppValueBool($k, (bool)$save\[$k/g' custom_apps/fulltextsearch/lib/Service/ConfigService.php

# configure fulltextsearch
docker compose -f tests/integration/compose.yml exec -T --user www-data nextcloud php occ fulltextsearch:configure '{"search_platform":"OCA\\FullTextSearch_Elasticsearch\\Platform\\ElasticSearchPlatform"}'
docker compose -f tests/integration/compose.yml exec -T --user www-data nextcloud php occ fulltextsearch_elasticsearch:configure '{"elastic_host":"http://elasticsearch:9200", "elastic_index":"testindex"}'

# run index once for testing
docker compose -f tests/integration/compose.yml exec -T --user www-data nextcloud php occ fulltextsearch:index

# --- Install and setup the filefinder app
docker cp . integration_nextcloud:/var/www/html/apps/filefinder
docker compose -f tests/integration/compose.yml exec -T nextcloud chown -R www-data:www-data /var/www/html/apps/filefinder
docker compose -f tests/integration/compose.yml exec -T --user www-data nextcloud php occ app:enable filefinder



