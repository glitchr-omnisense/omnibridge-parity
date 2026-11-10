<?php

namespace Omnibridge\Parity\Tests\Parity;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;

/**
 * The reference application (app/) asked the same requests under Symfony (hosts/symfony: omnibridge/symfony,
 * omnibase's BaseBundle) and under Laravel (hosts/laravel: omnibridge/laravel), both on one MySQL database, and
 * the answers compared: the status, the media type and the body, byte for byte for a page, value for value for
 * JSON. What the two hosts are allowed to do differently is written in tests/Parity/differences.php, each entry
 * explained in docs/differences.md: the master plan's phase 4 gate, "behavioural tests pass under both
 * frameworks; differences documented". Nothing here boots either framework: both are asked over HTTP.
 *
 * Origins: PARITY_SYMFONY and PARITY_LARAVEL (compose's parity service sets them).
 */
final class ParityTest extends TestCase
{
    private const SLUGS = ['green-tea', 'cafe-creme', 'night-train', 'harbour-walk', 'tea-ceremony', 'first-snow'];

    /** @var array<string, array{status: int, type: string, headers: array<string, string>, cookies: array<string, string>, body: string}> answers, by side and request */
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
    public function testBothHostsAnswerAlike(string $method, string $path, array $headers, ?string $body): void
    {
        $symfony = $this->ask('symfony', $method, $path, $headers, $body);
        $laravel = $this->ask('laravel', $method, $path, $headers, $body);

        $this->assertLessThan(500, $symfony['status'], "Symfony answers $method $path with {$symfony['status']}: the reference is broken.\n".substr($symfony['body'], 0, 2000));
        $this->assertSame($symfony['status'], $laravel['status'], "$method $path: Symfony answers {$symfony['status']}, Laravel {$laravel['status']}.");
        $this->assertSame($symfony['type'], $laravel['type'], "$method $path: the media types differ.");
        $this->assertSame(self::vary($symfony), self::vary($laravel), "$method $path: Vary differs.");

        $diff = self::diff($symfony['type'], $symfony['body'], $laravel['body']);
        if (null !== $diff) {
            $this->fail("$method $path differs between Symfony (-) and Laravel (+):\n".$diff);
        }
    }

    /**
     * No such page: a note, a topic or an address that is none. Both hosts answer 404; the error page itself is each
     * framework's (differences.php, "error-pages"), so only the status is compared.
     */
    public function testNoSuchPageIsNotFoundOnBoth(): void
    {
        foreach (['/notes/no-such-note', '/topics/nothing', '/sujets/nothing', '/no/such/address', '/notes/UPPER'] as $path) {
            $this->assertSame(
                ['symfony' => 404, 'laravel' => 404],
                ['symfony' => $this->ask('symfony', 'GET', $path)['status'], 'laravel' => $this->ask('laravel', 'GET', $path)['status']],
                $path,
            );
        }
    }

    /**
     * A route the request's method does not allow (a POST-only action asked with GET, HEAD, PUT): 405 on both hosts,
     * with the methods it allows in `Allow`, as Symfony's router and Laravel's answer it. The error page itself is
     * each framework's ("error-pages"), so only the status and `Allow` are compared.
     */
    public function testAMethodTheRouteRefusesIsNotAllowedOnBoth(): void
    {
        foreach (['GET', 'PUT'] as $method) {
            foreach (['symfony', 'laravel'] as $side) {
                $answer = $this->ask($side, $method, '/preview');
                $this->assertSame(405, $answer['status'], "$side: $method /preview");
                $this->assertSame('POST', $answer['headers']['allow'] ?? null, "$side: $method /preview's Allow");
            }
        }
    }

    /**
     * A page sent is counted in omnibase's analytics (analytics_page_view, on the one database) by both hosts, once:
     * omnibridge/symfony's PageViewSubscriber and omnibridge/laravel's Omnibase\PageViews, once the page is sent.
     * What is no page view - a JSON answer, a POST, a 404, a prefetch - is counted by neither.
     */
    public function testBothHostsCountAPageSentAndNothingElse(): void
    {
        $views = fn (): int => (int) (json_decode($this->ask('symfony', 'GET', '/page-views', [], null, true)['body'], true)['views'] ?? -1);

        foreach (['laravel', 'symfony'] as $side) {
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

    /** The language follows the route's (_locale) on both hosts, and both write it back to LANG, as a site names it. */
    public function testTheLanguageIsTheRoutesAndIsKeptInLang(): void
    {
        foreach (['/topics/cuisine' => ['en', 'en-GB'], '/sujets/cuisine' => ['fr', 'fr-FR']] as $path => [$locale, $cookie]) {
            foreach (['symfony', 'laravel'] as $side) {
                $answer = $this->ask($side, 'GET', $path);
                $this->assertSame($locale, json_decode($answer['body'], true)['locale'] ?? null, "$side: $path");
                $this->assertSame($cookie, $answer['cookies']['LANG'] ?? null, "$side: $path writes LANG");
            }
        }
    }

    /**
     * What differences.php lists is the whole set of differences, and each still holds: an entry the hosts no longer
     * need is to leave the list (and docs/differences.md), so that the written list stays true.
     */
    public function testEveryListedDifferenceStillHoldsAndIsDocumented(): void
    {
        $docs = (string) file_get_contents(\dirname(__DIR__, 2).'/docs/differences.md');
        $ask = fn (string $side, string $method, string $path, array $headers = [], ?string $body = null): array => $this->ask($side, $method, $path, $headers, $body, true);
        $differences = require __DIR__.'/differences.php';
        $this->assertNotEmpty($differences);
        foreach ($differences as $id => $difference) {
            $this->assertStringContainsString('`'.$id.'`', $docs, "differences.php's $id is explained in docs/differences.md");
            $this->assertNotSame('', trim($difference['what']), "$id: what differs");
            $this->assertTrue(($difference['check'])($ask), "$id no longer holds: remove it from tests/Parity/differences.php and docs/differences.md ({$difference['what']})");
        }
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
        $origin = rtrim((string) (getenv('symfony' === $side ? 'PARITY_SYMFONY' : 'PARITY_LARAVEL') ?: ('symfony' === $side ? 'http://localhost:8701' : 'http://localhost:8702')), '/');

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
    private static function diff(string $type, string $symfony, string $laravel): ?string
    {
        if ('application/json' === $type) {
            $a = json_decode($symfony, true, 512, \JSON_THROW_ON_ERROR);
            $b = json_decode($laravel, true, 512, \JSON_THROW_ON_ERROR);
            if ($a === $b) {
                return null;
            }
            $symfony = json_encode($a, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE)."\n";
            $laravel = json_encode($b, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE)."\n";
        } elseif ($symfony === $laravel) {
            return null;
        }

        return (new Differ(new UnifiedDiffOutputBuilder("--- symfony\n+++ laravel\n")))->diff($symfony, $laravel);
    }
}
