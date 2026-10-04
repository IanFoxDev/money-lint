.PHONY: test stan cs cs-fix check examples

test:
	vendor/bin/phpunit

stan:
	vendor/bin/phpstan analyse --no-progress

cs:
	vendor/bin/php-cs-fixer check --diff

cs-fix:
	vendor/bin/php-cs-fixer fix

check: cs stan test

examples:
	cd examples/laravel && composer install --no-progress -q && php ../check.php
	cd examples/symfony && composer install --no-progress -q && php ../check.php
