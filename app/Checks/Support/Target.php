<?php

namespace App\Checks\Support;

use Illuminate\Support\Str;

/**
 * Normalises a founder-typed website ("acme.com", "http://acme.com/x") to host and https origin.
 */
final readonly class Target
{
    public function __construct(public string $host) {}

    public static function from(string $url): ?self
    {
        $url = trim($url);
        $host = parse_url(Str::contains($url, '://') ? $url : "https://{$url}", PHP_URL_HOST);

        return is_string($host) && str_contains($host, '.') ? new self(strtolower($host)) : null;
    }

    public function origin(): string
    {
        return "https://{$this->host}";
    }

    public function apexHost(): string
    {
        return Str::after($this->host, 'www.');
    }
}
