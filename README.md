# Client AI

A pro-grade, modular, and highly secure AI chatbot engine for WordPress. It supports native function calling, dynamic multi-provider LLM adapters, a searchable global holiday seeder, and advanced prompt engineering controls.

[![Version](https://img.shields.io/badge/version-2.5.1-blue.svg)](#) [![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-0073aa.svg)](#) [![PHP](https://img.shields.io/badge/PHP-8.0%2B-777bb4.svg)](#) [![License](https://img.shields.io/badge/license-GPL--2.0-green.svg)](#) [![Buy Me A Coffee](https://img.shields.io/badge/Buy%20Me%20A%20Coffee-Donate-FF8F3F?logo=buy-me-a-coffee)](https://buymeacoffee.com/totaldsgn)

* * *

## Important Disclosures & Privacy Notice

To deliver advanced context-aware assistance and dynamically fetched calendar parameters, this plugin interfaces with the following external services. All data transmitted is limited strictly to the resources required to process requests.

### 1. Global Public Holidays Lookup (Nager.Date API)

- **Service Provider**: Nager.Date API ([https://date.nager.at](https://date.nager.at))
- **Usage**: When a site administrator uses the "Seed Global Country Holidays" configuration page to seed special opening hours, the plugin queries this API for public holidays based on the country selected.
- **Data Transmitted**: No personal, administrative, or visitor data is transmitted. Only the target year and country code are sent.
- **Terms of Use**: [Nager.Date Project Information](https://date.nager.at)

### 2. Large Language Model (LLM) Processing

Depending on your chosen configuration in the settings panel, this plugin forwards chat prompts to the specified third-party API endpoint to generate conversational answers. No other site files or databases are shared.

- **Anthropic API** ([https://api.anthropic.com](https://api.anthropic.com)): [Anthropic Privacy Policy](https://www.anthropic.com/legal/privacy)
- **Google Gemini API** ([https://generativelanguage.googleapis.com](https://generativelanguage.googleapis.com)): [Google Privacy Policy](https://policies.google.com/privacy)
- **Groq API** ([https://api.groq.com](https://api.groq.com)): [Groq Privacy Policy](https://groq.com/privacy/)
- **Cerebras API** ([https://api.cerebras.ai](https://api.cerebras.ai)): [Cerebras Privacy Policy](https://www.cerebras.net/privacy-policy/)
- **Mistral API** ([https://docs.mistral.ai/api](https://docs.mistral.ai/api/)): [Mistral Privacy Policy](https://legal.mistral.ai/terms/privacy-policy?language=en-US)

* * *

## How It Works

This plugin delivers a flexible, robust, and highly secure artificial intelligence assistant to your WordPress website. Built on a clean MVC modular architecture, it separates database logic, front-end visual presentation, and API routing.

The chatbot leverages advanced **Strategy A retrieval mechanics** to digest localized page content dynamically, merging it with custom business FAQ rules, real-time temporal pivots, and deterministic schedule validation via native LLM tool-calling (function calling).

* * *

## Key Features

- **Multi-Provider AI Adapters**: Integrated connection adapters for Anthropic (Claude), Google AI Studio (Gemini), Groq (Llama), Cerebras, Mistral, and custom self-hosted OpenAI-compatible endpoints (Ollama, LM Studio, etc.).
- **Multi-Language Support**: Auto-detect visitor browser language or set a fixed language. The AI responds natively in the specified language, making the chatbot accessible to international audiences without configuration changes.
- **Visitor Feedback**: Optional thumbs up/down after each response. Satisfaction rate is displayed in the Dashboard, helping identify which answers need improvement.
- **Conversation History**: Full threaded chat transcripts grouped by session. View the complete back-and-forth of any conversation in a chat-bubble interface from the admin area.
- **Deterministic Calendar Tool**: Empower the AI to answer business opening-hours questions using the check\_calendar function. It supports dynamic weekday/weekend defaults, specific date overrides, and annual recurring dates.
- **Global Holiday Seeder**: Connects to the Nager.Date API with a searchable autocomplete country selector. This allows you to automatically pull and seed holidays from any supported country directly into your schedules database.
- **Advanced Prompt Engineering**: A toggleable developer panel inside your settings page lets you inspect, edit, and experiment with the sub-prompts coordinating the AI's temporal pivots, tool-calling protocols, and negative constraints. Complete with a secure "Reset Engineering Templates" button.
- **Import / Export Configuration**: Backup and restore your entire plugin configuration via the Settings page. Select specific sections to export (General Settings, Calendar & Hours, Advanced Prompt Engineering, Custom Q&A Entries, Custom Model Definitions) to a downloadable JSON file. API keys are securely excluded from exports. Import previously exported files to restore your configuration with a single click.
- **Strategy A Context Retrieval**: Intelligently compiles clean text digests of your allowed post types on save, caching condensed summaries to significantly reduce API token usage and latency.
- **Custom Q&A Overrides**: Prioritized semantic matching table to bypass expensive LLM inference entirely for exact business FAQs and keywords.
- **Accessibility-First Design (WCAG 2.2 AA Principles)**: Engineered with accessibility as a core priority. The frontend chatbot uses strict keyboard focus management (capturing and returning focus cleanly to the launcher on open/close), a native Escape key closing hook, ARIA landmark and live announcer roles (role="dialog", role="log", aria-live="polite"), and full CSS support for prefers-reduced-motion browser media queries.
  
  *Administrator Responsibility Note:* While the chatbot's structural engine is built to fully support WCAG 2.2 AA standards, complete compliance on your live website ultimately depends on your administrative choices. Ensure your chosen "Primary Color" under settings maintains a contrast ratio of at least 4.5:1 against white text, and that your custom welcome messages and Q&As remain descriptive, clear, and readable.
- **Enterprise Cryptography & Security**: Secure AES-256-GCM database encryption for API keys, strict Server-Side Request Forgery (SSRF) endpoint filters, WP nonce verification, and CDN-aware proxy IP rate limiting.
- **Models Management**: A dedicated admin page for managing AI models per provider. Add custom models, edit existing ones, toggle active status, or reset a provider to its default models — all without editing JSON files. Built-in models are seeded from the plugin and automatically updated on upgrade.
- **Audit Logs & Cleanup**: Paginated, filterable conversation logging with automated background cron cleanup tasks to manage database storage.

* * *

## Installation & Setup

### 1. Installation

1. Download or clone this repository into a folder named client-ai.
2. Move the directory to your /wp-content/plugins/ directory.
3. Activate **Client AI** inside your WordPress **Plugins** screen.

### 2. Basic Configuration

1. Go to **Client AI > Settings** in your WordPress admin menu.
2. Select your active AI Provider and input your API credentials.
3. Choose your desired model from the dynamic catalog.
4. Set your primary brand color, chatbot title, and default welcome message.
5. Save your settings.

### 3. Display Options

You can output the chatbot toggle on your site in two ways:

- **Automatic Injection**: Check the **Auto-inject** box in Display Options to display the floating widget on all allowed public pages automatically.
- **Shortcode**: Uncheck Auto-inject and drop this shortcode into any page, text block, header, or footer element: `[ai_chatbot]`

* * *

## Models Management

Client AI introduces a database-driven Models system accessible via **Client AI > Models** in the WordPress admin. This replaces the old JSON-only catalog with a flexible, CRUD-capable approach.

### Features

- **Built-in Models**: Automatically seeded from assets/models.json on plugin activation. Marked as "Built-in" and protected from accidental deletion. Legacy "custom-model" seeds have been removed to ensure new custom installations start with a completely clean slate.
- **Custom Models**: Add your own models for any provider. Enter the model ID exactly as required by the API (e.g. `mistralai/mistral-small-3.1-24b-instruct:free` on OpenRouter), give it a display name, description, and configure tool support.
- **Custom Endpoints & Keys**: When adding a model under the Custom / Self-hosted provider, you can specify a model-specific **Base URL Endpoint** and **Custom API Key**. Stored keys are encrypted securely in the database using AES-256-GCM.
- **Edit & Toggle**: Edit any model's display name, description, context window, and flags. Toggle models active/inactive without deleting them.
- **Reset Provider to Defaults**: Remove all custom models for a provider and re-seed the factory defaults — useful after experimenting.

### How It Works

1. On activation, the plugin creates the wp\_aicb\_models database table.
2. All models from assets/models.json are inserted using INSERT IGNORE — safe to re-run without overwriting custom models.
3. The catalog functions (aicb\_get\_catalog(), aicb\_get\_providers(), aicb\_get\_models()) now query the database instead of reading JSON, while falling back to JSON if the table is empty.
4. The Settings page completely isolates the Model selection. Selecting the Custom / Self-hosted active provider card allows you to choose your custom-defined model configurations directly from the standard dropdown menu. All confusing, legacy global text inputs (Endpoint URL, Model ID, and API Key) have been completely removed from the Settings screen.
5. Custom model API requests are routed dynamically. If a custom model has its own endpoint and API key defined, the plugin automatically decrypts the key and targets the model-specific URL.

### Developer Notes

- Editing assets/models.json directly no longer has any effect after the first activation. The JSON file serves as the seed source; the database is the authoritative runtime source.
- The is\_custom flag (0 = seeded, 1 = user-added) enables clean separation between built-in and custom models.
- The tool-support check in aicb\_adapter\_openai\_compat() now queries the supports\_tools column directly instead of relying on fragile string matching against provider names and URLs.

* * *

## Developer Guide

### Advanced Prompt Engineering (Exposed Sub-Prompts)

By expanding the 'Advanced Prompt Engineering' panel on the settings page, developers can modify the core instructions guiding the model's behavior:

- **Temporal Context Template**: Instructs the model on how to handle the current system time and date. Supports {current\_date} and {current\_time} dynamic tag replacements.
- **Tool Coordination Protocol**: Teaches the model exactly when to call the calendar tool vs. reading static FAQs, and how to negotiate rule overrides.
- **Negative Constraints & Integrity**: Governs conversational formatting, strict output length limits, context leak safeguards, and forbidden word exclusions.

If a developer's customizations cause unwanted behavior or system drift, clicking the Reset Engineering Templates button will securely purge custom overrides and restore the factory prompt schemas.

## Future Roadmap

I am working to expand the capabilities of this plugin. Upcoming development priorities, without promises, include:

### Live Discord Integration (Hybrid Support)
Introduce a seamless transition from AI-driven conversations to live human support via Discord. 
* **How it will work:** When a user requests to speak with a live person, a bridge will be established directly between the chat interface and your Discord server using the Discord API.
* **The Experience:** Support agents can respond straight from Discord channels, while the website visitor enjoys a real-time, continuous live chat experience without ever having to leave the chat window.

### Optional Extensions & Add-ons
To keep the core plugin lightweight, advanced features—including the live Discord bridge—will be introduced as optional, modular add-on plugins. This ensures you only install and configure the specific integrations your workflow requires.

* * *

## Open Source & Community License

This plugin is fully open source and released under the GPL-2.0+ license.

Members of the WordPress community are welcome to clone, fork, modify, and redistribute this codebase, provided that any modified distributions are also kept free, open source, and accessible to the public.

If this Client AI integration saved you development hours, improved your customer experience, or made your site more accessible, please consider buying me a coffee to support my ongoing work and contributions to the open-source community!

[![Buy Me A Coffee](https://img.shields.io/badge/Buy%20Me%20A%20Coffee-Donate-FF8F3F?logo=buy-me-a-coffee)](https://buymeacoffee.com/totaldsgn)