<?php

namespace App\Action;

use App\Notes;
use Base\Screen\Template;
use Symfony\Component\Routing\Attribute\Route;

/** A note's page, or no such page (null: a 404 on both hosts). JSON for a client that prefers it. */
#[Route('/notes/{slug}', name: 'note', requirements: ['slug' => '[a-z0-9-]+'])]
final class Note
{
    public function __construct(private readonly Notes $notes)
    {
    }

    public function __invoke(string $slug): ?Template
    {
        $note = $this->notes->find($slug);
        if (null === $note) {
            return null;
        }

        return new Template('note.html.twig', ['note' => $note->card() + ['body' => $note->body]], json: true, alone: true);
    }
}
