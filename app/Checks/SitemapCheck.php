<?php

namespace App\Checks;

use App\Checks\Support\Target;
use Illuminate\Http\Client\Response;

class SitemapCheck extends HttpCheck
{
    public function label(): string
    {
        return __('Sitemap exists');
    }

    protected function path(): string
    {
        return '/sitemap.xml';
    }

    protected function evaluate(Response $response, Target $target): CheckResult
    {
        $body = $response->body();

        if (! str_contains($body, '<urlset') && ! str_contains($body, '<sitemapindex')) {
            return CheckResult::fail(__(':origin/sitemap.xml is not a sitemap.', ['origin' => $target->origin()]));
        }

        return CheckResult::pass(__('Sitemap found with :count URLs.', ['count' => substr_count($body, '<loc>')]));
    }
}
