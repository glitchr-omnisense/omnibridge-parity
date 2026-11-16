# Changelog

This repository is a test tool (type `project`, not on Packagist, not tagged): this file says what its CI proves.

## Unreleased

One reference application (`app/`: actions, entities, repositories, templates, catalogues) served by Symfony through
omnibridge/symfony and by Laravel through omnibridge/laravel, each persisted through omnibridge/doctrine and through
omnibridge/eloquent, on one MySQL database, written only with omnibase's `Base\` names and its own `App\` ones.

- The host/ORM matrix: four cells in one run of `.github/workflows/parity.yml`, every cell asked the same requests and
  compared with Symfony on Doctrine (`ParityTest`), with the same 404, 405, page-count, language and persistence checks
  on each, and the documented differences between the hosts checked between every Symfony cell and every Laravel cell.
- The repositories cell (`RepositoriesTest`, `make repositories`): `App\Repository\NoteRepository` and
  `CommentRepository` on omnibase's agnostic `Base\Database\Repository\ServiceEntityRepository`, asked the same cases on
  the four cells (finds, orders and pages, lists, counts, the finder language, criteria groups, paths through one and
  two associations, `null` and `[]` criteria, `findValuesBy()` with an aggregate (`CommentRepository::countPerNote()`),
  `Repositories::of()`, each case written once and compiled by omnibridge/doctrine and omnibridge/eloquent), and a
  listener of the neutral lifecycle events (`App\EntitySubscriber\CommentLifecycle`)
  hearing the same moments on each.
- The hosts require omnisense/omnibase at `^7.0`, omnisense/omnibridge at `^2.0` and the bridges at their new majors
  (omnibridge/doctrine and omnibridge/symfony `^3.0`, omnibridge/eloquent, omnibridge/laravel and omnibridge/twig
  `^2.0`); `make ensemble` runs the same
  on local checkouts (path repositories in a `composer.ensemble.json` per host, never committed; `compose.ensemble.yml`).
