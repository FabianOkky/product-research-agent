<div align="center">

# 🔍 Product Research Agent

**Describe what you need in plain language — get a structured, sourced product report back.**

An AI research assistant that turns _"I need a laptop for video editing, budget around 15M,
good screen and battery"_ into a comparison report researched from the live web. No need to
know product names in advance.

[![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)](https://www.php.net)
[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![Livewire](https://img.shields.io/badge/Livewire-4-FB70A9?logo=livewire&logoColor=white)](https://livewire.laravel.com)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind-4-06B6D4?logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![Tests](https://img.shields.io/badge/tests-99%20passing-success?logo=pytest&logoColor=white)](#-testing--code-style)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

</div>

> The app UI and the generated reports are in **Bahasa Indonesia**. The codebase and this
> README are in English.

---

## 📑 Table of contents

- [What it does](#-what-it-does)
- [Why it's interesting](#-why-its-interesting-engineering-highlights)
- [How it works](#-how-it-works)
- [Tech stack](#-tech-stack)
- [Screens](#-screens)
- [Getting started](#-getting-started)
  - [Requirements](#requirements)
  - [Get the API keys (free)](#get-the-api-keys-free)
  - [Installation](#installation)
  - [Configure your environment](#configure-your-environment)
  - [Run it](#run-it)
- [Usage](#-usage)
- [Testing & code style](#-testing--code-style)
- [AI failover (optional)](#-ai-failover-optional)
- [Deployment](#-deployment)
- [Project structure](#-project-structure)
- [License](#-license)

---

## ✨ What it does

- **Natural-language input** — describe a _need_, not a product. The agent extracts the
  category, use case, budget, and priorities for you.
- **Real-time progress tracker** — a live, polling step list (understand → search → read →
  synthesize → done) so the ~30–60s wait stays transparent.
- **8-section sourced report** — recommendations, a quick-comparison table, per-product
  detail, pricing notes, red flags, a final verdict, and the list of real sources.
- **Research history** — every run is saved to your account and reopenable.
- **Resilient by design** — parallel HTTP, per-source error isolation, multi-tier AI
  failover, and friendly error messages when something is misconfigured.

## 🧠 Why it's interesting (engineering highlights)

This is a small app, but it was built to behave like a real one:

| Concern | How it's handled |
|---|---|
| **Long-running work** | The full pipeline runs in a **queued job** (`RunProductResearch`); the UI never blocks. |
| **Live feedback** | `Research/Show` polls the job row every 2s with `wire:poll` and **stops polling automatically** once the job settles. |
| **Provider outages** | **Multi-tier AI failover** (Gemini → Groq → Ollama) triggered _only_ by rate-limit / overload / out-of-credit errors. |
| **Flaky sources** | Search and page reads run in parallel (`Http::pool`); **one bad source never fails the batch**. |
| **Token blow-up** | Scraped pages (~80k chars) are **truncated per page** before hitting the LLM. |
| **Enumerable URLs** | Jobs are addressed by an **opaque UUID** route key — sequential integer ids return 404. |
| **LLM output safety** | Generated markdown is rendered with raw HTML **stripped** and unsafe (`javascript:`) links removed. |
| **Misconfiguration** | A **preflight check** turns missing API keys into a friendly, retryable error instead of a stack trace. |
| **Confidence** | **99 Pest tests** — no test ever hits a live API or spends quota (everything is faked). |

## 🔧 How it works

```
User input (natural language)
  │
  1. [Gemini · structured output]  extract { category, use_case, budget, priorities }
  │                                + generate 3 distinct search queries
  │
  2. [Serper]        run the 3 queries in parallel → take the top 2 URLs each → 6 sources
  │
  3. [Jina Reader]   fetch the 6 pages in parallel → clean markdown
  │
  4. [Gemini]        synthesize everything → an 8-section markdown report
  │
  5. Save the report to the job; the UI renders it the moment status = done
```

The whole pipeline runs inside a **queued job**. `Research/Show` polls the job every 2
seconds and renders the report once it's `done` (or a friendly error if it `failed`).

## 🛠 Tech stack

| Layer | Choice |
|---|---|
| Framework | Laravel 13 (Livewire starter kit) |
| UI | Livewire 4 + Flux UI 2 + Tailwind CSS 4 |
| AI | Laravel AI SDK (`laravel/ai`) → Google Gemini (`gemini-2.5-flash`) |
| Search / Read | Serper (Google search API) + Jina Reader (no key) |
| Database | PostgreSQL (Supabase) — SQLite for a quick local start |
| Queue | `database` driver + a `queue:work` worker |
| Auth | Laravel Fortify (from the starter kit) |
| Tests | Pest 4 — **99 tests** |

## 🖥 Screens

A quick tour of the app (all in Bahasa Indonesia):

- **Dashboard** — at-a-glance status counts (total / done / running / failed) and your most
  recent runs, with a one-click "Riset Baru".
- **Riset Baru** (New Research) — a single textarea: describe your need in plain language.
- **Hasil Riset** (Result) — the live progress tracker, then the 8-section report with a
  comparison table and the list of sources.
- **Riwayat** (History) — every run you've made, newest first, reopenable.

> 💡 _Want screenshots in this README? Drop images into `docs/screenshots/` and link them here._

## 🚀 Getting started

### Requirements

- **PHP 8.4+** and Composer
- **Node.js 20+** and npm
- A database: **Supabase Postgres** (free) _or_ local **SQLite**
- Two API keys (both have generous free tiers — see below)

### Get the API keys (free)

| Service | Where | Notes |
|---|---|---|
| **Gemini** | <https://aistudio.google.com/apikey> | Free tier is enough (~2 calls per run) |
| **Serper** | <https://serper.dev> | 2,500 free queries on signup, no credit card |
| **Jina Reader** | — | No key required (`https://r.jina.ai`) |

### Installation

```bash
git clone https://github.com/FabianOkky/product-research-agent.git
cd product-research-agent

composer install
npm install

cp .env.example .env          # Windows PowerShell: copy .env.example .env
php artisan key:generate
```

### Configure your environment

Open `.env` and set at least the database and the two API keys:

```dotenv
# --- Database: Supabase Postgres (recommended) ---
DB_CONNECTION=pgsql
DB_HOST=your-project.pooler.supabase.com
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres.your-project
DB_PASSWORD=your-password

# --- ...or comment the block above and use SQLite for a quick start ---
# DB_CONNECTION=sqlite        # then create the file: touch database/database.sqlite

# --- Product Research Agent keys ---
GEMINI_API_KEY=your-gemini-key
SERPER_API_KEY=your-serper-key
```

Then migrate and build the front-end assets:

```bash
php artisan migrate
npm run build
```

### Run it

One command runs the web server, the **queue worker**, and Vite together:

```bash
composer dev
```

Now open **<http://localhost:8000>**, register an account, and start a research run.

> ⚠️ The pipeline only progresses while a **queue worker** is running. `composer dev`
> includes it. If you start pieces yourself, you need all three:
> `php artisan serve`, `php artisan queue:work`, and `npm run dev` (or `npm run build`).

## 📖 Usage

1. Register / log in.
2. The **Dashboard** shows your research summary; click **"Riset Baru"** (New Research).
3. Describe your need in plain language (min. 10 characters).
4. Watch the live progress tracker (≈30–60s).
5. Read the 8-section report; find past runs under **"Riwayat"** (History).

## ✅ Testing & code style

```bash
php artisan test            # 99 Pest tests — no real API calls, everything is faked
vendor/bin/pint             # format code (Laravel Pint)
composer test               # lint check + the full test suite
```

Tests run against **SQLite in-memory** and never touch live APIs or your quota. There are
also lightweight **architecture tests** that keep agents, services, and jobs in their lane
and ban stray `dd()`/`dump()` calls.

## 🛟 AI failover (optional)

Configure a **multi-tier** backup chain so a quota / rate-limit / overload on the primary
provider doesn't fail a run. Comma-separate providers to chain them, tried in order:

```dotenv
RESEARCH_AI_PRIMARY_PROVIDER=gemini
RESEARCH_AI_FALLBACK_PROVIDER=groq,ollama                         # tried in order
RESEARCH_AI_FALLBACK_MODEL=llama-3.3-70b-versatile,qwen2.5:3b     # paired by position
GROQ_API_KEY=your-groq-key                                        # Groq needs a key
```

This builds **Gemini → Groq (free cloud) → Ollama (local LLM)**. A single value (e.g.
`ollama`) still works, and leaving `RESEARCH_AI_FALLBACK_PROVIDER` empty disables failover.
Failover triggers **only** on rate-limit / overloaded / insufficient-credits errors — other
errors surface normally.

> **Note:** the structured parameter-extraction step automatically **skips Groq** (its
> strict `json_schema` mode rejects our schema), so extraction fails over `Gemini → Ollama`,
> while plain-text synthesis uses the full chain. Ollama needs its service running on
> `localhost:11434` with the model pulled (`ollama pull qwen2.5:3b`).

## ☁️ Deployment

The app needs **two processes in production**: the web server **and** a
`php artisan queue:work` worker. See **[DEPLOY.md](DEPLOY.md)** for the full step-by-step
guide. The available paths in short:

- **Local + Cloudflare Tunnel** — free; share a public URL while your machine runs.
- **Oracle Cloud Always Free** — a free 24/7 VPS (more setup).
- **Railway** — easiest cloud, but the worker is paid (~$5/mo).

Production build commands (also in `DEPLOY.md`):

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache route:cache view:cache
php artisan queue:work --tries=2 --timeout=120   # the worker (required)
```

## 🧩 Project structure

```
app/
  Ai/Agents/         ParamExtractionAgent (structured output) · SynthesisAgent (report)
  Ai/Support/        ProviderChain (builds the AI failover chain from config)
  Jobs/              RunProductResearch (the full queued pipeline)
  Services/          SerperClient (search) · JinaReader (page reader)
  Livewire/          Dashboard · Research/{Create, Show, History}
  Models/            ResearchJob (UUID route key)
resources/views/
  components/        research-status-badge (shared status badge)
  livewire/          dashboard · research/{create, show, history}
config/
  research.php       pipeline knobs (query count, timeouts, AI model, failover)
  services.php       serper + jina endpoints / keys
tests/               Pest feature + unit + architecture tests
```

## 📄 License

Released under the **MIT License** — see [LICENSE](LICENSE). Built on the Laravel Livewire
starter kit.
