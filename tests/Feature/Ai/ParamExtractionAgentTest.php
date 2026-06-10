<?php

use App\Ai\Agents\ParamExtractionAgent;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\StructuredAgentResponse;

it('extracts parameters and exactly three search queries via structured output', function () {
    ParamExtractionAgent::fake([
        [
            'category' => 'laptop',
            'use_case' => 'editing video',
            'budget' => 'Rp15.000.000',
            'priorities' => ['layar akurat warna', 'performa GPU'],
            'queries' => [
                'laptop terbaik untuk editing video 2026',
                'rekomendasi laptop editing video budget 15 juta',
                'best video editing laptop under 1000 USD review',
            ],
        ],
    ]);

    $result = (new ParamExtractionAgent)->prompt('Butuh laptop untuk editing video budget 15 juta');

    expect($result['category'])->toBe('laptop')
        ->and($result['use_case'])->not->toBeEmpty()
        ->and($result['budget'])->toBe('Rp15.000.000')
        ->and($result['priorities'])->toBeArray()
        ->and($result['queries'])->toHaveCount(3)
        ->and($result['queries'])->each->toBeString();
});

it('passes the user input through to the agent prompt', function () {
    ParamExtractionAgent::fake([
        [
            'category' => 'headphone',
            'use_case' => 'kerja remote di kafe',
            'budget' => 'tidak disebutkan',
            'priorities' => ['noise cancelling'],
            'queries' => [
                'headphone noise cancelling terbaik 2026',
                'rekomendasi headphone untuk kerja di kafe',
                'best ANC headphones for remote work review',
            ],
        ],
    ]);

    $input = 'Cari headphone yang enak buat kerja remote di kafe, yang penting noise cancelling';

    (new ParamExtractionAgent)->prompt($input);

    ParamExtractionAgent::assertPrompted($input);
    ParamExtractionAgent::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains('noise cancelling'));
});

it('can resolve fake responses dynamically from the incoming prompt', function () {
    ParamExtractionAgent::fake(fn (string $prompt) => [
        'category' => 'kursi kerja',
        'use_case' => $prompt,
        'budget' => 'tidak disebutkan',
        'priorities' => [],
        'queries' => [
            'kursi kerja ergonomis terbaik',
            'rekomendasi kursi kantor untuk punggung',
            'best ergonomic office chair review',
        ],
    ]);

    $result = (new ParamExtractionAgent)->prompt('Butuh kursi kerja yang nyaman');

    expect($result['use_case'])->toBe('Butuh kursi kerja yang nyaman')
        ->and($result['priorities'])->toBe([])
        ->and($result['queries'])->toHaveCount(3);
});

it('does not invoke a real provider when faked', function () {
    ParamExtractionAgent::fake([
        [
            'category' => 'monitor',
            'use_case' => 'desain grafis',
            'budget' => 'Rp5.000.000',
            'priorities' => ['akurasi warna'],
            'queries' => [
                'monitor terbaik untuk desain grafis 2026',
                'rekomendasi monitor desain budget 5 juta',
                'best monitor for graphic design color accuracy',
            ],
        ],
    ])->preventStrayPrompts();

    $result = (new ParamExtractionAgent)->prompt('Mau monitor buat desain grafis budget 5 jutaan');

    expect($result)->toBeInstanceOf(StructuredAgentResponse::class)
        ->and($result['queries'])->toHaveCount(3);
});
