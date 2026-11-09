<?php

namespace App\Action;

use Base\Screen\Json;
use Symfony\Component\Routing\Attribute\Route;

/**
 * What a form posted, read back: `array $body` is the request's JSON object, else its form fields, on either host.
 * Nothing is written: the reference application's database is read-only, so that both hosts read the same rows.
 */
#[Route('/preview', name: 'preview', methods: ['POST'])]
final class Preview
{
    /** @param array<string, mixed> $body */
    public function __invoke(array $body = []): Json
    {
        $title = trim((string) ($body['title'] ?? ''));

        return '' === $title
            ? new Json(['error' => 'a title is required'], 422)
            : new Json(['title' => $title, 'slug' => trim((string) preg_replace('~[^a-z0-9]+~', '-', strtolower($title)), '-')]);
    }
}
