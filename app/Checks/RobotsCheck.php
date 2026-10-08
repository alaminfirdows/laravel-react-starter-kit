<?php

namespace App\Checks;

use App\Checks\Support\Target;
use Illuminate\Http\Client\Response;

class RobotsCheck extends HttpCheck
{
    public function label(): string
    {
        return __('robots.txt allows crawling');
    }

    protected function path(): string
    {
        return '/robots.txt';
    }

    protected function evaluate(Response $response, Target $target): CheckResult
    {
        if ($this->blocksEverything($response->body())) {
            return CheckResult::fail(__('robots.txt blocks all crawlers with "Disallow: /".'));
        }

        return CheckResult::pass(__('robots.txt found and allows crawling.'));
    }

    private function blocksEverything(string $robots): bool
    {
        $forAll = false;

        foreach (preg_split('/\R/', $robots) ?: [] as $line) {
            $line = strtolower(trim((string) preg_replace('/#.*/', '', $line)));

            if (str_starts_with($line, 'user-agent:')) {
                $forAll = trim(substr($line, 11)) === '*';
            } elseif ($forAll && preg_match('/^disallow:\s*\/\s*$/', $line) === 1) {
                return true;
            }
        }

        return false;
    }
}
