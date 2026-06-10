<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Parameter Pipeline Riset Produk
    |--------------------------------------------------------------------------
    |
    | Nilai-nilai ini mengatur perilaku pipeline RunProductResearch: berapa
    | query pencarian yang dibuat, berapa URL diambil per query, timeout HTTP
    | untuk panggilan Serper/Jina, dan model Gemini yang dipakai agent.
    |
    */

    // Jumlah query pencarian yang diminta ke agent ekstraksi parameter.
    'queries' => (int) env('RESEARCH_QUERIES', 3),

    // Jumlah URL teratas yang diambil dari hasil Serper per query.
    'urls_per_query' => (int) env('RESEARCH_URLS_PER_QUERY', 2),

    // Timeout (detik) untuk tiap request HTTP ke Serper & Jina.
    'http_timeout' => (int) env('RESEARCH_HTTP_TIMEOUT', 20),

    // Batas karakter konten tiap halaman yang dikirim ke SynthesisAgent. Konten
    // mentah Jina bisa puluhan ribu karakter per halaman; dipotong agar token
    // hemat dan sintesis tetap cepat.
    'synthesis_chars_per_page' => (int) env('RESEARCH_SYNTHESIS_CHARS_PER_PAGE', 4000),

    // Model teks Gemini untuk ekstraksi parameter & sintesis laporan.
    'ai_model' => env('RESEARCH_AI_MODEL', 'gemini-2.5-flash'),

    /*
    |--------------------------------------------------------------------------
    | Failover AI (Phase 9)
    |--------------------------------------------------------------------------
    |
    | Saat provider AI utama kena rate-limit / kuota / kredit habis
    | (FailoverableException), agent otomatis failover ke provider cadangan
    | sehingga riset tidak gagal. Disusun jadi rantai oleh App\Ai\Support\
    | ProviderChain::text() dan dipakai di ParamExtractionAgent & SynthesisAgent.
    |
    */

    // Provider utama (dicoba pertama). Modelnya = 'ai_model' di atas.
    'ai_primary_provider' => env('RESEARCH_AI_PRIMARY_PROVIDER', 'gemini'),

    // Provider cadangan (opsional). KOSONG = failover nonaktif. Boleh BANYAK,
    // pisahkan koma untuk rantai berurutan, mis. 'groq,ollama' = coba Groq dulu,
    // lalu Ollama lokal. Dev: 'ollama' (LLM lokal). Produksi: 'groq' / 'openrouter'.
    'ai_fallback_provider' => env('RESEARCH_AI_FALLBACK_PROVIDER'),

    // Model untuk tiap provider cadangan, dipasangkan per-posisi dengan daftar di
    // atas (pisahkan koma), mis. 'llama-3.3-70b-versatile,llama3.2:3b'.
    'ai_fallback_model' => env('RESEARCH_AI_FALLBACK_MODEL'),

];
