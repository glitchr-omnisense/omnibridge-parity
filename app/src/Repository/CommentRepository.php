<?php

namespace App\Repository;

use App\Entity\Comment;
use App\Entity\Note;
use Base\Database\Repository\Finder\Aggregate;
use Base\Database\Repository\ServiceEntityRepository;

/**
 * The comments: omnibase's agnostic repository, the same class on every cell. What the finder language says is
 * written once here, on every persistence: two finders (onNotesOf()), and a GROUP BY in the language
 * (countPerNote(): findValuesBy() with an aggregate).
 *
 * @method Comment|null find($id)
 * @method list<Comment> findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 * @method Comment|null findOneBy(array $criteria, ?array $orderBy = null)
 */
class CommentRepository extends ServiceEntityRepository
{
    /**
     * The comments on the notes an author wrote (comment.note.author.handle): a criterion through two associations,
     * which no persistence is asked to compile at 1.0 - the notes first, through one association, then their
     * comments by the list of notes.
     *
     * @return list<Comment> by code
     */
    public function onNotesOf(string $handle): array
    {
        $notes = $this->finders()->of(Note::class)->run('findBy', [['author.handle' => $handle]])->getResult();

        return array_values($this->findBy(['note' => $notes], ['code' => 'ASC']));
    }

    /**
     * The number of comments of each note that has some, by the note's slug: one GROUP BY in the database.
     *
     * @return array<string, int> slug => comments, by slug
     */
    public function countPerNote(): array
    {
        $counts = [];
        foreach ($this->findValuesBy(['note.slug', 'comments' => Aggregate::count()], [], ['note.slug' => 'ASC']) as $row) {
            if (null !== $row['note.slug']) {
                $counts[(string) $row['note.slug']] = (int) $row['comments'];
            }
        }

        return $counts;
    }
}
