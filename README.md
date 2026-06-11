<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Chatbot (Modular) - Documentation</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji";
            font-size: 16px;
            line-height: 1.6;
            word-wrap: break-word;
            max-width: 880px;
            margin: 40px auto;
            padding: 0 20px;
            color: #24292f;
            background-color: #ffffff;
        }
        h1, h2, h3 {
            margin-top: 24px;
            margin-bottom: 16px;
            font-weight: 600;
            line-height: 1.25;
            color: #0f172a;
        }
        h1 {
            font-size: 2em;
            padding-bottom: 0.3em;
            border-bottom: 1px solid #d8dee4;
        }
        h2 {
            font-size: 1.5em;
            padding-bottom: 0.3em;
            border-bottom: 1px solid #d8dee4;
        }
        h3 {
            font-size: 1.25em;
        }
        p, ul, ol {
            margin-top: 0;
            margin-bottom: 16px;
        }
        ul, ol {
            padding-left: 2em;
        }
        li {
            margin-top: 0.25em;
        }
        hr {
            height: 0.25em;
            padding: 0;
            margin: 24px 0;
            background-color: #d8dee4;
            border: 0;
        }
        code, pre {
            font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "Liberation Mono", monospace;
            font-size: 85%;
            background-color: rgba(175, 184, 193, 0.2);
            border-radius: 6px;
            padding: 0.2em 0.4em;
            margin: 0;
        }
        pre {
            padding: 16px;
            overflow: auto;
            line-height: 1.45;
            background-color: #f6f8fa;
            border-radius: 6px;
            margin-bottom: 16px;
        }
        pre code {
            background-color: transparent;
            padding: 0;
            margin: 0;
            font-size: 100%;
            word-break: normal;
            white-space: pre;
            border: 0;
        }
        a {
            color: #0969da;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        .badge-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
            margin-bottom: 24px;
        }
        .badge {
            display: inline-block;
        }
        .badge img {
            border-radius: 3px;
            vertical-align: middle;
        }
        .callout {
            padding: 16px;
            margin-top: 24px;
            margin-bottom: 24px;
            background-color: #f0fdf4;
            border-left: 4px solid #16a34a;
            border-radius: 6px;
        }
        .callout p {
            margin: 0;
            font-weight: 500;
            color: #14532d;
        }
        .callout a {
            color: #166534;
            font-weight: bold;
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <h1>AI Chatbot (Modular)</h1>
    <p>A pro-grade, modular, and highly secure AI chatbot engine for WordPress. It supports native function calling, dynamic multi-provider LLM adapters, a searchable global holiday seeder, and advanced prompt engineering controls.</p>

    <div class="badge-container">
        <span class="badge"><img src="https://img.shields.io/badge/version-2.2.8-blue.svg" alt="Version"></span>
        <span class="badge"><img src="https://img.shields.io/badge/WordPress-6.0%2B-0073aa.svg" alt="WordPress"></span>
        <span class="badge"><img src="https://img.shields.io/badge/PHP-8.0%2B-777bb4.svg" alt="PHP"></span>
        <span class="badge"><img src="https://img.shields.io/badge/license-GPL--2.0-green.svg" alt="License"></span>
        <a class="badge" href="https://buymeacoffee.com/totaldsgn" target="_blank" rel="noopener">
            <img src="https://img.shields.io/badge/Buy%20Me%20A%20Coffee-Donate-FF8F3F?logo=buy-me-a-coffee" alt="Buy Me A Coffee">
        </a>
    </div>

    <hr>

    <h2>How It Works</h2>
    <p>This plugin delivers a flexible, robust, and highly secure artificial intelligence assistant to your WordPress website. Built on a clean MVC modular architecture, it separates database logic, front-end visual presentation, and API routing.</p>
    <p>The chatbot leverages advanced <strong>Strategy A retrieval mechanics</strong> to digest localized page content dynamically, merging it with custom business FAQ rules, real-time temporal pivots, and deterministic schedule validation via native LLM tool-calling (function calling).</p>

    <hr>

    <h2>Key Features</h2>
    <ul>
        <li><strong>Multi-Provider AI Adapters</strong>: Integrated connection adapters for Anthropic (Claude), Google AI Studio (Gemini), Groq (Llama), Cerebras, Mistral, and custom self-hosted OpenAI-compatible endpoints (Ollama, LM Studio, etc.).</li>
        <li><strong>Deterministic Calendar Tool</strong>: Empower the AI to answer business opening-hours questions using the <code>check_calendar</code> function. It supports dynamic weekday/weekend defaults, specific date overrides, and annual recurring dates.</li>
        <li><strong>Global Holiday Seeder</strong>: Connects to the Nager.Date API with a searchable autocomplete country selector. This allows you to automatically pull and seed holidays from any supported country directly into your schedules database.</li>
        <li><strong>Advanced Prompt Engineering</strong>: A toggleable developer panel inside your settings page lets you inspect, edit, and experiment with the sub-prompts coordinating the AI’s temporal pivots, tool-calling protocols, and negative constraints. Complete with a secure "Reset to Defaults" option.</li>
        <li><strong>Strategy A Context Retrieval</strong>: Intelligently compiles clean text digests of your allowed post types on save, caching condensed summaries to significantly reduce API token usage and latency.</li>
        <li><strong>Custom Q&A Overrides</strong>: Prioritized semantic matching table to bypass expensive LLM inference entirely for exact business FAQs and keywords.</li>
        <li><strong>Accessible Slide-Out Interface (WCAG 2.2 AA)</strong>: Fully responsive and transition-smooth sliding panel widgets for <code>tab-right</code> and <code>tab-left</code> placements. Includes top-aligned, upright emoji icons, focus-visible outline states, and native HTML5 <code>hidden</code> toggle alignments.</li>
        <li><strong>Enterprise Cryptography & Security</strong>: Secure AES-256-GCM database encryption for API keys, strict Server-Side Request Forgery (SSRF) endpoint filters, WP nonce verification, and CDN-aware proxy IP rate limiting.</li>
        <li><strong>Audit Logs & Cleanup</strong>: Paginated, filterable conversation logging with automated background cron cleanup tasks to manage database storage.</li>
    </ul>

    <hr>

    <h2>Installation & Setup</h2>

    <h3>1. Installation</h3>
    <ol>
        <li>Download or clone this repository into a folder named <code>ai-chatbot-modular</code>.</li>
        <li>Move the directory to your <code>/wp-content/plugins/</code> directory.</li>
        <li>Activate <strong>AI Chatbot (Modular)</strong> inside your WordPress <strong>Plugins</strong> screen.</li>
    </ol>

    <h3>2. Basic Configuration</h3>
    <ol>
        <li>Go to <strong>AI Chatbot > Settings</strong> in your WordPress admin menu.</li>
        <li>Select your active AI Provider and input your API credentials.</li>
        <li>Choose your desired model from the dynamic catalog.</li>
        <li>Set your primary brand color, chatbot title, and default welcome message.</li>
        <li>Save your settings.</li>
    </ol>

    <h3>3. Display Options</h3>
    <p>You can output the chatbot toggle on your site in two ways:</p>
    <ul>
        <li><strong>Automatic Injection</strong>: Check the <strong>Auto-inject</strong> box in <strong>Display Options</strong> to display the floating widget on all allowed public pages automatically.</li>
        <li><strong>Shortcode</strong>: Uncheck Auto-inject and drop this shortcode into any page, text block, header, or footer element:
            <pre><code>[ai_chatbot]</code></pre>
        </li>
    </ul>

    <hr>

    <h2>Developer Guide</h2>

    <h3>Advanced Prompt Engineering (Exposed Sub-Prompts)</h3>
    <p>By expanding the <strong>Advanced Prompt Engineering</strong> panel on the settings page, developers can modify the core instructions guiding the model's behavior:</p>
    <ol>
        <li><strong>Temporal Context Template</strong>: Instructions guiding how current system date details are presented. Supports <code>{current_date}</code> and <code>{current_time}</code> dynamic tag replacements.</li>
        <li><strong>Tool Coordination Protocol</strong>: Instructs the model on exactly when and how to call the calendar schedule tool, and how to verify rule overrides.</li>
        <li><strong>Negative Constraints & Integrity</strong>: Governs conversational formatting, strict output length limits, context leak filters, and forbidden word exclusions.</li>
    </ol>
    <p>If a developer's customizations cause unwanted behavior or system drift, clicking the <strong>Reset Engineering Templates</strong> button will securely purge custom overrides and restore the factory prompt schemas.</p>

    <hr>

    <h2>Open Source & Community License</h2>
    <p>This plugin is <strong>fully open source</strong> and released under the <strong>GPL-2.0+</strong> license.</p>
    <p>Members of the WordPress community are welcome to clone, fork, modify, and redistribute this codebase, provided that any modified distributions are also kept free, open source, and accessible to the public.</p>

    <div class="callout">
        <p>If this modular AI chatbot integration saved you development hours, improved your customer experience, or made your site more accessible, please consider <a href="https://buymeacoffee.com/totaldsgn" target="_blank" rel="noopener">buying me a coffee</a> to support my ongoing work and contributions to the open-source community!</p>
    </div>

</body>
</html>
