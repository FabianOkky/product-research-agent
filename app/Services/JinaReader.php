<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class JinaReader
{
    /**
     * Read every URL through the Jina Reader proxy in parallel.
     *
     * Returns clean markdown keyed by source URL. URLs that fail to fetch (bad
     * status or connection error) are skipped so one dead page never breaks the
     * batch.
     *
     * @param  iterable<int, string>  $urls
     * @return array<string, string>
     */
    public function readMany(iterable $urls): array
    {
        $urls = $this->normalize($urls);

        if ($urls === []) {
            return [];
        }

        $base = rtrim((string) config('services.jina.url'), '/');
        $timeout = (int) config('research.http_timeout');

        $responses = Http::pool(fn (Pool $pool) => array_map(
            fn (string $url) => $pool->as($url)
                ->timeout($timeout)
                ->get($base.'/'.$url),
            $urls,
        ));

        $pages = [];

        foreach ($urls as $url) {
            $response = $responses[$url] ?? null;

            if ($response instanceof Response && $response->successful()) {
                $pages[$url] = $response->body();
            }
        }

        return $pages;
    }

    /**
     * Reduce an arbitrary iterable of URLs to a unique, trimmed, re-indexed list.
     *
     * @param  iterable<int, string>  $urls
     * @return array<int, string>
     */
    private function normalize(iterable $urls): array
    {
        $list = [];

        foreach ($urls as $url) {
            $url = trim((string) $url);

            if ($url !== '') {
                $list[] = $url;
            }
        }

        return array_values(array_unique($list));
    }
}
