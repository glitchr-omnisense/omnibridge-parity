# The same commands locally and in CI (.github/workflows/parity.yml). Docker only: no PHP on the host.
COMPOSE ?= docker compose
COMPOSER := composer update --no-interaction --no-progress --prefer-dist

.PHONY: install up parity repositories versions down ci ensemble

## Each host's dependencies and the suite's, resolved afresh from Packagist (no lock is committed): the stable releases
## the hosts' carets allow (omnisense/omnibase and the bridges ^1.0), both persistence bridges in each host
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
	$(COMPOSE) run --rm -T $(if $(PARITY_CELLS),-e PARITY_CELLS="$(PARITY_CELLS)") parity php vendor/bin/phpunit --testsuite Parity

## The repositories cell: the application's repositories and lifecycle listener, the same answers on the four cells
repositories:
	$(COMPOSE) run --rm -T $(if $(PARITY_CELLS),-e PARITY_CELLS="$(PARITY_CELLS)") parity php vendor/bin/phpunit --testsuite Repositories

## What each host runs: omnisense/omnibase and the bridges, with their commits; and the persistence each cell runs on
versions:
	@for host in symfony laravel; do echo "## $$host"; $(COMPOSE) run --rm --no-deps -T $$host sh -c "composer show --no-ansi | grep -E '^(omnisense|omnibridge)/|^(symfony/framework-bundle|laravel/framework|doctrine/orm|illuminate/database) '" 2>/dev/null; done
	@echo "## cells"; for cell in symfony laravel symfony-eloquent laravel-eloquent; do printf '%s: ' $$cell; $(COMPOSE) exec -T $$cell curl -fsS http://127.0.0.1:8000/persistence 2>/dev/null || echo 'not running'; echo; done

## Everything stopped, the database's volume removed
down:
	$(COMPOSE) down -v --remove-orphans

## What the CI runs
ci: install up parity repositories versions

## The same run on packages checked out locally (ENSEMBLE_ROOT: the folder holding omnibase/ and omnibridge/, this
## checkout being its omnibridge/parity): each host's composer.ensemble.json (never committed) resolves omnisense/* and
## omnibridge/* by path repositories, compose.ensemble.yml mounts them; a new database first. The stack stays up: `make
## down COMPOSE="docker compose -f compose.yml -f compose.ensemble.yml"` after.
ENSEMBLE_ROOT ?= $(abspath ../..)
export ENSEMBLE_ROOT
ensemble:
	python3 $(ENSEMBLE_ROOT)/_tools/foundation/ensemble-composer.py omnibridge/parity/hosts/symfony
	python3 $(ENSEMBLE_ROOT)/_tools/foundation/ensemble-composer.py omnibridge/parity/hosts/laravel
	$(MAKE) down ci COMPOSE="$(COMPOSE) -f compose.yml -f compose.ensemble.yml"

