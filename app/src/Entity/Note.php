<?php

namespace App\Entity;

use App\Repository\NoteRepository;
use Base\Mapping as ORM;

/**
 * The reference application's notes, each read by its slug. Mapped with omnibase's attributes (Base\Mapping), the same
 * class on every cell: omnibridge/doctrine's entity manager (DoctrineBundle's under Symfony, omnibridge/laravel's on
 * Laravel's connection) and omnibridge/eloquent's store, which reads the same mapping through omnisense/omnibridge's
 * schema description; its table is database/schema.sql's. Its repository, App\Repository\NoteRepository, is
 * omnibase's agnostic ServiceEntityRepository: the same class on every cell. Its author may be missing (null).
 */
#[ORM\Entity(repositoryClass: NoteRepository::class)]
#[ORM\Table(name: 'note')]
class Note
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    public string $slug = '';

    #[ORM\Column(length: 255)]
    public string $title = '';

    #[ORM\Column(type: 'text')]
    public string $body = '';

    #[ORM\Column(length: 32)]
    public string $topic = '';

    #[ORM\Column(name: 'publishedAt')]
    public \DateTimeImmutable $publishedAt;

    #[ORM\ManyToOne(targetEntity: Author::class)]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id', nullable: true)]
    public ?Author $author = null;

    /** @return array{slug: string, title: string, topic: string, publishedAt: string} */
    public function card(): array
    {
        return ['slug' => $this->slug, 'title' => $this->title, 'topic' => $this->topic, 'publishedAt' => $this->publishedAt->format(\DATE_ATOM)];
    }
}
