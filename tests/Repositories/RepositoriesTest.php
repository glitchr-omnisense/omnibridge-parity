<?php

namespace Omnibridge\Parity\Tests\Repositories;

use Omnibridge\Parity\Tests\Cells;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The repositories cell: the reference application's repositories (app/src/Repository - App\Repository\NoteRepository
 * and CommentRepository, omnibase's agnostic Base\Database\Repository\ServiceEntityRepository - and the parent class
 * itself for App\Entity\Author, which has none) asked the same cases on every cell of the matrix, Symfony and Laravel,
 * each on omnibridge/doctrine and on omnibridge/eloquent, over HTTP (App\Action\Repositories, /repositories/{case}),
 * and every answer compared with the reference cell's: plain finds, orders, limits and offsets, lists (IN), counts in
 * the database, the finder language, criteria groups, pages (a finder's Result, CriteriaPages), paths through one
 * association, a path through two (two finders, and the path itself), `null` and `[]` criteria, a GROUP BY written
 * once (CommentRepository::countPerNote(), findValuesBy()), Repositories::of(); then a comment written, changed, read and removed with the
 * application's listener of the neutral lifecycle events (App\EntitySubscriber\CommentLifecycle) hearing the same
 * moments on each cell. The reference's answers are checked against the seed (database/seed.sql) too: four cells
 * agreeing on a wrong answer are no proof.
 */
final class RepositoriesTest extends TestCase
{
    /** App\Action\Repositories::CASES: the suite does not load the application. */
    private const CASES = [
        'find', 'find-by', 'find-by-list', 'count', 'named-finders', 'groups', 'paging', 'one-association-path',
        'two-association-path', 'null-criteria', 'empty-list-criteria', 'statement', 'repositories-of',
    ];

    /** What the reference must answer, from database/seed.sql (a subset of each case's keys). */
    private const EXPECTED = [
        'find' => ['found' => 'night-train', 'missing' => null, 'null' => null],
        'find-by' => [
            'cuisine, newest first' => ['cafe-creme', 'green-tea', 'tea-ceremony'],
            'cuisine, 2 after the first' => ['green-tea', 'tea-ceremony'],
            'all, by topic then slug' => ['tea-ceremony', 'green-tea', 'cafe-creme', 'first-snow', 'night-train', 'harbour-walk'],
            'one, oldest travel' => 'night-train',
            'one, nothing matches' => null,
            'published comments, best rated' => ['c01', 'c06', 'c09'],
        ],
        'find-by-list' => [
            'slugs' => ['first-snow', 'green-tea'],
            'states' => ['c03', 'c05', 'c07', 'c11'],
            'notes as entities' => ['c06', 'c07', 'c08'],
        ],
        'count' => ['notes' => 6, 'cuisine notes' => 3, 'published comments' => 8, 'comments of two states' => 4, 'no such topic' => 0],
        'named-finders' => [
            'findByTopic' => ['harbour-walk', 'night-train'],
            'findOneBySlug' => 'first-snow',
            'countByTopic' => 3,
            'findByRatingGreaterThan' => ['c01', 'c04', 'c06', 'c08', 'c09'],
            'findByRatingNull' => ['c02', 'c07', 'c11'],
            'findByStateAndRatingLowerEqualTo' => ['c10', 'c12'],
            'findByInsensitivePartialTitle' => ['green-tea', 'tea-ceremony'],
        ],
        'groups' => [
            'travel or season' => ['first-snow', 'harbour-walk', 'night-train'],
            'published and (rated 5 or anonymous)' => ['c01', 'c06', 'c09'],
            'title or body holds "tea" (Group::by)' => ['green-tea', 'tea-ceremony'],
            'nested: cuisine and (rated over 3 or pending)' => ['c01', 'c03', 'c04', 'c09'],
            'counted' => 2,
        ],
        'paging' => [
            'result count' => 8,
            'result slice 2,3' => ['c04', 'c06', 'c08'],
            'result slice past the end' => [],
            'pages count' => 8,
            'pages slice 0,4' => ['c01', 'c06', 'c09', 'c04'],
            'pages slice 4,4' => ['c08', 'c12', 'c10', 'c02'],
            'offset 5, limit 3' => ['c06', 'c07', 'c08'],
        ],
        'one-association-path' => [
            "ada's comments" => ['c04', 'c07', 'c09'],
            'on cuisine notes' => ['c01', 'c02', 'c03', 'c04', 'c05', 'c09', 'c10', 'c11'],
            'by note slug then code' => ['c04', 'c12', 'c02', 'c01', 'c08', 'c06', 'c10', 'c09'],
            'author name holds "co" (a group member)' => ['c02', 'c06', 'c10'],
            'counted on travel notes' => 3,
            'association and its path side by side' => ['c10'],
        ],
        'two-association-path' => ['two finders' => ['c01', 'c02', 'c03', 'c08'], 'two finders, an author with no note' => []],
        'null-criteria' => [
            'unrated comments' => ['c02', 'c07', 'c11'],
            'anonymous comments' => ['c03', 'c11'],
            'notes without author' => ['first-snow'],
            'first author without email' => 'basho',
            'anonymous and unrated, counted' => 1,
            'pending, anonymous' => ['c03', 'c11'],
        ],
        'empty-list-criteria' => ['findBy' => [], 'findBy beside another criterion' => [], 'findOneBy' => null, 'count' => 0, 'two finders over no note' => []],
        'statement' => ['comments per note' => ['cafe-creme' => 2, 'first-snow' => 1, 'green-tea' => 3, 'harbour-walk' => 1, 'night-train' => 2, 'tea-ceremony' => 3]],
        'repositories-of' => [
            'App\Entity\Note' => 'App\Repository\NoteRepository',
            'App\Entity\Comment' => 'App\Repository\CommentRepository',
            'App\Entity\Author' => 'Base\Database\Repository\ServiceEntityRepository',
            'the same as the container gives' => true,
            'author by handle' => 'Matsuo Bashō',
            'authors counted' => 3,
            'by ids, sorted' => ['harbour-walk', 'night-train'],
            'one by id, missing' => null,
        ],
    ];

    /** Keys whose answer may differ by persistence, each checked by a test of its own. */
    private const PER_PERSISTENCE = ['two-association-path' => ['path']];

    /** @return iterable<string, array{string}> */
    public static function cases(): iterable
    {
        foreach (self::CASES as $case) {
            yield $case => [$case];
        }
    }

    #[DataProvider('cases')]
    public function testEveryCellAnswersAsTheReference(string $case): void
    {
        $cells = Cells::all();
        $reference = array_key_first($cells);
        $expected = $this->json($reference, '/repositories/'.$case);
        foreach (self::EXPECTED[$case] ?? [] as $key => $value) {
            $this->assertArrayHasKey($key, $expected, "$reference, $case");
            $this->assertSame($value, $expected[$key], "$reference, $case: \"$key\" is not what database/seed.sql gives");
        }

        $failures = [];
        foreach (array_slice(array_keys($cells), 1) as $cell) {
            $answer = $this->json($cell, '/repositories/'.$case);
            foreach (self::PER_PERSISTENCE[$case] ?? [] as $key) {
                unset($answer[$key], $expected[$key]);
            }
            if ($answer !== $expected) {
                $failures[] = "$cell answers ".self::pretty($answer)."\n$reference answers ".self::pretty($expected);
            }
        }
        $this->assertSame([], $failures, "/repositories/$case:\n".implode("\n", $failures));
    }

    /**
     * A criterion through two associations (note.author.handle) is no part of the agnostic language at 1.0: a cell
     * either answers it as the two finders do, or refuses it aloud (UnsupportedQueryException) - never another list.
     */
    public function testATwoAssociationPathIsAnsweredOrRefusedAloud(): void
    {
        foreach (array_keys(Cells::all()) as $cell) {
            $answer = $this->json($cell, '/repositories/two-association-path');
            $this->assertContains($answer['path'] ?? 'missing', ['refused', $answer['two finders'] ?? []], "$cell answers the two-association path with another list than its two finders'");
        }
    }

    public function testNoSuchCaseIsNotFoundOnEveryCell(): void
    {
        foreach (array_keys(Cells::all()) as $cell) {
            $this->assertSame(404, Cells::ask($cell, 'GET', '/repositories/no-such-case')['status'] ?? null, $cell);
        }
    }

    /**
     * A comment persisted, flushed, changed, read again and removed, cell after cell: the application's lifecycle
     * listener hears the same moments at the same steps everywhere, and its stamp, written before the insert, is read
     * back from the database. Each cell removes its row, so the next starts from the seed.
     */
    public function testTheLifecycleListenerHearsTheSameMomentsOnEveryCell(): void
    {
        $expected = [
            'purged' => 0,
            'persist' => ['PrePersist'],
            'flush (insert)' => ['PostPersist'],
            'flush (update)' => ['PreUpdate', 'PostUpdate'],
            'read again' => ['PostLoad'],
            'as read' => ['state' => 'published', 'rating' => 4, 'stamp' => 'stamped before its insert', 'note' => 'harbour-walk'],
            'remove' => ['PreRemove'],
            'flush (delete)' => ['PostRemove'],
            'left' => 0,
        ];
        $failures = [];
        foreach (array_keys(Cells::all()) as $cell) {
            $answer = $this->json($cell, '/repository-lifecycle', 'POST');
            if ($answer !== $expected) {
                $failures[] = "$cell: ".self::pretty($answer);
            }
        }
        $this->assertSame([], $failures, "Expected on every cell: ".self::pretty($expected)."\n".implode("\n", $failures));
    }

    /** @return array<string, mixed> */
    private function json(string $cell, string $path, string $method = 'GET'): array
    {
        $answer = Cells::ask($cell, $method, $path, ['Accept' => 'application/json']);
        $this->assertNotNull($answer, "$cell does not answer $method $path");
        $this->assertSame(200, $answer['status'], "$cell answers $method $path with {$answer['status']}:\n".substr($answer['body'], 0, 3000));
        $data = json_decode($answer['body'], true);
        $this->assertIsArray($data, "$cell: $method $path is no JSON object:\n".substr($answer['body'], 0, 3000));

        return $data;
    }

    private static function pretty(mixed $value): string
    {
        return (string) json_encode($value, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }
}
