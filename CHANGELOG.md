# Changelog

This repository is a test tool (type `project`, not on Packagist, not tagged): this file records what its CI proves,
change by change.

## Unreleased

### Added

- The host/ORM matrix (master plan §10, point 6): four cells - Symfony and Laravel, each on omnibridge/doctrine and on
  omnibridge/eloquent - in one run of `.github/workflows/parity.yml`, every cell asked the same requests and compared
  with Symfony on Doctrine (`ParityTest::testEveryCellAnswersAsTheReference`), with the same 404, 405, page-count and
  language checks on each. `compose.yml`'s `symfony-eloquent` (:8705) and `laravel-eloquent` (:8706) beside `symfony`
  and `laravel`, `OMNIBRIDGE_PERSISTENCE` per cell, both bridges installed in each host.
- `/persistence` (`App\Action\Persistence`, omnisense/omnibridge's `Backend::name()`) and
  `ParityTest::testEveryCellRunsOnItsPersistence`: a cell that fell back to the other bridge fails.
- `PARITY_CELLS` (`make parity PARITY_CELLS="..."`) for a smaller matrix; `make versions` prints each cell's persistence.

### Changed

- The hosts require the stable releases a consumer installs, with Composer's default stability: omnisense/omnibase
  `^5.1.1`, omnisense/omnibridge `^1.0.1`, omnibridge/symfony `^1.1.1`, omnibridge/laravel `^1.1`, omnibridge/doctrine
  `^1.0.1`, omnibridge/eloquent `^1.0`, omnibridge/twig `^1.0`, instead of the 5.x-dev / 1.x-dev heads with
  `minimum-stability: dev`.
- The reference application asks no ORM: `App\Notes` reads through omnibase's `Base\Database\Repository\Repositories`
  (the search filters the notes read by title in PHP), the page counter through `Base\Repository\Analytics\PageViews`,
  instead of Doctrine's `EntityManagerInterface` and DBAL.
- The differences between the hosts are checked between every Symfony cell and every Laravel cell.
- The Symfony host keeps one container per persistence (`Kernel::getCacheDir()`).
- omnibridge/laravel `^1.1.1`: its Eloquent persistence reads the entity folders of `omnibridge.doctrine.entities`, so
  the Laravel host no longer sets `omnibase.project_dir` to `app/` for the Laravel/Eloquent cell.

## 2026-11-09

- First version (29d7731, df57eb2): Symfony and Laravel on Doctrine, the development heads, 23 requests compared and the documented differences.
