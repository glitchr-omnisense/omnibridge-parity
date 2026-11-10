# omnibridge/parity

One unchanged PHP reference application, served by Symfony through
[omnibridge/symfony](https://github.com/glitchr-omnisense/omnibridge-symfony) and by Laravel through
[omnibridge/laravel](https://github.com/glitchr-omnisense/omnibridge-laravel), on one MySQL database. A suite asks
both hosts the same requests and compares the answers. This is the Omnisense master plan's phase 4 gate, run in CI:
*behavioural tests pass under both frameworks; differences documented*.

The same proof exists on a real site, a private pilot site with its Laravel twin. That one runs on its own data and
cannot run in a public CI. This repository is the self-contained version: invented fixtures, public packages only,
no secret.

## Layout

| Path | What it is |
|---|---|
| `app/src/Action/` | the actions, written once: plain classes with symfony/routing's `#[Route]`, returning omnisense/omnibase's `Base\Screen\Template` or `Json` (or `null`: no such page) and taking `array $query` / `array $body` |
| `app/src/Notes.php`, `app/src/Entity/Note.php` | the notes, read through Doctrine's `EntityManagerInterface`, which each host provides |
| `app/templates/`, `app/translations/` | the Twig templates and the ICU catalogues, the same files on both hosts |
| `hosts/symfony/` | the Symfony host: a micro kernel, FrameworkBundle, TwigBundle, DoctrineBundle, SecurityBundle and omnibase's `BaseBundle` (omnibridge/symfony), and the configuration omnibase needs ([what and why](docs/differences.md#what-each-host-needs)) |
| `hosts/laravel/` | the Laravel host: `bootstrap/app.php` (omnibridge/laravel's `Localize`), `routes/web.php` (`ActionRoutes` on the same actions), `config/omnibridge.php` |
| `database/schema.sql`, `database/seed.sql` | the schema, as Doctrine's SchemaTool writes it, and the fixtures, loaded when MySQL's volume is new |
| `tests/Parity/` | the suite (`ParityTest`) and the differences it allows (`differences.php`) |
| `docs/differences.md` | what differs between the two hosts and why: the gate's "differences documented" |

## Run it

Only Docker is needed:

```sh
make install   # each host's dependencies and the suite's, resolved afresh (no lock is committed)
make up        # MySQL, then Symfony on :8701 and Laravel on :8702, until both are healthy
make parity    # the suite against both
make versions  # what ran: omnisense/omnibase and the bridges, with their commits
make down      # everything stopped, the database's volume removed
```

`make ci` runs install, up, parity and versions, which is what `.github/workflows/parity.yml` runs on every push,
every pull request and nightly. Dependencies come from Packagist each time, at the heads of omnisense/omnibase 5.x and
the bridges' 1.x, so a change in any of them shows here the night after Packagist serves it.

The PHP image is glitchr-studio/ci's `ghcr.io/glitchr-studio/php:8.4` with `pdo_mysql` added (`docker/Dockerfile`).

## Add a case

Add the action to `app/src/Action` (and its template, if it has one), add a row to the seed if it needs data, and add
the request to `ParityTest::requests()`. If the two hosts must answer differently, add an entry to
`tests/Parity/differences.php` with a check that the difference holds, and explain it under the same id in
`docs/differences.md`. Otherwise fix the host that is wrong.

## License

MIT
