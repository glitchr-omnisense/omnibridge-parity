<?php

namespace App\Action;

use App\Entity\Author;
use App\Entity\Comment;
use App\Entity\Note;
use App\Repository\CommentRepository;
use App\Repository\NoteRepository;
use Base\Database\Repository\CriteriaPages;
use Base\Database\Repository\Finder\Criterion;
use Base\Database\Repository\Finder\Group;
use Base\Database\Repository\Finder\Parser;
use Base\Database\Repository\Repositories as AllRepositories;
use Base\Database\Repository\UnsupportedQueryException;
use Base\Screen\Json;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The application's repositories asked one case at a time, in JSON: the same App\Repository classes (omnibase's agnostic
 * ServiceEntityRepository) on every cell, whose answers tests/Repositories/RepositoriesTest compares with the
 * reference cell's. Rows are named by their natural key (a note's slug, a comment's code, an author's handle), never
 * by an id. No such case: no such page.
 */
#[Route('/repositories/{case}', name: 'repositories', requirements: ['case' => '[a-z-]+'], methods: ['GET'])]
final class Repositories
{
    /** The cases, in the order the suite asks them. */
    public const CASES = [
        'find', 'find-by', 'find-by-list', 'count', 'named-finders', 'groups', 'paging', 'one-association-path',
        'two-association-path', 'null-criteria', 'empty-list-criteria', 'statement', 'repositories-of',
    ];

    public function __construct(
        private readonly NoteRepository $notes,
        private readonly CommentRepository $comments,
        private readonly AllRepositories $repositories,
    ) {
    }

    public function __invoke(string $case): ?Json
    {
        return \in_array($case, self::CASES, true) ? new Json(['case' => $case] + $this->{lcfirst(str_replace('-', '', ucwords($case, '-')))}()) : null;
    }

    /** find() by id, a missing id, a null id. */
    private function find(): array
    {
        $id = $this->notes->oneBySlug('night-train')?->id;

        return [
            'found' => self::slug($this->notes->find($id)),
            'missing' => self::slug($this->notes->find(987654)),
            'null' => self::slug($this->notes->find(null)),
        ];
    }

    /** findBy() / findOneBy() with an order on several fields, a limit, an offset. */
    private function findBy(): array
    {
        return [
            'cuisine, newest first' => self::slugs($this->notes->findBy(['topic' => 'cuisine'], ['publishedAt' => 'DESC', 'id' => 'DESC'])),
            'cuisine, 2 after the first' => self::slugs($this->notes->findBy(['topic' => 'cuisine'], ['publishedAt' => 'DESC', 'id' => 'DESC'], 2, 1)),
            'all, by topic then slug' => self::slugs($this->notes->findBy([], ['topic' => 'ASC', 'slug' => 'DESC'])),
            'one, oldest travel' => self::slug($this->notes->findOneBy(['topic' => 'travel'], ['publishedAt' => 'ASC'])),
            'one, nothing matches' => self::slug($this->notes->findOneBy(['topic' => 'nothing'])),
            'published comments, best rated' => self::codes($this->comments->findBy(['state' => 'published'], ['rating' => 'DESC', 'code' => 'ASC'], 3)),
        ];
    }

    /** A list as a criterion's value: one of these (IN), a value that matches nothing among them. */
    private function findByList(): array
    {
        return [
            'slugs' => self::slugs($this->notes->findBy(['slug' => ['first-snow', 'green-tea', 'no-such-note']], ['slug' => 'ASC'])),
            'states' => self::codes($this->comments->findBy(['state' => ['pending', 'spam']], ['code' => 'ASC'])),
            'notes as entities' => self::codes($this->comments->findBy(['note' => $this->notes->findBy(['topic' => 'travel'])], ['code' => 'ASC'])),
        ];
    }

    /** count() in the database, with the criteria findBy() reads. */
    private function count(): array
    {
        return [
            'notes' => $this->notes->count(),
            'cuisine notes' => $this->notes->count(['topic' => 'cuisine']),
            'published comments' => $this->comments->count(['state' => 'published']),
            'comments of two states' => $this->comments->count(['state' => ['pending', 'spam']]),
            'no such topic' => $this->notes->count(['topic' => 'nothing']),
        ];
    }

    /** The finder language: findBy<criteria>, findOneBy<criteria>, countBy<criteria>, comparisons, Result. */
    private function namedFinders(): array
    {
        return [
            'findByTopic' => self::slugs($this->notes->findByTopic('travel', [], ['slug' => 'ASC'])->getResult()),
            'findOneBySlug' => self::slug($this->notes->findOneBySlug('first-snow')),
            'countByTopic' => (int) $this->notes->countByTopic('cuisine'),
            'findByRatingGreaterThan' => self::codes($this->comments->findByRatingGreaterThan(3, [], ['code' => 'ASC'])->getResult()),
            'findByRatingNull' => self::codes($this->comments->findByRatingNull([], ['code' => 'ASC'])->getResult()),
            'findByStateAndRatingLowerEqualTo' => self::codes($this->comments->findByStateAndRatingLowerEqualTo('published', 3, [], ['code' => 'ASC'])->getResult()),
            'findByInsensitivePartialTitle' => self::slugs($this->notes->search('TEA')),
        ];
    }

    /** Criteria groups: parentheses from a name of the language (Group::by) and from members (Group::of). */
    private function groups(): array
    {
        return [
            'travel or season' => self::slugs($this->notes->findBy([
                Group::of(Parser::SEPARATOR_OR, Criterion::of('topic', 'travel'), Criterion::of('topic', 'season')),
            ], ['slug' => 'ASC'])),
            'published and (rated 5 or anonymous)' => self::codes($this->comments->findBy([
                'state' => 'published',
                Group::of(Parser::SEPARATOR_OR, Criterion::of('rating', 5), Criterion::of('author', null, Parser::OPTION_NULL)),
            ], ['code' => 'ASC'])),
            'title or body holds "tea" (Group::by)' => self::slugs($this->notes->findBy([
                Group::by('InsensitivePartialTitleOrInsensitivePartialBody', '%tea%', '%tea%'),
            ], ['slug' => 'ASC'])),
            'nested: cuisine and (rated over 3 or pending)' => self::codes($this->comments->findBy([
                'note.topic' => 'cuisine',
                Group::of(Parser::SEPARATOR_OR,
                    Criterion::of('rating', 3, Parser::OPTION_GREATER),
                    Group::of(Parser::SEPARATOR_AND, Criterion::of('state', 'pending'), Criterion::of('rating', null, Parser::OPTION_NOT_NULL)),
                ),
            ], ['code' => 'ASC'])),
            'counted' => $this->comments->count([Group::of(Parser::SEPARATOR_OR, Criterion::of('state', 'spam'), Criterion::of('rating', 1))]),
        ];
    }

    /** Pages: a finder's Result (count in the database, a slice) and omnibase's CriteriaPages. */
    private function paging(): array
    {
        $result = $this->comments->findByState('published', [], ['code' => 'ASC']);
        $pages = new CriteriaPages($this->comments, ['state' => 'published'], ['rating' => 'DESC', 'code' => 'ASC']);

        return [
            'result count' => $result->count(),
            'result slice 2,3' => self::codes($result->slice(2, 3)),
            'result slice past the end' => self::codes($result->slice(20, 5)),
            'pages count' => $pages->count(),
            'pages slice 0,4' => self::codes($pages->slice(0, 4)),
            'pages slice 4,4' => self::codes($pages->slice(4, 4)),
            'offset 5, limit 3' => self::codes($this->comments->findBy([], ['code' => 'ASC'], 3, 5)),
        ];
    }

    /** A path through one association: a criterion, an order, a group member, a count. */
    private function oneAssociationPath(): array
    {
        return [
            "ada's comments" => self::codes($this->comments->findBy(['author.handle' => 'ada'], ['code' => 'ASC'])),
            'on cuisine notes' => self::codes($this->comments->findBy(['note.topic' => 'cuisine'], ['code' => 'ASC'])),
            'by note slug then code' => self::codes($this->comments->findBy(['state' => 'published'], ['note.slug' => 'ASC', 'code' => 'DESC'])),
            'author name holds "co" (a group member)' => self::codes($this->comments->findBy([
                Group::of(Parser::SEPARATOR_AND, Criterion::of('author.name', '%co%', Parser::OPTION_INSENSITIVE, Parser::OPTION_PARTIAL)),
            ], ['code' => 'ASC'])),
            'notes by an author with no email' => self::slugs($this->notes->findBy(['author.email' => null], ['slug' => 'ASC'])),
            'counted on travel notes' => $this->comments->count(['note.topic' => 'travel']),
            'association and its path side by side' => self::codes($this->comments->findBy([
                'note' => $this->notes->oneBySlug('tea-ceremony'), 'author.handle' => 'colette',
            ])),
        ];
    }

    /**
     * A path through two associations (comment.note.author): two finders, the agnostic form (onNotesOf()); the path
     * itself, which only Doctrine compiles and Eloquent refuses aloud - never a silent wrong answer
     * (RepositoriesTest::testATwoAssociationPathIsAnsweredOrRefusedAloud).
     */
    private function twoAssociationPath(): array
    {
        try {
            $path = self::codes($this->comments->findBy(['note.author.handle' => 'ada'], ['code' => 'ASC']));
        } catch (UnsupportedQueryException) {
            $path = 'refused';
        }

        return [
            'two finders' => self::codes($this->comments->onNotesOf('ada')),
            'two finders, an author with no note' => self::codes($this->comments->onNotesOf('nobody')),
            'path' => $path,
        ];
    }

    /** `field => null` is IS NULL, on a field, on an association, on a path; with findBy, findOneBy and count. */
    private function nullCriteria(): array
    {
        return [
            'unrated comments' => self::codes($this->comments->findBy(['rating' => null], ['code' => 'ASC'])),
            'anonymous comments' => self::codes($this->comments->findBy(['author' => null], ['code' => 'ASC'])),
            'notes without author' => self::slugs($this->notes->findBy(['author' => null])),
            'first author without email' => $this->repositories->of(Author::class)->findOneBy(['email' => null], ['handle' => 'ASC'])?->handle,
            'anonymous and unrated, counted' => $this->comments->count(['author' => null, 'rating' => null]),
            'pending, anonymous' => self::codes($this->comments->findBy(['state' => 'pending', 'author' => null], ['code' => 'ASC'])),
        ];
    }

    /** `field => []` matches nothing: [] / null / 0, without a query. */
    private function emptyListCriteria(): array
    {
        return [
            'findBy' => self::slugs($this->notes->findBy(['slug' => []])),
            'findBy beside another criterion' => self::codes($this->comments->findBy(['state' => 'published', 'note' => []])),
            'findOneBy' => self::slug($this->notes->findOneBy(['slug' => []])),
            'count' => $this->comments->count(['code' => []]),
            'two finders over no note' => self::codes($this->comments->onNotesOf('nobody')),
        ];
    }

    /** A GROUP BY written once in the language (findValuesBy() with an aggregate). */
    private function statement(): array
    {
        return ['comments per note' => $this->comments->countPerNote()];
    }

    /**
     * omnibase's Repositories: an entity's own repository class (repositoryClass), the same service the container
     * gives, and for an entity without one the agnostic parent class, the finder language included.
     */
    private function repositoriesOf(): array
    {
        $authors = $this->repositories->of(Author::class);

        return [
            Note::class => $this->repositories->of(Note::class)::class,
            Comment::class => $this->repositories->of(Comment::class)::class,
            Author::class => $authors::class,
            'the same as the container gives' => $this->repositories->of(Note::class) instanceof NoteRepository,
            'author by handle' => $authors->findOneByHandle('basho')?->name,
            'authors counted' => $authors->count(),
            'by ids, sorted' => self::sorted(self::slugs($this->repositories->byIds(Note::class, array_map(static fn (Note $note): ?int => $note->id, $this->notes->findBy(['topic' => 'travel']))))),
            'one by id, missing' => self::slug($this->repositories->oneById(Note::class, 987654)),
        ];
    }

    /** @param list<string> $keys @return list<string> */
    private static function sorted(array $keys): array
    {
        sort($keys);

        return $keys;
    }

    private static function slug(?object $note): ?string
    {
        return $note instanceof Note ? $note->slug : null;
    }

    /** @param iterable<object> $notes @return list<string> */
    private static function slugs(iterable $notes): array
    {
        $slugs = [];
        foreach ($notes as $note) {
            $slugs[] = $note instanceof Note ? $note->slug : get_debug_type($note);
        }

        return $slugs;
    }

    /** @param iterable<object> $comments @return list<string> */
    private static function codes(iterable $comments): array
    {
        $codes = [];
        foreach ($comments as $comment) {
            $codes[] = $comment instanceof Comment ? $comment->code : get_debug_type($comment);
        }

        return $codes;
    }
}
