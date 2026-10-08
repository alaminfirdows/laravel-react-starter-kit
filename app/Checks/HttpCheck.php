<?php

namespace App\Checks;

use App\Checks\Support\Target;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Base for checks that fetch one URL of the site.
 */
abstract class HttpCheck implements Check
{
    public const int TIMEOUT = 10;

    abstract protected function path(): string;

    abstract protected function evaluate(Response $response, Target $target): CheckResult;

    public function run(string $url): CheckResult
    {
        $target = Target::from($url);

        if ($target === null) {
            return CheckResult::fail(__('":url" is not a valid website address.', ['url' => $url]));
        }

        $address = $target->origin().$this->path();

        try {
            $response = Http::timeout(self::TIMEOUT)->withUserAgent('FounderOS-Check/1.0')->get($address);
        } catch (ConnectionException $e) {
            return CheckResult::fail(__('Could not reach :address: :error', ['address' => $address, 'error' => $e->getMessage()]));
        }

        if (! $response->successful()) {
            return CheckResult::fail(__(':address returned HTTP :status.', ['address' => $address, 'status' => $response->status()]));
        }

        return $this->evaluate($response, $target);
    }
}
