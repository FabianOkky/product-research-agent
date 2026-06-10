<?php

use App\Services\SerperClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.serper.key' => 'test-serper-key',
        'services.serper.url' => 'https://google.serper.dev/search',
        'research.urls_per_query' => 2,
        'research.http_timeout' => 20,
    ]);

    Http::preventStrayRequests();
});

it('returns the top N urls per query with query/url/title shape', function () {
    Http::fake(function (Request $request) {
        $query = $request->data()['q'];

        return Http::response([
            'organic' => [
                ['title' => "Teratas: {$query}", 'link' => "https://shop.test/{$query}/1"],
                ['title' => "Kedua: {$query}", 'link' => "https://shop.test/{$query}/2"],
                ['title' => "Ketiga: {$query}", 'link' => "https://shop.test/{$query}/3"],
            ],
        ]);
    });

    $results = (new SerperClient)->topUrls(['laptop', 'mouse', 'monitor'], perQuery: 2);

    // 3 queries x top 2 = 6 entries; the third organic item is never included.
    expect($results)->toHaveCount(6)
        ->and($results)->each->toHaveKeys(['query', 'url', 'title']);

    $urls = collect($results)->pluck('url')->all();

    expect($urls)->toContain('https://shop.test/laptop/1', 'https://shop.test/laptop/2')
        ->and($urls)->not->toContain('https://shop.test/laptop/3');

    Http::assertSentCount(3);
});

it('skips a query whose response failed and keeps the others', function () {
    Http::fake(function (Request $request) {
        if ($request->data()['q'] === 'query gagal') {
            return Http::response(['message' => 'kena rate limit'], 500);
        }

        return Http::response([
            'organic' => [
                ['title' => 'OK 1', 'link' => 'https://shop.test/ok/1'],
                ['title' => 'OK 2', 'link' => 'https://shop.test/ok/2'],
            ],
        ]);
    });

    $results = (new SerperClient)->topUrls(['query ok', 'query gagal'], perQuery: 2);

    expect($results)->toHaveCount(2)
        ->and(collect($results)->pluck('query')->unique()->all())->toBe(['query ok']);
});

it('returns an empty array when every query connection fails', function () {
    Http::fake(['google.serper.dev/*' => Http::failedConnection()]);

    expect((new SerperClient)->topUrls(['a', 'b']))->toBe([]);
});

it('sends the serper api key header and posts each query to the endpoint', function () {
    Http::fake([
        'google.serper.dev/*' => Http::response([
            'organic' => [['title' => 'A', 'link' => 'https://shop.test/a']],
        ]),
    ]);

    (new SerperClient)->topUrls(['kamera mirrorless'], perQuery: 2);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://google.serper.dev/search'
        && $request->method() === 'POST'
        && $request->hasHeader('X-API-KEY', 'test-serper-key')
        && $request->data()['q'] === 'kamera mirrorless');
});

it('ignores organic results without a link and de-duplicates queries', function () {
    Http::fake([
        'google.serper.dev/*' => Http::response([
            'organic' => [
                ['title' => 'Tanpa link'],                            // no link key -> skipped
                ['title' => 'Punya link', 'link' => 'https://shop.test/x'],
                ['title' => 'Link kosong', 'link' => ''],             // empty link -> skipped
            ],
        ]),
    ]);

    $results = (new SerperClient)->topUrls(['gadget', 'gadget'], perQuery: 3);

    expect($results)->toHaveCount(1)
        ->and($results[0]['url'])->toBe('https://shop.test/x');

    // Duplicate query collapses to a single pooled request.
    Http::assertSentCount(1);
});

it('falls back to the configured urls_per_query when none is given', function () {
    config(['research.urls_per_query' => 1]);

    Http::fake([
        'google.serper.dev/*' => Http::response([
            'organic' => [
                ['title' => '1', 'link' => 'https://shop.test/1'],
                ['title' => '2', 'link' => 'https://shop.test/2'],
            ],
        ]),
    ]);

    $results = (new SerperClient)->topUrls(['kursi']);

    expect($results)->toHaveCount(1)
        ->and($results[0]['url'])->toBe('https://shop.test/1');
});
