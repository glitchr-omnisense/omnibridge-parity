<?php

namespace App\Action;

use App\Notes;
use Base\Screen\Json;
use Symfony\Component\Routing\Attribute\Route;

/**
 * A topic's notes in JSON, at a localized address: one route per language (topic.en, topic.fr), the page's language
 * the route's. `array $query` is the query string's values, given by either host.
 */
#[Route(['en' => '/topics/{topic}', 'fr' => '/sujets/{topic}'], name: 'topic', requirements: ['topic' => '[a-z]+'])]
final class Topic
{
    public function __construct(private readonly Notes $notes)
    {
    }

    /** @param array<string, mixed> $query */
    public function __invoke(string $topic, string $_locale, array $query = []): ?Json
    {
        $limit = max(1, min(50, (int) ($query['limit'] ?? 20)));
        $notes = $this->notes->latest($topic, $limit);
        if ([] === $notes) {
            return null;
        }

        return new Json(['topic' => $topic, 'locale' => $_locale, 'limit' => $limit, 'notes' => array_map(static fn ($note) => $note->card(), $notes)]);
    }
}
