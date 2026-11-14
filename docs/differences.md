# Differences between the two hosts

The master plan's phase 4 gate reads: *behavioural tests pass under both frameworks; differences documented*. This
page is the second half. The first half is `tests/Parity/ParityTest.php`. It sends the same requests to the reference
application on four cells, Symfony (`hosts/symfony`, omnibridge/symfony with omnibase's `BaseBundle`) and Laravel
(`hosts/laravel`, omnibridge/laravel), each on omnibridge/doctrine and on omnibridge/eloquent, all reading one MySQL
database, compares every cell with Symfony on Doctrine, and fails on any difference not listed here.

The differences listed are the hosts'. Each is checked between every Symfony cell and every Laravel cell, whichever
persistence each runs on. Between Doctrine and Eloquent under one host, no difference is listed and none is allowed.

Each difference below has an id. `tests/Parity/differences.php` has an entry under the same id, with a check that the
difference still holds. When a change removes a difference, the suite fails until its entry leaves both files, so the
list stays true.

## What is the same

The suite compares, for every request in `ParityTest::requests()`, each cell's answer with the reference's:

- the status;
- the media type (`text/html`, `application/json`; the charset's spelling, `UTF-8` or `utf-8`, is not compared);
- `Vary`;
- the body: byte for byte for a page, value for value for JSON (key order and escaping aside).

The requests are:

- the home, a note's page for each note, in HTML and in JSON (`Accept: application/json` on the same address);
- the home for a French browser (`Accept-Language`) and for a French visitor (`LANG=fr-FR`), translated through the
  same ICU catalogues;
- a topic at its English and its French address (one route per language, the page's language the route's), with and
  without a query;
- a search (accents and case), one too short (400), one with nothing asked;
- omnibase's Base/App precedence (`/aliases`: `App\Entity\Note` is the application's own class, `App\Enum\CommentState`
  is omnibase's `Base\Enum\CommentState` under another name);
- a form posted and a JSON object posted (`array $body`), and nothing posted (422).

Beyond those, the suite checks that every cell answers 404 for a note, a topic or an address that does not exist, 405
with `Allow: POST` for the POST-only action asked with another method, takes the route's language and writes it back to
`LANG` (`en-GB`, `fr-FR`), counts a page it sends once in omnibase's analytics and nothing else, and runs on the
persistence it is named after (`/persistence`).

The application code is the same on every cell. That covers the actions in `app/src/Action`, which return omnibase's
`Template` and `Json`, the `App\Notes` service on omnibase's `Base\Database\Repository\Repositories` (each
persistence's repositories: `findBy()`, `findOneBy()`; the search reads the notes by title and looks for the words in
PHP, since `Entities` takes criteria of equality only), the page counter on the core's
`Base\Repository\Analytics\PageViews`, the entity, the Twig templates and the catalogues. Nothing in `app/` asks an
ORM. Only each host's own bootstrap differs: `hosts/symfony` (kernel, bundles, configuration) and
`hosts/laravel` (bootstrap, routes from the same `#[Route]` attributes, `config/omnibridge.php`).

## The differences

### `error-pages`

A page that does not exist is a 404 on both hosts, but the error page is each framework's own: Symfony's
`TwigBundle` error template, Laravel's `errors::404` view. The reference application ships no error template of its
own, so the bodies are not compared. A site that wants one page on both hosts gives each framework the same template.
This is a framework default, not a defect of either bridge.

### `not-found-vary`

Symfony's 404 carries `Vary: Accept`, whether an action found nothing (it returned `null`) or no route matched.
Symfony's error renderer (`SerializerErrorRenderer`, there because omnibase installs symfony/serializer) picks the
error page's format from `Accept` and says so with `Vary: Accept`. Laravel's exception handler also renders JSON for a
client that expects it, but sends no `Vary`. A cache in front of a Laravel site could therefore keep one 404 for both
kinds of client. To remove the difference, omnibridge/laravel would add `Vary: Accept` to the error responses it
renders.

### `cache-control`

A page whose action sets no caching is sent by Symfony with `Cache-Control: max-age=0, must-revalidate, private` and an
`Expires` header, and by Laravel with `Cache-Control: no-cache, private`. Both bridges apply omnibase's
`Base\Screen\Cache` the same way, and the default one sets nothing. The Symfony header comes from Symfony's session
listener (`AbstractSessionListener`), which marks private any response of a request that started the session, and
omnibase's Symfony host starts one on every page (`session-cookies`). Laravel's is its `Response` default. Both forbid a
shared cache and ask for revalidation, so a browser behaves the same. An action that sets a `Base\Screen\Cache` gets
the same directives on both hosts, but on Symfony a started session still makes the page private.

### `session-cookies`

Both hosts start a session on a public page that reads none. Symfony's is `PHPSESSID`, started by omnibase's Symfony
host. Laravel's is `laravel_session` (encrypted), started by its `web` middleware group, which also sends the CSRF
cookie `XSRF-TOKEN`. The reference application reads neither. omnibase's forms carry their own token through the
`Tokens` contract on both hosts. `LANG`, the cookie omnibase reads, is written the same way by both.

### `twig-app-variable`

Under Symfony, every template sees Twig's `app` variable (`app.request`, `app.user`, `app.session`), which `TwigBundle`
provides. omnibridge/laravel's default rendering (the bound `Base\Screen\Negotiation`) has no `app`. A Laravel site
adds its own through `Omnibridge\Laravel\Page\AppVariable` in the `Negotiation` it binds. For that reason, the
reference application's templates do not read `app`; they take the page's language from the catalogue
(`notes.lang`). `/twig-app` prints whether `app` is defined.

## What each host needs

The Laravel host needs omnibridge/laravel, omnibridge/doctrine and Doctrine, and five options in
`config/omnibridge.php` (languages, catalogues, templates, entities, and `omnibase` on: omnibase's services, its
analytics among them).

The Symfony host needs FrameworkBundle, TwigBundle and omnibase's `BaseBundle`, and beyond them only what omnibase's
Symfony host is built on. Each item below was found by booting `hosts/symfony` with less:

- SecurityBundle registered, with one firewall (`security.firewalls.main: ~`, SecurityBundle's own minimum): omnibase's
  Symfony host is built on Symfony's security - the firewall map in its router and `Base\Service\BaseService`, the
  token storage, the authorization checker and the CSRF token manager in its form, field and Twig services, its voters.
  It comes with omnibridge/symfony; registering it is the host's part.
- Sessions on (`framework.session`): omnibase's forms and sign-in keep their CSRF tokens there
  (`security.csrf.token_manager`, which FrameworkBundle makes only with sessions or stateless token ids).
- DoctrineBundle and omnibridge/doctrine, which omnibridge/symfony only suggests: the Symfony/Doctrine cell's
  persistence, and on the Symfony/Eloquent cell the entities' mapping and the PDO omnibridge/eloquent reads. That is
  this repository's choice, to run both cells from one folder, not omnibase's: on omnibridge/eloquent alone, omnibase's
  host compiles without Doctrine (Capsule on `DATABASE_URL`).
- Omnibase's analytics tables in the database, beside `note`: both hosts count the pages they send there
  (`testEveryCellCountsAPageSentAndNothingElse`). Without them, each host reports the failed write after sending the page.

For the matrix, each host's folder installs omnibridge/doctrine and omnibridge/eloquent, and each cell names its
persistence with `OMNIBRIDGE_PERSISTENCE` (compose.yml), which omnisense/omnibridge's `Persistences` reads where both are
installed and `base.persistence` names none:

- Symfony chooses the persistence when the container is compiled, so `hosts/symfony/src/Kernel.php` keeps one cache
  folder per persistence (`var/cache/prod-doctrine`, `var/cache/prod-eloquent`). On Eloquent, omnibridge/symfony reads
  the entities from DoctrineBundle's mapping (`config/packages/doctrine.yaml`) and shares DoctrineBundle's PDO.
- Laravel reads the entities from `omnibridge.doctrine.entities` on both persistences: omnibridge/laravel 1.1.1's
  Eloquent persistence describes the folders declared there, beside the core's.

None of these items changes what the pages answer, which is why they are listed here and not in `differences.php`.

The host needs no longer (each a commit of omnibridge/symfony or omnisense/omnibase; with older ones, `hosts/symfony`
does not boot):

- MonologBundle: `Base\Service\SpamChecker` took `monolog.http_client`; it makes the same client itself where
  MonologBundle is not there. symfony/monolog-bundle is a suggestion of omnibridge/symfony.
- florianv/swap, symfony/http-client and a PSR-7 implementation: `Controller\UX\AutocompleteController` took the
  exchange rates' service, which the core declares only with symfony/http-client; without it the forex route answers
  404.
- league/flysystem-bundle and its six storages: omnibridge/symfony registers the local storages the core declares
  (`local.cache`, `local.uploads`, `local.wysiwyg` and each one's `.public`) where the application names none.
- A `cache.adapter` pool: omnibridge/symfony makes one on the application's default adapter where it configures none.
- `base.yaml`: the storages default to the ones the core declares (`local.uploads`, `local.wysiwyg`), and the
  `use_custom` switches left unset are off, as their readers take them (it was a `TypeError` at boot, in
  `Base\Twig\Loader\FilesystemLoader::$useCustomLoader`).
- Empty `src/Controller`, `templates` and `translations` folders: omnibase's Twig loader reads the ones there are.

## Found and fixed while writing the suite

- **Page views on Laravel** (omnibridge/laravel, *Added: the pages sent counted in omnibase's analytics, as under
  Symfony*). The Symfony host counted every HTML page it sent (omnibridge/symfony's `PageViewSubscriber`, on
  `kernel.terminate`), the Laravel host none. omnibridge/laravel's terminable middleware `Omnibase\PageViews` now gives
  the same pages to `Base\Service\Analytics::track()`, on Doctrine's repositories or omnibridge/eloquent's. This was
  the difference `page-views`; with an omnibridge/laravel older than that commit, the suite fails on
  `testEveryCellCountsAPageSentAndNothingElse`.

- **A method the route does not allow** (omnibridge/symfony, *Fixed: 405 Method Not Allowed for a method the route does
  not allow*). A POST-only action (`/preview`) asked with GET got a 404 from Symfony and a 405 with `Allow: POST` from
  Laravel: omnibridge/symfony's `Routing\Matcher\AdvancedUrlMatcher::match()` caught Symfony's
  `MethodNotAllowedException` and retried the address with a trailing slash, which then matched nothing. It now
  rethrows it when that retry finds nothing, so Symfony's router listener answers 405 with `Allow`. This was the
  difference `method-not-allowed`; with an omnibridge/symfony older than that commit, the suite fails on
  `testAMethodTheRouteRefusesIsNotAllowedOnEveryCell`.

- **Base/App precedence on Laravel** (omnibridge/laravel, *Fixed: omnibase's App\ classes from the start on Laravel*).
  `App\Enum\CommentState` was `null` on Laravel for a request that read no entity, because the convention was only
  registered when Doctrine's entity manager was first built. The provider now registers it. With an omnibridge/laravel
  older than that commit, `/aliases` differs and the suite fails.

## Not compared

- Pages behind a sign-in. The reference application has no account, so the sign-in's effects are not checked here.
  They are compared on the private pilot site and its Laravel twin, which share a database: the same pages signed in,
  an administrator's page, the sign-in's e-mails.
- Writes through omnibase's unit of work and the back office. The only writes here are omnibase's page counters (the
  native SQL of omnibridge/doctrine's analytics repository, the `#[BackendOnly]` methods written on Eloquent's
  connection). The pilot site's suites cover the others under both hosts, on both persistences.
- Response headers other than `Vary` and the ones above (`Date`, `X-Powered-By`, `Host`, `Connection`). They are each
  server's own.
