<?php

namespace App\Action;

use App\Notes;
use Base\Screen\Json;
use Symfony\Component\Routing\Attribute\Route;

/** The notes whose title holds the words asked (?q=), in JSON; fewer than two letters is no search (400). */
#[Route('/search', name: 'search')]
final class Search
{
    public function __construct(private readonly Notes $notes)
    {
    }

    /** @param array<string, mixed> $query */
    public function __invoke(array $query = []): Json
    {
        $words = trim((string) ($query['q'] ?? ''));
        if (mb_strlen($words) < 2) {
            return new Json(['error' => 'too short', 'q' => $words], 400);
        }

        return new Json(['q' => $words, 'results' => array_map(static fn ($note) => $note->slug, $this->notes->search($words))]);
    }
}
