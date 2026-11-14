<?php

namespace App;

use App\Entity\Note;
use Base\Database\Repository\Entities;
use Base\Database\Repository\Repositories;

/**
 * The notes, read through omnibase's Repositories: the persistence's own repository of the class, whichever the host
 * runs on (omnibridge/doctrine's DoctrineRepositories, omnibridge/eloquent's EloquentRepositories). Nothing of an ORM
 * is asked here, so that the same code is the reference application on every cell of the matrix.
 */
final class Notes
{
    public function __construct(private readonly Repositories $repositories)
    {
    }

    /** @return list<Note> the published notes, newest first, of a topic when one is given */
    public function latest(?string $topic = null, int $limit = 20): array
    {
        $criteria = null === $topic ? [] : ['topic' => $topic];

        return array_values($this->notes()->findBy($criteria, ['publishedAt' => 'DESC', 'id' => 'DESC'], $limit));
    }

    public function find(string $slug): ?Note
    {
        $note = $this->notes()->findOneBy(['slug' => $slug]);

        return $note instanceof Note ? $note : null;
    }

    /**
     * @return list<Note> the notes whose title holds these words (case-insensitive), by title: every note read by
     *                    title, the words looked for in PHP (Entities has criteria of equality only)
     */
    public function search(string $words): array
    {
        $words = mb_strtolower($words);

        return array_values(array_filter(
            $this->notes()->findBy([], ['title' => 'ASC']),
            static fn (Note $note): bool => str_contains(mb_strtolower($note->title), $words),
        ));
    }

    /** @return Entities<Note> */
    private function notes(): Entities
    {
        return $this->repositories->of(Note::class);
    }
}
