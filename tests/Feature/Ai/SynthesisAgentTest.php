<?php

use App\Ai\Agents\SynthesisAgent;
use Laravel\Ai\Prompts\AgentPrompt;

/**
 * Minimal pipeline data passed to the SynthesisAgent in these tests.
 *
 * @return array{params: array<string, mixed>, pages: array<string, string>, sources: array<int, array{query: string, url: string, title: string}>}
 */
function synthesisFixture(): array
{
    return [
        'params' => [
            'category' => 'laptop',
            'use_case' => 'editing video',
            'budget' => 'Rp15.000.000',
            'priorities' => ['layar akurat warna', 'performa GPU'],
        ],
        'pages' => [
            'https://shop.test/a' => "# Review A\nIsi halaman review pertama.",
            'https://shop.test/b' => "# Review B\nIsi halaman review kedua.",
        ],
        'sources' => [
            ['query' => 'laptop editing video', 'url' => 'https://shop.test/a', 'title' => 'Review A'],
            ['query' => 'laptop editing video', 'url' => 'https://shop.test/b', 'title' => 'Review B'],
        ],
    ];
}

it('produces a markdown report with all eight section headings', function () {
    SynthesisAgent::fake([fakeSynthesisReport()]);

    $fixture = synthesisFixture();

    $report = (new SynthesisAgent)->report(
        'Butuh laptop untuk editing video budget 15 juta',
        $fixture['params'],
        $fixture['pages'],
        $fixture['sources'],
    );

    expect($report)->toBeString();

    foreach (synthesisReportHeadings() as $heading) {
        expect($report)->toContain($heading);
    }
});

it('feeds the user input, parameters, sources, and page content into the prompt', function () {
    SynthesisAgent::fake([fakeSynthesisReport()])->preventStrayPrompts();

    $fixture = synthesisFixture();
    $input = 'Butuh laptop untuk editing video budget 15 juta';

    (new SynthesisAgent)->report($input, $fixture['params'], $fixture['pages'], $fixture['sources']);

    SynthesisAgent::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains($input)
        && $prompt->contains('editing video')                  // detected parameter
        && $prompt->contains('https://shop.test/a')            // source URL
        && $prompt->contains('Isi halaman review pertama.'));  // page content
});

it('truncates long page content before prompting to keep the token count down', function () {
    config(['research.synthesis_chars_per_page' => 50]);

    SynthesisAgent::fake([fakeSynthesisReport()]);

    $longContent = str_repeat('x', 5000);

    (new SynthesisAgent)->report(
        'Butuh laptop',
        ['category' => 'laptop'],
        ['https://shop.test/a' => $longContent],
        [['query' => 'laptop', 'url' => 'https://shop.test/a', 'title' => 'A']],
    );

    SynthesisAgent::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains(str_repeat('x', 50))
        && ! $prompt->contains(str_repeat('x', 200)));
});

it('does not call a real provider when faked', function () {
    SynthesisAgent::fake([fakeSynthesisReport()])->preventStrayPrompts();

    $fixture = synthesisFixture();

    $report = (new SynthesisAgent)->report(
        'Butuh laptop untuk editing video',
        $fixture['params'],
        $fixture['pages'],
        $fixture['sources'],
    );

    expect($report)->toContain('## Verdict Final');
});
