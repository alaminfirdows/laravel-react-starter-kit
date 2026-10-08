<?php

namespace App\Checks;

use App\Checks\Support\DnsResolver;
use App\Checks\Support\Target;

class DnsSpfCheck implements Check
{
    public function __construct(protected DnsResolver $dns) {}

    public function label(): string
    {
        return __('SPF record set');
    }

    public function run(string $url): CheckResult
    {
        $target = Target::from($url);

        if ($target === null) {
            return CheckResult::fail(__('":url" is not a valid website address.', ['url' => $url]));
        }

        $spf = array_values(array_filter($this->dns->txt($target->apexHost()), fn (string $txt): bool => str_starts_with(strtolower($txt), 'v=spf1')));

        return match (count($spf)) {
            0 => CheckResult::fail(__('No SPF record on :host.', ['host' => $target->apexHost()])),
            1 => CheckResult::pass(__('SPF record: :record', ['record' => $spf[0]])),
            default => CheckResult::fail(__(':host has :count SPF records; keep one.', ['host' => $target->apexHost(), 'count' => count($spf)])),
        };
    }
}
