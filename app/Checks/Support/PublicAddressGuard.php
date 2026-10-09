<?php

namespace App\Checks\Support;

/**
 * Blocks site checks from reaching internal networks (SSRF): every address of
 * the host must be public. The caller pins the returned IP for the request.
 */
class PublicAddressGuard
{
    public function __construct(protected DnsResolver $dns) {}

    /**
     * The IP to connect to, or null when the URL is not https, does not resolve,
     * or any of its addresses is private, loopback, link-local, CGNAT, reserved or multicast.
     */
    public function resolve(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || ! is_string($host) || $host === '') {
            return null;
        }

        $addresses = $this->dns->addresses($host);

        if ($addresses === [] || array_filter($addresses, fn (string $ip): bool => ! $this->isPublic($ip)) !== []) {
            return null;
        }

        return $addresses[0];
    }

    public function isPublic(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) {
            return false;
        }

        $packed = (string) inet_pton($ip);

        if (strlen($packed) === 4) {
            // Multicast 224.0.0.0/4.
            return (ord($packed[0]) & 0xF0) !== 0xE0;
        }

        // Multicast ff00::/8 and NAT64 64:ff9b::/96 (maps to any IPv4).
        return $packed[0] !== "\xFF" && ! str_starts_with($packed, "\x00\x64\xFF\x9B".str_repeat("\x00", 8));
    }
}
