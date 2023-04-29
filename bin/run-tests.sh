#!/usr/bin/env bash

set -e

echo "Installing the test environment..."

docker-compose -f .docker/docker-compose.yml exec -u www-data wordpress rm -fr /tmp/wordpress*

docker-compose -f .docker/docker-compose.yml exec -u www-data wordpress \
	/var/www/html/wp-content/plugins/woocommerce-rave/bin/install-wp-tests.sh

echo "Running the tests..."

docker-compose -f .docker/docker-compose.yml exec -u www-data wordpress \
	/var/www/html/wp-content/plugins/woocommerce-rave/vendor/bin/phpunit \
	--configuration /var/www/html/wp-content/plugins/woocommerce-rave/phpunit.xml.dist \
	--coverage-clover coverage.xml .\
	$*

docker-compose -f .docker/docker-compose.yml cp wordpress:/var/www/html/wp-content/plugins/woocommerce-rave/coverage.xml .