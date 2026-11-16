<?php

namespace App\Entity;

use Base\Mapping as ORM;

/**
 * A note's or a comment's author. It has no repository class of its own: Repositories::of() gives it omnibase's
 * agnostic ServiceEntityRepository, with the whole finder language, on every persistence (the repositories suite's
 * "repositories-of" case). An email may be missing (null), which the null criteria read.
 */
#[ORM\Entity]
#[ORM\Table(name: 'author')]
class Author
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column(name: 'id')]
    public ?int $id = null;

    #[ORM\Column(name: 'handle', length: 32, unique: true)]
    public string $handle = '';

    #[ORM\Column(name: 'name', length: 64)]
    public string $name = '';

    #[ORM\Column(name: 'email', length: 128, nullable: true)]
    public ?string $email = null;
}
