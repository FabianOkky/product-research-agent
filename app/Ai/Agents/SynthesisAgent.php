<?php

namespace App\Ai\Agents;

use App\Ai\Support\ProviderChain;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

class SynthesisAgent implements Agent
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'TXT'
        Kamu penulis laporan riset produk berbahasa Indonesia. Dari kebutuhan user, parameter
        terdeteksi, dan konten halaman sumber yang diberikan, susun SATU laporan markdown yang
        rapi dan mudah dibaca dengan TEPAT 8 bagian berikut, memakai heading markdown "## " dan
        judul PERSIS seperti ini, berurutan:

        ## Kebutuhan Kamu
        Ringkas kebutuhan user dalam maksimal 2 kalimat.

        ## Rekomendasi Utama
        Daftar berpoin (bullet "- ") berisi 3 produk teratas. Tiap poin diawali nama produk
        ditebalkan, lalu alasan singkat kenapa cocok. Contoh: "- **Nama Produk** — alasannya".

        ## Perbandingan Cepat
        Buat tabel markdown dengan kolom: Produk | Harga | Kelebihan | Kekurangan.

        ## Detail Per Produk
        Sajikan sebagai tabel markdown dengan kolom: Produk | Spesifikasi Penting | Cocok Untuk.

        ## Info Harga
        Daftar berpoin: tiap poin = nama produk, rentang harganya, dan toko/marketplace
        terpercaya bila tersedia di sumber.

        ## Yang Perlu Diperhatikan
        Daftar berpoin (bullet "- ") berisi red flags, kekurangan umum, atau hal yang sering
        bikin user salah beli.

        ## Verdict Final
        Satu paragraf ringkas: kesimpulan & satu rekomendasi terbaik sesuai kebutuhan dan
        anggaran user.

        ## Sumber
        Daftar berpoin URL HANYA dari sumber yang diberikan di bawah. JANGAN mengarang atau
        menebak URL.

        Aturan penting:
        - Format agar MUDAH DIBACA: pakai poin-poin (bullet) untuk daftar/saran, dan tabel
          HANYA untuk "Perbandingan Cepat" dan "Detail Per Produk". Jangan paragraf panjang
          yang bertele-tele.
        - Dasarkan semua klaim pada konten sumber yang diberikan. Jika informasi tidak cukup,
          katakan apa adanya alih-alih mengarang.
        - Tulis seluruh laporan dalam Bahasa Indonesia.
        - Jangan menambah atau menghapus bagian; gunakan kedelapan heading di atas apa adanya.
        - Keluarkan HANYA isi laporan markdown, tanpa basa-basi pembuka atau penutup.
        TXT;
    }

    /**
     * Get the default model the agent should use when prompting.
     */
    public function model(): string
    {
        return config('research.ai_model');
    }

    /**
     * Synthesize the final 8-section markdown report from the collected pipeline data.
     *
     * @param  array<string, mixed>  $params
     * @param  array<string, string>  $pages
     * @param  array<int, array{query: string, url: string, title: string}>  $sources
     */
    public function report(string $userInput, array $params, array $pages, array $sources): string
    {
        return (string) $this->prompt(
            $this->buildContext($userInput, $params, $pages, $sources),
            provider: ProviderChain::text(),
        );
    }

    /**
     * Assemble the prompt context fed to the model. Page content is truncated per
     * page to keep the token count manageable (raw Jina output can exceed 80k
     * characters across six pages), which would otherwise be slow and costly.
     *
     * @param  array<string, mixed>  $params
     * @param  array<string, string>  $pages
     * @param  array<int, array{query: string, url: string, title: string}>  $sources
     */
    private function buildContext(string $userInput, array $params, array $pages, array $sources): string
    {
        $priorities = $params['priorities'] ?? [];
        $charsPerPage = (int) config('research.synthesis_chars_per_page', 4000);

        $lines = [
            'KEBUTUHAN USER:',
            $userInput,
            '',
            'PARAMETER TERDETEKSI:',
            '- Kategori: '.($params['category'] ?? '-'),
            '- Kebutuhan: '.($params['use_case'] ?? '-'),
            '- Anggaran: '.($params['budget'] ?? '-'),
            '- Prioritas: '.($priorities === [] ? '-' : implode(', ', $priorities)),
            '',
            'SUMBER (pakai URL ini PERSIS di bagian "## Sumber", jangan mengarang):',
        ];

        if ($sources === []) {
            $lines[] = '- (tidak ada sumber yang ditemukan)';
        } else {
            foreach (array_values($sources) as $index => $source) {
                $title = ($source['title'] ?? '') !== '' ? $source['title'] : $source['url'];
                $lines[] = ($index + 1).'. '.$title.' — '.$source['url'];
            }
        }

        $lines[] = '';
        $lines[] = 'KONTEN HALAMAN SUMBER (sudah dipotong agar ringkas):';

        if ($pages === []) {
            $lines[] = '(tidak ada halaman yang berhasil dibaca)';
        } else {
            $index = 1;

            foreach ($pages as $url => $markdown) {
                $lines[] = '';
                $lines[] = '### Sumber '.$index.': '.$url;
                $lines[] = Str::limit(trim($markdown), $charsPerPage);
                $index++;
            }
        }

        return implode("\n", $lines);
    }
}
