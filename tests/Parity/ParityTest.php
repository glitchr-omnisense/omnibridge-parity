<?php

namespace Omnibridge\Parity\Tests\Parity;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;

/**
 * The reference application (app/) asked the same requests on every cell of the matrix host x persistence - Symfony
 * (hosts/symfony: omnibridge/symfony, omnibase's BaseBundle) and Laravel (hosts/laravel: omnibridge/laravel), each on
 * omnibridge/doctrine and on omnibridge/eloquent -, all on one MySQL database, and every cell's answers compared with
 * the reference cell's (the first: Symfony on Doctrine): the status, the media type and the body, byte for byte for a
 * page, value for value for JSON. What the two hosts are allowed to do differently is written in
 * tests/Parity/differences.php, each entry explained in docs/differences.md and checked between every Symfony cell and
 * every Laravel cell; between two persistences of one host nothing may differ. The master plan's phase 4 gate, "behavioural tests pass under both
 * frameworks; differences documented", and its §10 point 6, the host/ORM matrix. Nothing here boots either framework:
 * every cell is asked over HTTP.
 *
 * Cells: PARITY_CELLS, `host/persistence=origin` separated by spaces (compose's parity service sets the four), the
 * reference first.
 */
final class ParityTest extends TestCase
{
    private const SLUGS = ['green-tea', 'cafe-creme', 'night-train', 'harbour-walk', 'tea-ceremony', 'first-snow'];

    /** The four cells and their ports on the machine, when PARITY_CELLS names none (make up publishes them so). */
    private const DEFAULT_CELLS = 'symfony/doctrine=http://localhost:8701 laravel/doctrine=http://localhost:8702 symfony/eloquent=http://localhost:8705 laravel/eloquent=http://localhost:8706';

    /** @var array<string, array{status: int, type: string, headers: array<string, string>, cookies: array<string, string>, body: string}> answers, by cell and request */
    private static array $answers = [];

    /** @return iterable<string, array{string, string, array<string, string>, string|null}> method, path, headers, body */
    public static function requests(): iterable
    {
        yield 'home' => ['GET', '/', [], null];
        yield 'home, a French browser' => ['GET', '/', ['Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.5'], null];
        yield 'home, French chosen (LANG)' => ['GET', '/', ['Cookie' => 'LANG=fr-FR'], null];
        foreach (self::SLUGS as $slug) {
            yield 'note '.$slug => ['GET', '/notes/'.$slug, [], null];
        }
        yield 'note in JSON (Accept)' => ['GET', '/notes/cafe-creme', ['Accept' => 'application/json'], null];
        yield 'home in JSON (Accept)' => ['GET', '/', ['Accept' => 'application/json'], null];
        yield 'topic, English address' => ['GET', '/topics/cuisine', [], null];
        yield 'topic, French address' => ['GET', '/sujets/travel', [], null];
        yield 'topic with a query' => ['GET', '/topics/cuisine?limit=1', [], null];
        yield 'topic with a wrong query' => ['GET', '/topics/season?limit=none', [], null];
        yield 'search' => ['GET', '/search?q=tea', [], null];
        yield 'search, accents and case' => ['GET', '/search?q='.rawurlencode('CAFÉ'), [], null];
        yield 'search, too short' => ['GET', '/search?q=t', [], null];
        yield 'search, nothing asked' => ['GET', '/search', [], null];
        yield 'aliases' => ['GET', '/aliases', [], null];
        yield 'form posted' => ['POST', '/preview', ['Content-Type' => 'application/x-www-form-urlencoded'], 'title='.rawurlencode('Hello World')];
        yield 'JSON posted' => ['POST', '/preview', ['Content-Type' => 'application/json'], '{"title":"Ünïcode note"}'];
        yield 'nothing posted' => ['POST', '/preview', [], ''];
    }

    #[DataProvider('requests')]
    public function testEveryCellAnswersAsTheReference(string $method, string $path, array $headers, ?string $body): void
    {
        $cells = self::cells();
        $reference = array_key_first($cells);
        $expected = $this->ask($reference, $method, $path, $headers, $body);
        $this->assertLessThan(500, $expected['status'], "$reference answers $method $path with {$expected['status']}: the reference is broken.\n".substr($expected['body'], 0, 2000));

        $failures = [];
        foreach (array_slice(array_keys($cells), 1) as $cell) {
            $answer = $this->ask($cell, $method, $path, $headers, $body);
            if ($expected['status'] !== $answer['status']) {
                $failures[] = "$cell answers {$answer['status']}, $reference {$expected['status']}.".($answer['status'] >= 500 ? "\n".substr($answer['body'], 0, 2000) : '');
            } elseif ($expected['type'] !== $answer['type']) {
                $failures[] = "$cell answers in {$answer['type']}, $reference in {$expected['type']}.";
            } elseif (self::vary($expected) !== self::vary($answer)) {
                $failures[] = "$cell's Vary is '".self::vary($answer)."', $reference's '".self::vary($expected)."'.";
            } else {
                $diff = self::diff($expected['type'], $expected['body'], $answer['body'], $reference, $cell);
                if (null !== $diff) {
                    $failures[] = "$cell's body differs from $reference's (-, $cell +):\n".$diff;
                }
            }
        }
        $this->assertSame([], $failures, "$method $path:\n".implode("\n", $failures));
    }

    /**
     * No such page: a note, a topic or an address that is none. Every cell answers 404; the error page itself is each
     * framework's (differences.php, "error-pages"), so only the status is compared.
     */
    public function testNoSuchPageIsNotFoundOnEveryCell(): void
    {
        foreach (['/notes/no-such-note', '/topics/nothing', '/sujets/nothing', '/no/such/address', '/notes/UPPER'] as $path) {
            $statuses = [];
            foreach (array_keys(self::cells()) as $cell) {
                $statuses[$cell] = $this->ask($cell, 'GET', $path)['status'];
            }
            $this->assertSame(array_fill_keys(array_keys(self::cells()), 404), $statuses, $path);
        }
    }

    /**
     * A route the request's method does not allow (a POST-only action asked with GET, PUT): 405 on every cell, with the
     * methods it allows in `Allow`, as Symfony's router and Laravel's answer it. The error page itself is each
     * framework's ("error-pages"), so only the status and `Allow` are compared.
     */
    public function testAMethodTheRouteRefusesIsNotAllowedOnEveryCell(): void
    {
        foreach (['GET', 'PUT'] as $method) {
            foreach (array_keys(self::cells()) as $side) {
                $answer = $this->ask($side, $method, '/preview');
                $this->assertSame(405, $answer['status'], "$side: $method /preview");
                $this->assertSame('POST', $answer['headers']['allow'] ?? null, "$side: $method /preview's Allow");
            }
        }
    }

    /**
     * A page sent is counted in omnibase's analytics (analytics_page_view, on the one database) by every cell, once:
     * omnibridge/symfony's PageViewSubscriber and omnibridge/laravel's Omnibase\PageViews, once the page is sent,
     * through the persistence's own analytics repository (the native SQL of omnibridge/doctrine's, the #[BackendOnly]
     * methods written on Eloquent's connection). What is no page view - a JSON answer, a POST, a 404, a prefetch - is
     * counted by none. The count is read on the reference cell.
     */
    public function testEveryCellCountsAPageSentAndNothingElse(): void
    {
        $reference = array_key_first(self::cells());
        $views = fn (): int => (int) (json_decode($this->ask($reference, 'GET', '/page-views', [], null, true)['body'], true)['views'] ?? -1);

        foreach (array_reverse(array_keys(self::cells())) as $side) {
            $before = $views();
            $this->assertSame(200, $this->ask($side, 'GET', '/notes/green-tea', [], null, true)['status']);
            $this->assertSame($before + 1, $views(), "$side counts the page it sent");

            $this->ask($side, 'GET', '/notes/cafe-creme', ['Accept' => 'application/json'], null, true);
            $this->ask($side, 'POST', '/preview', ['Content-Type' => 'application/json'], '{"title":"Not a view"}', true);
            $this->ask($side, 'GET', '/notes/no-such-note', [], null, true);
            $this->ask($side, 'GET', '/notes/green-tea', ['Sec-Purpose' => 'prefetch'], null, true);
            $this->assertSame($before + 1, $views(), "$side counts no JSON answer, POST, 404 nor prefetch");
        }
    }

    /** The language follows the route's (_locale) on every cell, and each writes it back to LANG, as a site names it. */
    public function testTheLanguageIsTheRoutesAndIsKeptInLang(): void
    {
        foreach (['/topics/cuisine' => ['en', 'en-GB'], '/sujets/cuisine' => ['fr', 'fr-FR']] as $path => [$locale, $cookie]) {
            foreach (array_keys(self::cells()) as $side) {
                $answer = $this->ask($side, 'GET', $path);
                $this->assertSame($locale, json_decode($answer['body'], true)['locale'] ?? null, "$side: $path");
                $this->assertSame($cookie, $answer['cookies']['LANG'] ?? null, "$side: $path writes LANG");
            }
        }
    }

    /**
     * Each cell runs on the persistence it is named after (omnisense/omnibridge's Backend::name(), /persistence): a cell
     * that silently fell back to the other bridge would compare a persistence with itself.
     */
    public function testEveryCellRunsOnItsPersistence(): void
    {
        foreach (array_keys(self::cells()) as $cell) {
            $answer = $this->ask($cell, 'GET', '/persistence');
            $this->assertSame(200, $answer['status'], "$cell: GET /persistence\n".substr($answer['body'], 0, 2000));
            $this->assertSame(explode('/', $cell)[1], json_decode($answer['body'], true)['persistence'] ?? null, "$cell runs on another persistence");
        }
    }

    /**
     * What differences.php lists is the whole set of differences between the two hosts, and each still holds between
     * every Symfony cell and every Laravel cell, whichever persistence each runs on: an entry the hosts no longer need is
     * to leave the list (and docs/differences.md), so that the written list stays true. The two persistences of one
     * host have no listed difference: testEveryCellAnswersAsTheReference allows them none.
     */
    public function testEveryListedDifferenceStillHoldsAndIsDocumented(): void
    {
        $docs = (string) file_get_contents(\dirname(__DIR__, 2).'/docs/differences.md');
        $differences = require __DIR__.'/differences.php';
        $this->assertNotEmpty($differences);

        $hosts = [];
        foreach (array_keys(self::cells()) as $cell) {
            $hosts[explode('/', $cell)[0]][] = $cell;
        }
        $this->assertArrayHasKey('symfony', $hosts, 'No Symfony cell: the differences between the hosts are checked on none.');
        $this->assertArrayHasKey('laravel', $hosts, 'No Laravel cell: the differences between the hosts are checked on none.');

        foreach ($hosts['symfony'] as $symfony) {
            foreach ($hosts['laravel'] as $laravel) {
                $pair = ['symfony' => $symfony, 'laravel' => $laravel];
                $ask = fn (string $side, string $method, string $path, array $headers = [], ?string $body = null): array => $this->ask($pair[$side], $method, $path, $headers, $body, true);
                foreach ($differences as $id => $difference) {
                    $this->assertStringContainsString('`'.$id.'`', $docs, "differences.php's $id is explained in docs/differences.md");
                    $this->assertNotSame('', trim($difference['what']), "$id: what differs");
                    $this->assertTrue(($difference['check'])($ask), "$id no longer holds between $symfony and $laravel: remove it from tests/Parity/differences.php and docs/differences.md ({$difference['what']})");
                }
            }
        }
    }

    /** @return array<string, string> origin by cell (`host/persistence`), the reference first */
    private static function cells(): array
    {
        $cells = [];
        foreach (preg_split('~\s+~', trim((string) (getenv('PARITY_CELLS') ?: self::DEFAULT_CELLS)), -1, \PREG_SPLIT_NO_EMPTY) as $cell) {
            [$name, $origin] = explode('=', $cell, 2) + [1 => ''];
            if (!preg_match('~^(symfony|laravel)/(doctrine|eloquent)$~', $name) || '' === $origin) {
                throw new \UnexpectedValueException("PARITY_CELLS: '$cell' is no host/persistence=origin (symfony|laravel, doctrine|eloquent).");
            }
            $cells[$name] = rtrim($origin, '/');
        }
        if (\count($cells) < 2) {
            throw new \UnexpectedValueException('PARITY_CELLS names fewer than two cells: nothing to compare.');
        }

        return $cells;
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array{status: int, type: string, headers: array<string, string>, cookies: array<string, string>, body: string}
     */
    private function ask(string $side, string $method, string $path, array $headers = [], ?string $body = null, bool $fresh = false): array
    {
        $key = $side.' '.$method.' '.$path.' '.json_encode($headers).' '.$body;
        if (!$fresh && isset(self::$answers[$key])) {
            return self::$answers[$key];
        }
        $origin = self::cells()[$side];

        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name.': '.$value;
        }
        $received = [];
        $curl = curl_init($origin.$path);
        curl_setopt_array($curl, [
            \CURLOPT_CUSTOMREQUEST => $method,
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_FOLLOWLOCATION => false,
            \CURLOPT_TIMEOUT => 30,
            \CURLOPT_HTTPHEADER => $lines,
            \CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$received): int {
                if (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $received[] = [strtolower(trim($name)), trim($value)];
                }

                return \strlen($line);
            },
        ] + (null === $body ? [] : [\CURLOPT_POSTFIELDS => $body]));
        $content = curl_exec($curl);
        if (false === $content) {
            $this->fail("$side ($origin) does not answer $method $path: ".curl_error($curl));
        }
        $status = curl_getinfo($curl, \CURLINFO_RESPONSE_CODE);

        $named = [];
        $cookies = [];
        foreach ($received as [$name, $value]) {
            if ('set-cookie' === $name) {
                [$pair] = explode(';', $value, 2);
                [$cookie, $cookieValue] = explode('=', $pair, 2) + [1 => ''];
                $cookies[$cookie] = urldecode($cookieValue);
            } else {
                $named[$name] = $value;
            }
        }

        return self::$answers[$key] = [
            'status' => $status,
            'type' => strtolower(trim(explode(';', $named['content-type'] ?? '')[0])),
            'headers' => $named,
            'cookies' => $cookies,
            'body' => (string) $content,
        ];
    }

    /** @param array{headers: array<string, string>} $answer */
    private static function vary(array $answer): string
    {
        return strtolower(str_replace(' ', '', $answer['headers']['vary'] ?? ''));
    }

    /** The two bodies' differences (null: none), JSON compared by value, anything else as it is. */
    private static function diff(string $type, string $expected, string $actual, string $reference, string $cell): ?string
    {
        if ('application/json' === $type) {
            $a = json_decode($expected, true, 512, \JSON_THROW_ON_ERROR);
            $b = json_decode($actual, true, 512, \JSON_THROW_ON_ERROR);
            if ($a === $b) {
                return null;
            }
            $expected = json_encode($a, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE)."\n";
            $actual = json_encode($b, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE)."\n";
        } elseif ($expected === $actual) {
            return null;
        }

        return (new Differ(new UnifiedDiffOutputBuilder("--- $reference\n+++ $cell\n")))->diff($expected, $actual);
    }
}
