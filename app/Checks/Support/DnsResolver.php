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
}
