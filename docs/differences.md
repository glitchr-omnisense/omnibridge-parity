# Differences between the two hosts

The master plan's phase 4 gate reads: *behavioural tests pass under both frameworks; differences documented*. This
page is the second half. The first half is `tests/Parity/ParityTest.php`. It sends the same requests to the reference
application under Symfony (`hosts/symfony`, omnibridge/symfony with omnibase's `BaseBundle`) and under Laravel
(`hosts/laravel`, omnibridge/laravel), both reading one MySQL database, and fails on any difference not listed here.

Each difference below has an id. `tests/Parity/differences.php` has an entry under the same id, with a check that the
difference still holds. When a change removes a difference, the suite fails until its entry leaves both files, so the
list stays true.

## What is the same

The suite compares, for every request in `ParityTest::requests()`:

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

Beyond those, the suite checks that both hosts answer 404 for a note, a topic or an address that does not exist, and
that both take the route's language and write it back to `LANG` (`en-GB`, `fr-FR`).

The application code is the same on both hosts. That covers the actions in `app/src/Action`, which return omnibase's
`Template` and `Json`, the `App\Notes` service on Doctrine's `EntityManagerInterface`, the entity, the Twig templates
and the catalogues. Only each host's own bootstrap differs: `hosts/symfony` (kernel, bundles, configuration) and
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

### `method-not-allowed`

A POST-only action (`/preview`) asked with GET gets a 404 from Symfony and a 405 from Laravel. The 404 comes from
omnibridge/symfony's `Routing\Matcher\AdvancedUrlMatcher::match()`: it catches every exception of Symfony's matcher,
`MethodNotAllowedException` included, and tries the address again with a trailing slash, which then matches nothing.
Laravel's router answers 405 with `Allow: POST`. This difference is a defect of the Symfony host. The fix, in
omnibridge/symfony, is to rethrow the `MethodNotAllowedException` when the trailing-slash retry matches nothing. It is
not done here, because it changes what every omnibase Symfony site answers.

### `page-views`

Omnibase's Symfony host counts every HTML page it sends: omnibridge/symfony's `Subscriber\PageViewSubscriber`, on
`kernel.terminate`, writes `analytics_page_view` (and, for a visitor with the analytics cookie or a member,
`analytics_visit` and `analytics_page_visit`). The Laravel host has no such listener and counts nothing. This is why
`database/schema.sql` creates omnibase's analytics tables beside `note`: without them, the Symfony host fails after
sending each page. The suite reads the count (`/page-views`) before and after a page on each host. To remove the
difference, omnibridge/laravel would call omnibase's `Base\Service\Analytics::track()` from a terminable middleware.

### `twig-app-variable`

Under Symfony, every template sees Twig's `app` variable (`app.request`, `app.user`, `app.session`), which `TwigBundle`
provides. omnibridge/laravel's default rendering (the bound `Base\Screen\Negotiation`) has no `app`. A Laravel site
adds its own through `Omnibridge\Laravel\Page\AppVariable` in the `Negotiation` it binds. For that reason, the
reference application's templates do not read `app`; they take the page's language from the catalogue
(`notes.lang`). `/twig-app` prints whether `app` is defined.

## What each host needs

The Laravel host needs omnibridge/laravel, omnibridge/doctrine and Doctrine, and four options in
`config/omnibridge.php` (languages, catalogues, templates, entities).

The Symfony host needs more. Omnibase's `BaseBundle` registers services that depend on bundles, packages and settings
it does not require, and the container does not compile without them. Each item below was found by booting
`hosts/symfony` with less, and nothing in the list can be left out:

- SecurityBundle registered, with one firewall: `Base\Service\BaseService` reads `security.firewall.map`.
- MonologBundle registered and configured: `Base\Service\SpamChecker` takes `monolog.http_client`.
- Sessions on (`framework.session`): `Base\Security\Session\DynamicSessionStorageFactory` extends
  `session.storage.factory.native`.
- DoctrineBundle and omnibridge/doctrine, which omnibridge/symfony only suggests.
- florianv/swap: `Omnibridge\Symfony\Omnibase\Controller\UX\AutocompleteController` takes
  `Base\Field\Search\ExchangeRate`.
- league/flysystem-bundle, with the six storages omnibase names (`local.cache`, `local.uploads`, `local.wysiwyg` and
  each one's `.public`).
- A `cache.adapter` pool: `Base\Routing\AdvancedRouter` takes it.
- `base.yaml` with `database`, `router`, `attributes` and `twig`'s `use_custom` set, `twig.editor.storage` and
  `uploader.storage`. Those options have no default, so leaving one out is a `TypeError` at boot (for example
  `Base\Twig\Loader\FilesystemLoader::$useCustomLoader`), not a configuration error.
- Empty `src/Controller`, `templates` and `translations` folders, which omnibase's Twig loader reads.
- Omnibase's analytics tables in the database (`page-views` above).

SecurityBundle and MonologBundle come with omnibridge/symfony; registering them is the host's part. None of these
items changes what the pages answer, which is why they are listed here and not in `differences.php`. They are what a
Symfony site pays for omnibase's full host, where a Laravel site takes only the contracts. Making those services
optional (each wired only when its package is installed, each option with a default) belongs to omnibridge/symfony.

## Found and fixed while writing the suite

- **Base/App precedence on Laravel** (omnibridge/laravel, *Fixed: omnibase's App\ classes from the start on Laravel*).
  `App\Enum\CommentState` was `null` on Laravel for a request that read no entity, because the convention was only
  registered when Doctrine's entity manager was first built. The provider now registers it. With an omnibridge/laravel
  older than that commit, `/aliases` differs and the suite fails.

## Not compared

- Pages behind a sign-in. The reference application has no account, so the sign-in's effects are not checked here.
  They are compared on the private pilot site and its Laravel twin, which share a database: the same pages signed in,
  an administrator's page, the sign-in's e-mails.
- Writes through omnibase's unit of work, Eloquent as the persistence, the back office. The pilot site's suites cover
  them under both hosts, on both persistences.
- Response headers other than `Vary` and the ones above (`Date`, `X-Powered-By`, `Host`, `Connection`). They are each
  server's own.
