# The same commands locally and in CI (.github/workflows/parity.yml). Docker only: no PHP on the host.
COMPOSE ?= docker compose
COMPOSER := composer update --no-interaction --no-progress --prefer-dist

.PHONY: install up parity versions down ci

## Each host's dependencies and the suite's, resolved afresh as a consumer gets them (no lock is committed)
install:
	$(COMPOSE) build symfony
	$(COMPOSE) run --rm --no-deps -T symfony $(COMPOSER)
	$(COMPOSE) run --rm --no-deps -T laravel $(COMPOSER)
	$(COMPOSE) run --rm --no-deps -T parity $(COMPOSER)

## MySQL (schema and seed loaded), then both hosts, until each is healthy
up:
	$(COMPOSE) up -d --wait db symfony laravel

## The parity suite against both hosts
parity:
	$(COMPOSE) run --rm -T parity

## What each host runs: omnisense/omnibase and the bridges, with their commits
versions:
	@for host in symfony laravel; do echo "## $$host"; $(COMPOSE) run --rm --no-deps -T $$host sh -c "composer show --no-ansi | grep -E '^(omnisense|omnibridge)/|^(symfony/framework-bundle|laravel/framework|doctrine/orm) '" 2>/dev/null; done

## Everything stopped, the database's volume removed
down:
	$(COMPOSE) down -v --remove-orphans

## What the CI runs
ci: install up parity versions
