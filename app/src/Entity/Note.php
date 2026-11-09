<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * The reference application's one entity: a note, read by its slug. Mapped with Doctrine's attributes, the same
 * class under both hosts (omnibridge/doctrine under Symfony's DoctrineBundle, omnibridge/laravel's entity manager
 * on Laravel's connection); its table is database/schema.sql's.
 */
#[ORM\Entity]
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

    /** @return array{slug: string, title: string, topic: string, publishedAt: string} */
    public function card(): array
    {
        return ['slug' => $this->slug, 'title' => $this->title, 'topic' => $this->topic, 'publishedAt' => $this->publishedAt->format(\DATE_ATOM)];
    }
}
