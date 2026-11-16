<?php

namespace App\Action;

use App\Entity\Comment;
use App\EntitySubscriber\CommentLifecycle;
use App\Repository\CommentRepository;
use App\Repository\NoteRepository;
use Base\Screen\Json;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A comment written, changed, read again and removed through App\Repository\CommentRepository, and what the
 * application's lifecycle listener (App\EntitySubscriber\CommentLifecycle, on omnibase's neutral events) heard of it at
 * each step: the same moments, in the same order, on every cell, and its stamp written before the insert. The row is
 * removed at the end (and a row a broken run left is purged first), so that the reference data stays as seeded.
 */
#[Route('/repository-lifecycle', name: 'repository_lifecycle', methods: ['POST'])]
final class RepositoryLifecycle
{
    private const CODE = 'lifecycle';

    public function __construct(
        private readonly CommentRepository $comments,
        private readonly NoteRepository $notes,
        private readonly CommentLifecycle $listener,
    ) {
    }

    public function __invoke(): Json
    {
        $steps = ['purged' => $this->comments->deleteBy(['code' => self::CODE])];
        $this->listener->heard();

        $comment = new Comment();
        $comment->code = self::CODE;
        $comment->note = $this->notes->oneBySlug('harbour-walk') ?? throw new \LogicException('The seed has no harbour-walk note.');
        $comment->body = 'Written by the lifecycle case.';
        $comment->createdAt = new \DateTimeImmutable('2026-03-06 10:00:00');
        $this->comments->persist($comment);
        $steps['persist'] = $this->listener->heard();
        $this->comments->flush();
        $steps['flush (insert)'] = $this->listener->heard();

        $comment->state = 'published';
        $comment->rating = 4;
        $this->comments->flush();
        $steps['flush (update)'] = $this->listener->heard();

        $this->comments->clear();
        $read = $this->comments->findOneBy(['code' => self::CODE]);
        $steps['read again'] = $this->listener->heard();
        $steps['as read'] = $read instanceof Comment ? ['state' => $read->state, 'rating' => $read->rating, 'stamp' => $read->stamp, 'note' => $read->note->slug] : null;

        if ($read instanceof Comment) {
            $this->comments->remove($read);
            $steps['remove'] = $this->listener->heard();
            $this->comments->flush();
            $steps['flush (delete)'] = $this->listener->heard();
        }
        $steps['left'] = $this->comments->count(['code' => self::CODE]);

        return new Json($steps);
    }
}
