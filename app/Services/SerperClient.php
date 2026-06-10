<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class SerperClient
{
    /**
     * Run every search query against Serper in parallel and collect the top URLs.
     *
     * Each query is dispatched concurrently via a request pool. A query whose
     * response failed (or never arrived because the connection threw) is skipped
     * so a single bad query never aborts the rest.
     *
     * @param  array<int, string>  $queries
     * @return array<int, array{query: string, url: string, title: string}>
     */
    public function topUrls(array $queries, ?int $perQuery = null): array
    {
        $queries = array_values(array_filter(
            array_unique($queries),
            fn (string $query): bool => trim($query) !== '',
        ));

        if ($queries === []) {
            return [];
        }

        $perQuery ??= (int) config('research.urls_per_query');
        $timeout = (int) config('research.http_timeout');
        $endpoint = (string) config('services.serper.url');
        $apiKey = (string) config('services.serper.key');

        $responses = Http::pool(fn (Pool $pool) => array_map(
            fn (string $query) => $pool->as($query)
                ->withHeaders(['X-API-KEY' => $apiKey])
                ->timeout($timeout)
                ->acceptJson()
                ->post($endpoint, ['q' => $query]),
            $queries,
        ));

        $results = [];

        foreach ($queries as $query) {
            $response = $responses[$query] ?? null;

            if (! $response instanceof Response || $response->failed()) {
                continue;
            }

            $organic = $response->json('organic', []);

            foreach (array_slice(is_array($organic) ? $organic : [], 0, $perQuery) as $item) {
                if (! empty($item['link'])) {
                    $results[] = [
                        'query' => $query,
                        'url' => $item['link'],
                        'title' => $item['title'] ?? '',
                    ];
                }
            }
        }

        return $results;
    }
}
