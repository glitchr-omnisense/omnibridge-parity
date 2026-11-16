<?php

namespace Omnibridge\Parity\Tests;

/**
 * The cells of the matrix host x persistence and a plain HTTP client to ask them, for the suites that are not
 * ParityTest (which keeps its own, with its cache of answers): PARITY_CELLS, `host/persistence=origin` separated by
 * spaces, the reference first; without it, the four cells as `make up` publishes them on the machine.
 */
final class Cells
{
    public const DEFAULT_CELLS = 'symfony/doctrine=http://localhost:8701 laravel/doctrine=http://localhost:8702 symfony/eloquent=http://localhost:8705 laravel/eloquent=http://localhost:8706';

    /** @return array<string, string> origin by cell (`host/persistence`), the reference first */
    public static function all(): array
    {
        $cells = [];
        foreach (preg_split('~\s+~', trim((string) (getenv('PARITY_CELLS') ?: self::DEFAULT_CELLS)), -1, \PREG_SPLIT_NO_EMPTY) as $cell) {
            [$name, $origin] = explode('=', $cell, 2) + [1 => ''];
            if (!preg_match('~^(symfony|laravel)/(doctrine|eloquent)$~', $name) || '' === $origin) {
                throw new \UnexpectedValueException("PARITY_CELLS: '$cell' is no host/persistence=origin (symfony|laravel, doctrine|eloquent).");
            }
            $cells[$name] = rtrim($origin, '/');
        }
        if (\count($cells) < 2) {
            throw new \UnexpectedValueException('PARITY_CELLS names fewer than two cells: nothing to compare.');
        }

        return $cells;
    }

    /**
     * One request to a cell: its status and its body.
     *
     * @param array<string, string> $headers
     *
     * @return array{status: int, body: string}|null null when the cell does not answer
     */
    public static function ask(string $cell, string $method, string $path, array $headers = [], ?string $body = null): ?array
    {
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name.': '.$value;
        }
        $curl = curl_init(self::all()[$cell].$path);
        curl_setopt_array($curl, [
            \CURLOPT_CUSTOMREQUEST => $method,
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_FOLLOWLOCATION => false,
            \CURLOPT_TIMEOUT => 30,
            \CURLOPT_HTTPHEADER => $lines,
        ] + (null === $body ? [] : [\CURLOPT_POSTFIELDS => $body]));
        $content = curl_exec($curl);
        if (false === $content) {
            return null;
        }

        return ['status' => curl_getinfo($curl, \CURLINFO_RESPONSE_CODE), 'body' => (string) $content];
    }
}
