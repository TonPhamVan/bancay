.PHONY: setup test lint about

setup:
	bash scripts/setup-local.sh

test:
	docker compose run --rm --no-deps app php artisan test

lint:
	docker compose run --rm --no-deps app vendor/bin/pint --test

about:
	docker compose run --rm --no-deps app php artisan about
