<?php

use App\Services\JinaReader;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.jina.url' => 'https://r.jina.ai',
        'research.http_timeout' => 20,
    ]);

    Http::preventStrayRequests();
});

it('reads each url in parallel and returns markdown keyed by source url', function () {
    Http::fake(fn (Request $request) => Http::response('# Konten dari '.$request->url()));

    $urls = ['https://a.test/satu', 'https://b.test/dua'];

    $pages = (new JinaReader)->readMany($urls);

    expect($pages)->toHaveCount(2)
        ->and($pages)->toHaveKeys($urls)
        ->and($pages['https://a.test/satu'])->toContain('a.test/satu');

    Http::assertSentCount(2);
    // Each source url is fetched through the Jina reader proxy.
    Http::assertSent(fn (Request $request) => $request->url() === 'https://r.jina.ai/https://a.test/satu');
});

it('skips urls that fail and keeps the successful ones', function () {
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), 'rusak')) {
            return Http::response('Server Error', 500);
        }

        return Http::response('# Markdown bersih');
    });

    $pages = (new JinaReader)->readMany([
        'https://a.test/bagus',
        'https://b.test/rusak',
    ]);

    expect($pages)->toHaveCount(1)
        ->toHaveKey('https://a.test/bagus')
        ->not->toHaveKey('https://b.test/rusak');
});

it('skips urls whose connection fails without throwing', function () {
    Http::fake(['*' => Http::failedConnection()]);

    expect((new JinaReader)->readMany(['https://a.test/x']))->toBe([]);
});

it('de-duplicates and ignores empty urls', function () {
    Http::fake(fn (Request $request) => Http::response('# Konten'));

    $pages = (new JinaReader)->readMany([
        'https://a.test/satu',
        'https://a.test/satu', // duplikat -> sekali fetch
        '   ',                 // kosong setelah trim -> dilewati
        '',
    ]);

    expect($pages)->toHaveCount(1)->toHaveKey('https://a.test/satu');

    Http::assertSentCount(1);
});

it('accepts any iterable of urls', function () {
    Http::fake(fn (Request $request) => Http::response('# Konten'));

    $urls = (function () {
        yield 'https://a.test/satu';
        yield 'https://b.test/dua';
    })();

    $pages = (new JinaReader)->readMany($urls);

    expect($pages)->toHaveCount(2)
        ->toHaveKeys(['https://a.test/satu', 'https://b.test/dua']);
});
