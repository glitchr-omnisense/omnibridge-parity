# omnibridge/parity

One unchanged PHP reference application, served by Symfony through
[omnibridge/symfony](https://github.com/glitchr-omnisense/omnibridge-symfony) and by Laravel through
[omnibridge/laravel](https://github.com/glitchr-omnisense/omnibridge-laravel), each persisted through
[omnibridge/doctrine](https://github.com/glitchr-omnisense/omnibridge-doctrine) and through
[omnibridge/eloquent](https://github.com/glitchr-omnisense/omnibridge-eloquent), on one MySQL database. A suite asks
the four cells the same requests and compares every answer with Symfony on Doctrine's. This is the Omnisense master
plan's phase 4 gate, *behavioural tests pass under both frameworks; differences documented*, and its host/ORM matrix
(§10, point 6), run in CI.

## What is proven, and by which job

`.github/workflows/parity.yml`, job `parity` (on every push, every pull request and nightly), runs the four cells in
one run and the same suite against each:

| Cell | Host | Persistence | Port (`make up`) | Compared with |
|---|---|---|---|---|
| `symfony/doctrine` | omnibridge/symfony, omnibase's `BaseBundle`, DoctrineBundle | omnibridge/doctrine | 8701 | the reference |
| `laravel/doctrine` | omnibridge/laravel | omnibridge/doctrine (on Laravel's connection) | 8702 | `symfony/doctrine` |
| `symfony/eloquent` | omnibridge/symfony (`base.persistence` eloquent) | omnibridge/eloquent (on DoctrineBundle's PDO) | 8705 | `symfony/doctrine` |
| `laravel/eloquent` | omnibridge/laravel (`base.persistence` eloquent) | omnibridge/eloquent (on Laravel's connection) | 8706 | `symfony/doctrine` |

For every cell, the same assertions: each request of `ParityTest::requests()` answered with the reference's status,
media type, `Vary` and body; 404 for what does not exist; 405 with `Allow` for a refused method; a page sent counted
once in omnibase's analytics and nothing else; the route's language kept in `LANG`; and the cell on the persistence it
is named after (`/persistence`, omnibase's `Base\Contract\Backend::name()`). The differences between the two hosts
(`docs/differences.md`) are checked between every Symfony cell and every Laravel cell; between the two persistences of
one host, none is allowed.

The repositories cell (`tests/Repositories`, step "Repositories cell") asks the application's repositories - the same
`App\Repository` classes on omnibase's agnostic `ServiceEntityRepository` everywhere - the same cases on the four
cells: plain finds, orders, limits and offsets, lists, counts, the finder language, criteria groups, pages, paths
through one association and through two, `null` and `[]` criteria, a GROUP BY written once
(`findValuesBy()`), `Repositories::of()`, and a comment written, changed, read and removed while the application's
listener of omnibase's neutral lifecycle events records what it hears. Every cell answers as the reference, and the
reference as `database/seed.sql` says.

Each host's folder installs both persistence bridges, and `OMNIBRIDGE_PERSISTENCE` (compose.yml) names the one a cell
runs on, as omnisense/omnibridge's `Persistences` reads it when both are installed and the host's option names none.
The Symfony host keeps one container per persistence (`hosts/symfony/src/Kernel.php`).

The same proof exists on a real site, a private pilot site with its Laravel twin. That one runs on its own data and
cannot run in a public CI. This repository is the self-contained version: invented fixtures, public packages only,
no secret.

This repository is a test tool, not a package: it is not on Packagist, has no tag and no release, and nothing
installs it with `composer require`. Its `composer.json` (type `project`) only installs the suite's PHPUnit.

## Layout

| Path | What it is |
|---|---|
| `app/src/Action/` | the actions, written once: plain classes with symfony/routing's `#[Route]`, returning omnisense/omnibase's `Base\Screen\Template` or `Json` (or `null`: no such page) and taking `array $query` / `array $body` |
| `app/src/Entity/` | the notes, their authors and comments, mapped with omnibase's attributes (`Base\Mapping`, the names and parameters of Doctrine's), which omnibridge/doctrine and omnibridge/eloquent both read |
| `app/src/Repository/` | `NoteRepository`, `CommentRepository`: omnibase's agnostic `Base\Database\Repository\ServiceEntityRepository`, the same classes on every cell |
| `app/src/EntitySubscriber/` | a listener of omnibase's neutral lifecycle events (`Base\Contract\Lifecycle\*`), tagged `omnibridge.lifecycle_listener` by each host |
| `app/templates/`, `app/translations/` | the Twig templates and the ICU catalogues, the same files on both hosts |
| `hosts/symfony/` | the Symfony host: a micro kernel, FrameworkBundle, TwigBundle, DoctrineBundle, SecurityBundle and omnibase's `BaseBundle` (omnibridge/symfony), and the configuration omnibase needs ([what and why](docs/differences.md#what-each-host-needs)); both persistence bridges |
| `hosts/laravel/` | the Laravel host: `bootstrap/app.php` (omnibridge/laravel's `Localize`), `routes/web.php` (`ActionRoutes` on the same actions), `config/omnibridge.php`; both persistence bridges |
| `database/schema.sql`, `database/seed.sql` | the schema, as Doctrine's SchemaTool writes it, and the fixtures, loaded when MySQL's volume is new |
| `tests/Parity/` | the suite (`ParityTest`, its cells from `PARITY_CELLS`) and the differences it allows (`differences.php`) |
| `tests/Repositories/` | the repositories cell (`RepositoriesTest`): the application's repositories and lifecycle listener asked the same cases on every cell, the reference's answers checked against the seed |
| `docs/differences.md` | what differs between the two hosts and why: the gate's "differences documented" |

## Run it

Only Docker is needed:

```sh
make install   # each host's dependencies and the suite's, resolved afresh (no lock is committed)
make up        # MySQL, then the four cells (:8701, :8702, :8705, :8706), until each is healthy
make parity    # the suite against the four
make repositories  # the repositories cell against the four
make versions  # what ran: omnisense/omnibase and the bridges, and the persistence of each cell
make down      # everything stopped, the database's volume removed
```

`make ci` runs install, up, parity, repositories and versions, which is what `.github/workflows/parity.yml` runs. Dependencies come
from Packagist each time, at the stable releases each host's carets allow, with Composer's default stability: what a
project installs with `composer require` (omnisense/omnibase, omnisense/omnibridge and the bridges omnibridge/symfony,
laravel, doctrine, eloquent and twig, each at its new major: `^7.0` for omnisense/omnibase, `^3.0` for omnibridge/symfony and
omnibridge/doctrine, `^2.0` for the others).

`make ensemble ENSEMBLE_ROOT=<folder>` runs the same on packages checked out locally (a folder holding `omnibase/` and
`omnibridge/`, this checkout as its `omnibridge/parity`): each host gets a `composer.ensemble.json`, never committed,
resolving omnisense/* and omnibridge/* by path repositories, and `compose.ensemble.yml` mounts the folder in every
container; the database is new. A new release of any of them shows here the night after Packagist serves it.

A smaller matrix runs with `PARITY_CELLS`, the first cell named being the reference:

```sh
make parity PARITY_CELLS="symfony/doctrine=http://symfony:8000 laravel/eloquent=http://laravel-eloquent:8000"
```

The PHP image is glitchr-studio/ci's `ghcr.io/glitchr-studio/php:8.4` with `pdo_mysql` added (`docker/Dockerfile`).

## Add a case

Add the action to `app/src/Action` (and its template, if it has one), add a row to the seed if it needs data, and add
the request to `ParityTest::requests()`. If the two hosts must answer differently, add an entry to
`tests/Parity/differences.php` with a check that the difference holds, and explain it under the same id in
`docs/differences.md`. Otherwise fix the host that is wrong.

## License

MIT
