<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class ParamExtractionAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        $queryCount = (int) config('research.queries', 3);

        return <<<TXT
        Kamu asisten riset produk. Dari cerita kebutuhan user (bahasa bebas), ekstrak parameter berikut:
        - category: kategori produk yang dicari (mis. "laptop", "kursi kerja", "headphone").
        - use_case: tujuan atau pemakaian utama secara ringkas.
        - budget: anggaran user. Bila user tidak menyebutkan, isi persis dengan "tidak disebutkan".
        - priorities: daftar hal yang diprioritaskan user (mis. "baterai awet", "ringan"). Boleh kosong.

        Lalu buat TEPAT {$queryCount} query pencarian Google yang BERBEDA dan spesifik (boleh Bahasa
        Indonesia atau Inggris) untuk menemukan rekomendasi, review, dan perbandingan produk yang relevan.
        Hindari query yang duplikatif atau terlalu umum.
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
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        $queryCount = (int) config('research.queries', 3);

        return [
            'category' => $schema->string()->required(),
            'use_case' => $schema->string()->required(),
            'budget' => $schema->string()->required(),
            'priorities' => $schema->array()->items($schema->string()),
            'queries' => $schema->array()->items($schema->string())->min($queryCount)->max($queryCount)->required(),
        ];
    }
}
