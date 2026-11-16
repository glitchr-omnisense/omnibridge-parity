<?php

namespace App\Entity;

use App\Repository\CommentRepository;
use Base\Mapping as ORM;

/**
 * A comment on a note, by an author or anonymous (author null), rated or not (rating null): what the repositories
 * suite reads through App\Repository\CommentRepository - paths through one association (author.handle, note.topic),
 * a path through two (note.author.handle), null and empty-list criteria, a statement per persistence. Its code is the
 * key the suite compares (the ids of rows the lifecycle case writes differ from one cell to the next). The stamp is
 * written by App\EntitySubscriber\CommentLifecycle when the comment is persisted.
 */
#[ORM\Entity(repositoryClass: CommentRepository::class)]
#[ORM\Table(name: 'comment')]
class Comment
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(name: 'id')]
    public ?int $id = null;

    #[ORM\Column(name: 'code', length: 32, unique: true)]
    public string $code = '';

    #[ORM\ManyToOne(targetEntity: Note::class)]
    #[ORM\JoinColumn(name: 'note_id', referencedColumnName: 'id', nullable: false)]
    public Note $note;

    #[ORM\ManyToOne(targetEntity: Author::class)]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id', nullable: true)]
    public ?Author $author = null;

    #[ORM\Column(name: 'body', type: 'text')]
    public string $body = '';

    #[ORM\Column(name: 'rating', nullable: true)]
    public ?int $rating = null;

    #[ORM\Column(name: 'state', length: 16)]
    public string $state = 'pending';

    #[ORM\Column(name: 'stamp', length: 64, nullable: true)]
    public ?string $stamp = null;

    #[ORM\Column(name: 'createdAt')]
    public \DateTimeImmutable $createdAt;
}
