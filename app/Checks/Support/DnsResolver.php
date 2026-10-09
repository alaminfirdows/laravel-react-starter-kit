<?php

namespace App\Checks\Support;

/**
 * Thin seam over PHP DNS lookups so tests can swap it.
 */
class DnsResolver
{
    /**
     * @return list<string>
     */
    public function txt(string $host): array
    {
        $records = @dns_get_record($host, DNS_TXT);

        if (! is_array($records)) {
            return [];
        }

        return array_map(fn (array $record): string => (string) ($record['txt'] ?? ''), $records);
    }

    /**
     * IPv4 and IPv6 addresses of a host; an IP literal resolves to itself.
     *
     * @return list<string>
     */
    public function addresses(string $host): array
    {
        $host = trim($host, '[]');

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        if (! is_array($records)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (array $record): string => (string) ($record['ip'] ?? $record['ipv6'] ?? ''),
            $records,
        )));
    }
}
