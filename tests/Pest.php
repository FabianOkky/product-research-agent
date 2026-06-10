<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * The eight canonical section headings the SynthesisAgent must produce, in order.
 *
 * @return list<string>
 */
function synthesisReportHeadings(): array
{
    return [
        '## Kebutuhan Kamu',
        '## Rekomendasi Utama',
        '## Perbandingan Cepat',
        '## Detail Per Produk',
        '## Info Harga',
        '## Yang Perlu Diperhatikan',
        '## Verdict Final',
        '## Sumber',
    ];
}

/**
 * A canned 8-section markdown report used to stand in for the SynthesisAgent when
 * faking it in tests, so no real provider call is made.
 */
function fakeSynthesisReport(): string
{
    return <<<'MD'
    ## Kebutuhan Kamu
    Kamu butuh laptop untuk editing video dengan budget sekitar 15 juta.

    ## Rekomendasi Utama
    1. Produk A — performa GPU kuat.
    2. Produk B — layar akurat warna.
    3. Produk C — value terbaik.

    ## Perbandingan Cepat
    | Produk | Harga | Kelebihan | Kekurangan |
    | --- | --- | --- | --- |
    | Produk A | Rp14jt | GPU kencang | berat |
    | Produk B | Rp15jt | layar bagus | baterai biasa |
    | Produk C | Rp13jt | murah | port terbatas |

    ## Detail Per Produk
    Produk A cocok untuk rendering berat dan multitasking.

    ## Info Harga
    Rentang harga Rp13–15 juta di marketplace terpercaya.

    ## Yang Perlu Diperhatikan
    Hati-hati garansi distributor dan unit refurbished.

    ## Verdict Final
    Produk B paling seimbang untuk kebutuhan dan anggaranmu.

    ## Sumber
    1. https://shop.test/a
    2. https://shop.test/b
    MD;
}
