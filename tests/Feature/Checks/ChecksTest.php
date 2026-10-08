<?php

use App\Checks\DnsSpfCheck;
use App\Checks\HttpsCheck;
use App\Checks\RobotsCheck;
use App\Checks\SitemapCheck;
use App\Checks\Support\DnsResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => Http::preventStrayRequests());

test('https passes when the site answers over https', function () {
    Http::fake(['https://acme.test/' => Http::response('ok')]);

    expect(app(HttpsCheck::class)->run('http://acme.test/pricing'))->passed->toBeTrue();
});

test('https fails with a reason when the host is unreachable', function () {
    Http::fake(fn () => throw new ConnectionException('Could not resolve host'));

    $result = app(HttpsCheck::class)->run('acme.test');

    expect($result->passed)->toBeFalse()
        ->and($result->reason)->toContain('Could not reach https://acme.test/');
});

test('https fails on a server error and on an invalid address', function () {
    Http::fake(['*' => Http::response('', 503)]);

    expect(app(HttpsCheck::class)->run('acme.test'))->passed->toBeFalse()->reason->toContain('503')
        ->and(app(HttpsCheck::class)->run('not a url'))->passed->toBeFalse();
});

test('sitemap needs a real sitemap document', function (string $body, bool $passed) {
    Http::fake(['https://acme.test/sitemap.xml' => Http::response($body)]);

    expect(app(SitemapCheck::class)->run('acme.test')->passed)->toBe($passed);
})->with([
    'urlset' => ['<urlset><url><loc>https://acme.test/</loc></url></urlset>', true],
    'index' => ['<sitemapindex></sitemapindex>', true],
    'html page' => ['<html>Not found</html>', false],
]);

test('robots fails when it blocks every crawler', function (string $body, bool $passed) {
    Http::fake(['https://acme.test/robots.txt' => Http::response($body)]);

    expect(app(RobotsCheck::class)->run('acme.test')->passed)->toBe($passed);
})->with([
    'allows' => ["User-agent: *\nDisallow: /admin", true],
    'blocks one bot' => ["User-agent: BadBot\nDisallow: /\n\nUser-agent: *\nAllow: /", true],
    'blocks all' => ["User-agent: *\nDisallow: /", false],
]);

test('spf needs exactly one record on the apex domain', function (array $records, bool $passed) {
    $resolver = Mockery::mock(DnsResolver::class);
    $resolver->shouldReceive('txt')->with('acme.test')->andReturn($records);
    app()->instance(DnsResolver::class, $resolver);

    expect(app(DnsSpfCheck::class)->run('https://www.acme.test')->passed)->toBe($passed);
})->with([
    'one' => [['google-site-verification=x', 'v=spf1 include:_spf.google.com ~all'], true],
    'none' => [['google-site-verification=x'], false],
    'two' => [['v=spf1 -all', 'v=spf1 include:x ~all'], false],
]);
