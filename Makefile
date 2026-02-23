.PHONY: cs-fix cs-check stan-check test pipeline

cs-fix:
	vendor/bin/php-cs-fixer fix

cs-check:
	vendor/bin/php-cs-fixer fix --dry-run

stan-check:
	vendor/bin/phpstan analyse

test:
	vendor/bin/phpunit tests

pipeline: cs-fix stan-check test
	composer audit
