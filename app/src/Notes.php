<?php

namespace App;

use App\Entity\Note;
use Doctrine\ORM\EntityManagerInterface;

/** The notes, read through Doctrine's entity manager: the one each host gives (its container autowires it). */
final class Notes
{
    public function __construct(private readonly EntityManagerInterface $entities)
    {
    }

    /** @return list<Note> the published notes, newest first, of a topic when one is given */
    public function latest(?string $topic = null, int $limit = 20): array
    {
        $criteria = null === $topic ? [] : ['topic' => $topic];

        return array_values($this->entities->getRepository(Note::class)->findBy($criteria, ['publishedAt' => 'DESC', 'id' => 'DESC'], $limit));
    }

    public function find(string $slug): ?Note
    {
        return $this->entities->getRepository(Note::class)->findOneBy(['slug' => $slug]);
    }

    /** @return list<Note> the notes whose title holds these words (case-insensitive), by title */
    public function search(string $words): array
    {
        return array_values($this->entities->createQueryBuilder()
            ->select('n')->from(Note::class, 'n')
            ->where('LOWER(n.title) LIKE :words')->setParameter('words', '%'.mb_strtolower($words).'%')
            ->orderBy('n.title', 'ASC')
            ->getQuery()->getResult());
    }
}
