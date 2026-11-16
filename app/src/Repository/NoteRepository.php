<?php

namespace App\Repository;

use App\Entity\Note;
use Base\Database\Repository\ServiceEntityRepository;

/**
 * The notes: omnibase's agnostic repository (Base\Database\Repository\ServiceEntityRepository), the same class on
 * every cell of the matrix - its finders are compiled by the persistence the cell runs on (omnibridge/doctrine's DQL,
 * omnibridge/eloquent's queries), nothing of an ORM is named here. The pages read it (Home, Note, Topic, Search), and
 * the repositories suite asks it its plain finds, its counts, its criteria groups and its pages (App\Action\Repositories).
 *
 * @method Note|null find($id)
 * @method list<Note> findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 * @method Note|null findOneBy(array $criteria, ?array $orderBy = null)
 */
class NoteRepository extends ServiceEntityRepository
{
    /** @return list<Note> the notes, newest first (the same day: by id), of a topic when one is given */
    public function latest(?string $topic = null, int $limit = 20): array
    {
        return array_values($this->findBy(null === $topic ? [] : ['topic' => $topic], ['publishedAt' => 'DESC', 'id' => 'DESC'], $limit));
    }

    public function oneBySlug(string $slug): ?Note
    {
        $note = $this->findOneBy(['slug' => $slug]);

        return $note instanceof Note ? $note : null;
    }

    /** @return list<Note> the notes whose title holds these words, whatever their case, by title */
    public function search(string $words): array
    {
        $pattern = '%'.addcslashes(mb_strtolower($words), '%_\\').'%';

        return array_values($this->findByInsensitivePartialTitle($pattern, [], ['title' => 'ASC', 'id' => 'ASC'])->getResult());
    }
}
