# The same commands locally and in CI (.github/workflows/parity.yml). Docker only: no PHP on the host.
COMPOSE ?= docker compose
COMPOSER := composer update --no-interaction --no-progress --prefer-dist

.PHONY: install up parity versions down ci

## Each host's dependencies and the suite's, resolved afresh from Packagist (no lock is committed): the stable releases
## the hosts' carets allow (omnisense/omnibase ^5.1.1, the bridges ^1.x), both persistence bridges in each host
install:
	$(COMPOSE) build symfony
	$(COMPOSE) run --rm --no-deps -T symfony $(COMPOSER)
	$(COMPOSE) run --rm --no-deps -T laravel $(COMPOSER)
	$(COMPOSE) run --rm --no-deps -T parity $(COMPOSER)

## MySQL (schema and seed loaded), then the four cells (Symfony and Laravel, each on Doctrine and on Eloquent), until each is healthy
up:
	$(COMPOSE) up -d --wait db symfony laravel symfony-eloquent laravel-eloquent

## The parity suite against the four cells (PARITY_CELLS="host/persistence=origin ...": fewer, the first the reference)
parity:
	$(COMPOSE) run --rm -T $(if $(PARITY_CELLS),-e PARITY_CELLS="$(PARITY_CELLS)") parity

## What each host runs: omnisense/omnibase and the bridges, with their commits; and the persistence each cell runs on
versions:
	@for host in symfony laravel; do echo "## $$host"; $(COMPOSE) run --rm --no-deps -T $$host sh -c "composer show --no-ansi | grep -E '^(omnisense|omnibridge)/|^(symfony/framework-bundle|laravel/framework|doctrine/orm|illuminate/database) '" 2>/dev/null; done
	@echo "## cells"; for cell in symfony laravel symfony-eloquent laravel-eloquent; do printf '%s: ' $$cell; $(COMPOSE) exec -T $$cell curl -fsS http://127.0.0.1:8000/persistence 2>/dev/null || echo 'not running'; echo; done

## Everything stopped, the database's volume removed
down:
	$(COMPOSE) down -v --remove-orphans

## What the CI runs
ci: install up parity versions
