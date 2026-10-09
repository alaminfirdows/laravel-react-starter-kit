<?php

namespace App\Checks;

use App\Checks\Support\PublicAddressGuard;
use App\Checks\Support\Target;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Base for checks that fetch one URL of the site. Only public https addresses:
 * redirects are followed by hand (max 3) and each hop is checked again.
 */
abstract class HttpCheck implements Check
{
    public const int TIMEOUT = 10;

    public const int MAX_REDIRECTS = 3;

    public function __construct(protected PublicAddressGuard $guard) {}

    abstract protected function path(): string;

    abstract protected function evaluate(Response $response, Target $target): CheckResult;

    public function run(string $url): CheckResult
    {
        $target = Target::from($url);

        if ($target === null) {
            return CheckResult::fail(__('":url" is not a valid website address.', ['url' => $url]));
        }

        $start = $target->origin().$this->path();
        $address = $start;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $response = $this->fetch($address);

            if ($response === null) {
                return CheckResult::fail(__('Could not reach :address.', ['address' => $start]));
            }

            if (! $response->redirect()) {
                return $response->successful()
                    ? $this->evaluate($response, $target)
                    : CheckResult::fail(__(':address returned HTTP :status.', ['address' => $start, 'status' => $response->status()]));
            }

            $address = (string) UriResolver::resolve(new Uri($address), new Uri($response->header('Location')));
        }

        return CheckResult::fail(__(':address redirects too many times.', ['address' => $start]));
    }

    /**
     * One request pinned to the vetted IP, so DNS cannot change between check and connect.
     */
    private function fetch(string $address): ?Response
    {
        $ip = $this->guard->resolve($address);

        if ($ip === null) {
            Log::warning('Site check blocked a non-public or non-https address.', ['address' => $address]);

            return null;
        }

        $host = (string) parse_url($address, PHP_URL_HOST);
        $port = parse_url($address, PHP_URL_PORT) ?? 443;
        $pinnedIp = str_contains($ip, ':') ? "[{$ip}]" : $ip;

        try {
            return Http::timeout(self::TIMEOUT)
                ->withUserAgent('FounderOS-Check/1.0')
                ->withoutRedirecting()
                ->withOptions(['curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$pinnedIp}"]]])
                ->get($address);
        } catch (ConnectionException $exception) {
            Log::info('Site check could not connect.', ['address' => $address, 'error' => $exception->getMessage()]);

            return null;
        }
    }
}
