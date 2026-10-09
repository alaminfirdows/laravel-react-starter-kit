<?php

use App\Checks\DnsSpfCheck;
use App\Checks\HttpsCheck;
use App\Checks\RobotsCheck;
use App\Checks\SitemapCheck;
use App\Checks\Support\DnsResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\FakeDnsResolver;

beforeEach(function () {
    Http::preventStrayRequests();
    FakeDnsResolver::bind();
});

test('https passes when the site answers over https', function () {
    Http::fake(['https://acme.test/' => Http::response('ok')]);

    expect(app(HttpsCheck::class)->run('http://acme.test/pricing'))->passed->toBeTrue();
});

test('https fails with a generic reason when the host is unreachable', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 7: Failed to connect to 10.1.2.3 port 443'));

    $result = app(HttpsCheck::class)->run('acme.test');

    expect($result->passed)->toBeFalse()
        ->and($result->reason)->toBe('Could not reach https://acme.test/.');
});

test('hosts that resolve to internal addresses are never requested', function (array $addresses) {
    FakeDnsResolver::bind(['acme.test' => $addresses]);
    Http::fake();

    expect(app(HttpsCheck::class)->run('acme.test'))->passed->toBeFalse()->reason->toBe('Could not reach https://acme.test/.');
    Http::assertNothingSent();
})->with([
    'private' => [['10.0.0.5']],
    'loopback' => [['127.0.0.1']],
    'metadata' => [['169.254.169.254']],
    'cgnat' => [['100.64.0.1']],
    'ipv6 loopback' => [['::1']],
    'ipv6 unique local' => [['fd00:ec2::254']],
    'ipv4 mapped' => [['::ffff:10.0.0.1']],
    'one public, one private' => [['93.184.216.34', '10.0.0.5']],
    'unresolvable' => [[]],
]);

test('an ip literal in the website address is checked too', function () {
    Http::fake();

    expect(app(HttpsCheck::class)->run('169.254.169.254'))->passed->toBeFalse();
    Http::assertNothingSent();
});

test('a redirect to an internal address is not followed', function () {
    FakeDnsResolver::bind(['metadata.evil.test' => ['169.254.169.254']]);
    Http::fake([
        'https://acme.test/' => Http::response('', 302, ['Location' => 'https://metadata.evil.test/latest/meta-data/']),
        'https://metadata.evil.test/*' => Http::response('secret'),
    ]);

    expect(app(HttpsCheck::class)->run('acme.test'))->passed->toBeFalse();
    Http::assertSentCount(1);
});

test('redirects to plain http are not followed', function () {
    Http::fake(['https://acme.test/' => Http::response('', 301, ['Location' => 'http://acme.test/'])]);

    expect(app(HttpsCheck::class)->run('acme.test'))->passed->toBeFalse();
    Http::assertSentCount(1);
});

test('public redirects are followed up to three hops', function () {
    Http::fake([
        'https://acme.test/' => Http::response('', 301, ['Location' => 'https://www.acme.test/']),
        'https://www.acme.test/' => Http::response('ok'),
        'https://loop.test/' => Http::response('', 302, ['Location' => '/']),
    ]);

    expect(app(HttpsCheck::class)->run('acme.test'))->passed->toBeTrue()
        ->and(app(HttpsCheck::class)->run('loop.test'))->passed->toBeFalse()->reason->toContain('too many');
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

    $result = app(DnsSpfCheck::class)->run('https://www.acme.test');

    expect($result->passed)->toBe($passed)
        ->and($result->reason)->not->toContain('include:');
})->with([
    'one' => [['google-site-verification=x', 'v=spf1 include:_spf.google.com ~all'], true],
    'none' => [['google-site-verification=x'], false],
    'two' => [['v=spf1 -all', 'v=spf1 include:x ~all'], false],
]);
