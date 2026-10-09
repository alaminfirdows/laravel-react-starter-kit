<?php

namespace Tests\Fixtures;

use App\Checks\Support\DnsResolver;

/**
 * DNS without the network: IP literals resolve to themselves, other hosts to a public IP unless mapped.
 */
class FakeDnsResolver extends DnsResolver
{
    public const string PUBLIC_IP = '93.184.216.34';

    /**
     * @param  array<string, list<string>>  $map  host => addresses
     */
    public function __construct(public array $map = []) {}

    /**
     * @param  array<string, list<string>>  $map  host => addresses
     */
    public static function bind(array $map = []): self
    {
        $resolver = new self($map);
        app()->instance(DnsResolver::class, $resolver);

        return $resolver;
    }

    /**
     * @return list<string>
     */
    public function addresses(string $host): array
    {
        $literal = trim($host, '[]');

        return $this->map[$host] ?? (filter_var($literal, FILTER_VALIDATE_IP) !== false ? [$literal] : [self::PUBLIC_IP]);
    }
}
