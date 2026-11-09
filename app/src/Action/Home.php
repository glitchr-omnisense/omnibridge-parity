<?php

namespace App\Action;

use App\Notes;
use Base\Screen\Template;
use Symfony\Component\Routing\Attribute\Route;

/** The home: the latest notes, a page of its own markup (the host adds nothing around it). */
#[Route('/', name: 'home')]
final class Home
{
    public function __construct(private readonly Notes $notes)
    {
    }

    public function __invoke(): Template
    {
        return new Template('home.html.twig', ['notes' => array_map(static fn ($note) => $note->card(), $this->notes->latest())], json: true, alone: true);
    }
}
