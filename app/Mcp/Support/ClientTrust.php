<?php

namespace App\Mcp\Support;

use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Normalizer;

/**
 * Which OAuth clients may claim a Claude or Founder OS name, and how the consent screen labels them.
 */
class ClientTrust
{
    /**
     * Origins of the hosted Claude connector callbacks.
     */
    public const array CLAUDE_ORIGINS = ['https://claude.ai', 'https://claude.com'];

    /**
     * Custom scheme of the Claude desktop app.
     */
    public const string CLAUDE_SCHEME = 'claude';

    /**
     * Non-Latin letters that look like the Latin letters of the reserved names.
     */
    private const array HOMOGLYPHS = [
        'а' => 'a', 'А' => 'a', 'α' => 'a', 'Α' => 'a',
        'с' => 'c', 'С' => 'c', 'ϲ' => 'c', 'ⅽ' => 'c',
        'ԁ' => 'd', 'ⅾ' => 'd',
        'е' => 'e', 'Е' => 'e', 'ε' => 'e', 'Ε' => 'e',
        'ƒ' => 'f',
        'і' => 'i', 'І' => 'l', 'ӏ' => 'l', 'Ӏ' => 'l', 'ǀ' => 'l', 'ⅼ' => 'l', 'Ι' => 'l',
        'ո' => 'n', 'п' => 'n',
        'о' => 'o', 'О' => 'o', 'ο' => 'o', 'Ο' => 'o',
        'г' => 'r',
        'ѕ' => 's', 'Ѕ' => 's',
        'υ' => 'u', 'ս' => 'u',
    ];

    public function isReservedName(string $name): bool
    {
        $name = $this->skeleton($name);

        /** @var list<string> $reserved */
        $reserved = config('mcp.reserved_client_names', []);

        return collect($reserved)->contains(fn (string $reservedName): bool => Str::contains($name, $this->skeleton($reservedName)));
    }

    /**
     * Lookalike-proof form: NFKC (fullwidth), Cyrillic/Greek homoglyphs, ASCII, digit swaps, letters only.
     */
    private function skeleton(string $name): string
    {
        $name = strtr(Normalizer::normalize($name, Normalizer::FORM_KC) ?: $name, self::HOMOGLYPHS);
        $name = strtr(Str::lower(Str::ascii($name)), ['0' => 'o', '1' => 'l', '3' => 'e', '4' => 'a', '5' => 's', '|' => 'l', '!' => 'i']);

        return (string) preg_replace('/[^a-z]/', '', $name);
    }

    /**
     * Each redirect goes to a Claude origin, the Claude app scheme or the user's own machine (loopback).
     *
     * @param  array<int, mixed>  $redirectUris
     */
    public function hasClaudeRedirects(array $redirectUris): bool
    {
        return $redirectUris !== [] && collect($redirectUris)->every(
            fn (mixed $uri): bool => is_string($uri) && $this->isClaudeRedirect($uri),
        );
    }

    public function isVerified(Client $client): bool
    {
        return $this->isReservedName((string) $client->name) && $this->hasClaudeRedirects($this->redirectUris($client));
    }

    /**
     * @return list<string>
     */
    public function redirectUris(Client $client): array
    {
        $uris = $client->getAttribute('redirect_uris');

        return is_array($uris) ? array_values(array_filter($uris, is_string(...))) : [];
    }

    /**
     * Display form of a redirect target: "claude.ai", "localhost:3000" or "claude://claude.ai".
     */
    public function host(string $uri): string
    {
        $scheme = parse_url($uri, PHP_URL_SCHEME);
        $host = (string) parse_url($uri, PHP_URL_HOST);
        $port = parse_url($uri, PHP_URL_PORT);
        $hostWithPort = $port === null || $port === false ? $host : "{$host}:{$port}";

        return in_array($scheme, ['http', 'https'], true) ? $hostWithPort : "{$scheme}://{$hostWithPort}";
    }

    private function isClaudeRedirect(string $uri): bool
    {
        $scheme = parse_url($uri, PHP_URL_SCHEME);
        $host = parse_url($uri, PHP_URL_HOST);

        if (! is_string($scheme) || ! is_string($host) || parse_url($uri, PHP_URL_USER) !== null) {
            return false;
        }

        if ($scheme === self::CLAUDE_SCHEME) {
            return true;
        }

        if ($scheme === 'http') {
            return in_array($host, ['localhost', '127.0.0.1', '[::1]'], true);
        }

        $port = parse_url($uri, PHP_URL_PORT);

        return $scheme === 'https' && ($port === null || $port === 443) && in_array("https://{$host}", self::CLAUDE_ORIGINS, true);
    }
}
