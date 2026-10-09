<?php

namespace App\Checks\Support;

/**
 * Blocks site checks from reaching internal networks (SSRF): every address of
 * the host must be public. The caller pins the returned IP for the request.
 */
class PublicAddressGuard
{
    /**
     * IPv6 ranges the global-range filter lets through but that can reach internal hosts.
     *
     * @var list<array{0: string, 1: int}>
     */
    private const array BLOCKED_V6_PREFIXES = [
        ['ff00::', 8],          // multicast
        ['64:ff9b::', 96],      // NAT64 well-known (maps to any IPv4)
        ['64:ff9b:1::', 48],    // NAT64 local-use (RFC 8215)
        ['::ffff:0:0:0', 96],   // SIIT IPv4-translated
        ['fec0::', 10],         // deprecated site-local
    ];

    public function __construct(protected DnsResolver $dns) {}

    /**
     * The IP to connect to, or null when the URL is not https, does not resolve,
     * or any of its addresses is private, loopback, link-local, CGNAT, reserved or multicast.
     */
    public function resolve(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || ! is_string($host) || $host === '' || $this->isNumericHost($host)) {
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

        foreach (self::BLOCKED_V6_PREFIXES as [$prefix, $bits]) {
            if ($this->matchesPrefix($packed, (string) inet_pton($prefix), $bits)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Shorthand IPv4 forms ("2130706433", "0x7f.1", "127.1", "0177.0.0.1") that some
     * resolvers read as loopback. TLDs are never numeric, so no real host matches.
     */
    private function isNumericHost(string $host): bool
    {
        return filter_var($host, FILTER_VALIDATE_IP) === false
            && preg_match('/^(0x[0-9a-f]*|\d+)(\.(0x[0-9a-f]*|\d+))*\.?$/i', $host) === 1;
    }

    private function matchesPrefix(string $packed, string $prefix, int $bits): bool
    {
        $bytes = intdiv($bits, 8);
        $rest = $bits % 8;

        if (strncmp($packed, $prefix, $bytes) !== 0) {
            return false;
        }

        if ($rest === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $rest)) & 0xFF;

        return (ord($packed[$bytes]) & $mask) === (ord($prefix[$bytes]) & $mask);
    }
}
