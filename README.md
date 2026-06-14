# Client AI

A pro-grade, modular, and highly secure AI chatbot engine for WordPress. It supports native function calling, dynamic multi-provider LLM adapters, a searchable global holiday seeder, and advanced prompt engineering controls.

[![Version](https://img.shields.io/badge/version-2.4.0-blue.svg)](#)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-0073aa.svg)](#)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777bb4.svg)](#)
[![License](https://img.shields.io/badge/license-GPL--2.0-green.svg)](#)
[![Buy Me A Coffee](https://img.shields.io/badge/Buy%20Me%20A%20Coffee-Donate-FF8F3F?logo=buy-me-a-coffee)](https://buymeacoffee.com/totaldsgn)

---

## How It Works

This plugin delivers a flexible, robust, and highly secure artificial intelligence assistant to your WordPress website. Built on a clean MVC modular architecture, it separates database logic, front-end visual presentation, and API routing.

The chatbot leverages advanced **Strategy A retrieval mechanics** to digest localized page content dynamically, merging it with custom business FAQ rules, real-time temporal pivots, and deterministic schedule validation via native LLM tool-calling (function calling).

---

## Key Features

* **Multi-Provider AI Adapters**: Integrated connection adapters for Anthropic (Claude), Google AI Studio (Gemini), Groq (Llama), Cerebras, Mistral, and custom self-hosted OpenAI-compatible endpoints (Ollama, LM Studio, etc.).
* **Multi-Language Support**: Auto-detect visitor browser language or set a fixed language. The AI responds natively in the specified language, making the chatbot accessible to international audiences without configuration changes.
* **Visitor Feedback**: Optional thumbs up/down after each response. Satisfaction rate is displayed in the Dashboard, helping identify which answers need improvement.
* **Conversation History**: Full threaded chat transcripts grouped by session. View the complete back-and-forth of any conversation in a chat-bubble interface from the admin area.
* **Deterministic Calendar Tool**: Empower the AI to answer business opening-hours questions using the `check_calendar` function. It supports dynamic weekday/weekend defaults, specific date overrides, and annual recurring dates.
* **Global Holiday Seeder**: Connects to the Nager.Date API with a searchable autocomplete country selector. This allows you to automatically pull and seed holidays from any supported country directly into your schedules database.
* **Advanced Prompt Engineering**: A toggleable developer panel inside your settings page lets you inspect, edit, and experiment with the sub-prompts coordinating the AI’s temporal pivots, tool-calling protocols, and negative constraints. Complete with a secure "Reset to Defaults" option.
* **Strategy A Context Retrieval**: Intelligently compiles clean text digests of your allowed post types on save, caching condensed summaries to significantly reduce API token usage and latency.
* **Custom Q&A Overrides**: Prioritized semantic matching table to bypass expensive LLM inference entirely for exact business FAQs and keywords.
* **Accessible Slide-Out Interface (WCAG 2.2 AA)**: Fully responsive and transition-smooth sliding panel widgets for `tab-right` and `tab-left` placements.
* **Enterprise Cryptography & Security**: Secure AES-256-GCM database encryption for API keys, strict Server-Side Request Forgery (SSRF) endpoint filters, WP nonce verification, and CDN-aware proxy IP rate limiting.
* **Models Management**: A dedicated admin page for managing AI models per provider. Add custom models, edit existing ones, toggle active status, or reset a provider to its default models — all without editing JSON files. Built-in models are seeded from the plugin and automatically updated on upgrade.
* **Audit Logs & Cleanup**: Paginated, filterable conversation logging with automated background cron cleanup tasks to manage database storage.

---

## Installation & Setup

### 1. Installation
1. Download or clone this repository into a folder named `client-ai`.
2. Move the directory to your `/wp-content/plugins/` directory.
3. Activate **Client AI** inside your WordPress **Plugins** screen.

### 2. Basic Configuration
1. Go to **Client AI > Settings** in your WordPress admin menu.
2. Select your active AI Provider and input your API credentials.
3. Choose your desired model from the dynamic catalog.
4. Set your primary brand color, chatbot title, and default welcome message.
5. Save your settings.

### 3. Display Options
You can output the chatbot toggle on your site in two ways:
* **Automatic Injection**: Check the **Auto-inject** box in **Display Options** to display the floating widget on all allowed public pages automatically.
* **Shortcode**: Uncheck Auto-inject and drop this shortcode into any page, text block, header, or footer element:
  
  ```text
  [ai_chatbot]
  ```

## Models Management

Client AI 2.4.0 introduces a database-driven Models system accessible via **Client AI > Models** in the WordPress admin. This replaces the old JSON-only catalog with a flexible, CRUD-capable approach.

### Features
- **Built-in Models**: Automatically seeded from `assets/models.json` on plugin activation. Marked as "Built-in" and protected from accidental deletion.
- **Custom Models**: Add your own models for any provider. Enter the model ID exactly as required by the API, give it a display name, description, and configure tool support.
- **Edit & Toggle**: Edit any model's display name, description, context window, and flags. Toggle models active/inactive without deleting them.
- **Reset Provider to Defaults**: Remove all custom models for a provider and re-seed the factory defaults — useful after experimenting.

### How It Works
1. On activation, the plugin creates the `wp_aicb_models` database table.
2. All models from `assets/models.json` are inserted using `INSERT IGNORE` — safe to re-run without overwriting custom models.
3. The catalog functions (`aicb_get_catalog()`, `aicb_get_providers()`, `aicb_get_models()`) now query the database instead of reading JSON, while falling back to JSON if the table is empty.
4. The Settings page provider grid and model dropdown continue to work identically — no JavaScript or HTML changes needed.

### Developer Notes
- Editing `assets/models.json` directly no longer has any effect after the first activation. The JSON file serves as the seed source; the database is the authoritative runtime source.
- The `is_custom` flag (0 = seeded, 1 = user-added) enables clean separation between built-in and custom models.
- The tool-support check in `aicb_adapter_openai_compat()` now queries the `supports_tools` column directly instead of relying on fragile string matching against provider names and URLs.

---

## Developer Guide

### Advanced Prompt Engineering (Exposed Sub-Prompts)

By expanding the 'Advanced Prompt Engineering' panel on the settings page, developers can modify the core instructions guiding the model's behavior:

* **Temporal Context Template**: Instructs the model on how to handle the current system time and date. Supports {current_date} and {current_time} dynamic tag replacements.
* **Tool Coordination Protocol**: Teaches the model exactly when to call the calendar tool vs. reading static FAQs, and how to negotiate rule overrides.
* **Negative Constraints & Integrity**: Governs conversational formatting, strict output length limits, context leak safeguards, and forbidden word exclusions.

If a developer's customizations cause unwanted behavior or system drift, clicking the **Reset Engineering Templates** button will securely purge custom overrides and restore the factory prompt schemas.

## Open Source & Community License

This plugin is fully **open source** and released under the **GPL-2.0+ license**.

Members of the WordPress community are welcome to clone, fork, modify, and redistribute this codebase, provided that any modified distributions are also kept free, open source, and accessible to the public.

If this Client AI integration saved you development hours, improved your customer experience, or made your site more accessible, please consider buying me a coffee to support my ongoing work and contributions to the open-source community!

<!-- Testing the CodeRabbit automated review pipeline -->

[![Buy Me A Coffee](https://img.shields.io/badge/Buy%20Me%20A%20Coffee-Donate-FF8F3F?logo=buy-me-a-coffee)](https://buymeacoffee.com/totaldsgn)
