<?php

namespace App\Checks;

/**
 * Checks by key, as used in `task_actions.config.check` and criteria `check_ref`.
 */
class CheckRegistry
{
    /**
     * @var array<string, class-string<Check>>
     */
    public const array CHECKS = [
        'https' => HttpsCheck::class,
        'dns_spf' => DnsSpfCheck::class,
        'sitemap' => SitemapCheck::class,
        'robots' => RobotsCheck::class,
    ];

    public function find(string $key): ?Check
    {
        $class = self::CHECKS[$key] ?? null;

        return $class === null ? null : app($class);
    }
}
