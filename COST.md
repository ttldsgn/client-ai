# ClientAI — API Cost & Token Guide

An architectural breakdown of token utilization, caching mechanics, and real-world running costs.

> **📅 Pricing Snapshot:** All model calculations, provider rates, and token estimates in this guide reflect live pay-as-you-go rates as of **Friday, June 26, 2026**. These figures are a dated snapshot and are subject to change; consult each provider's official pricing page for current rates.

When deploying an AI chatbot on your WordPress website, one of the primary concerns is the ongoing running cost of LLM APIs. This guide is designed to demystify how `ClientAI` processes data, how the local caching system actively works to protect your wallet, and what the real-world monthly costs look like across different traffic profiles.

---

## 1. How the Local Caching System Saves Money

Traditional AI plugins send your entire website's raw text to the AI model on every single user question. This results in massive input token usage, high API bills, and sluggish response times. 

`ClientAI` uses an advanced retrieval pattern (RAG) combined with background caching to bypass this overhead entirely:

* **Background Summarization (Warming):** The plugin features a background cache compiler. It processes your pages asynchronously inside your admin dashboard, compressing lengthy articles into dense, 500-character factual digests.
* **No Live Caching Overhead:** The dynamic AI summarizer is completely decoupled from your frontend chat window. When a visitor asks a question, the server never makes slow, nested, synchronous API calls on-the-fly.
* **Keyword-Targeted Context:** When a user asks a question, the chatbot runs a highly selective, keyword-targeted query (leveraging WordPress's native search engine) to retrieve *only* the top relevant page summaries. The remaining pages on your website stay on the shelf, costing you **zero** tokens.

---

## 2. Real-World Running Costs

The majority of visitors on small-to-medium websites do not open the chat widget. Based on global benchmarks, the average chat engagement rate sits around **3%** of total page traffic. 

Assuming an average conversation session consists of **4 queries**, and each query uses approximately **1,800 input tokens** (RAG context + prompt) and generates a **200 output token** response:

| Site Traffic Profile | Chatting Users / Month | Total Queries / Month | Est. Monthly Cost (8B Model)¹ | Est. Monthly Cost (70B Model)² |
| :--- | :--- | :--- | :--- | :--- |
| **Low Traffic** (5,000 visitors/mo) | 150 users | 600 queries | **$0.06 / mo** | **$0.73 / mo** |
| **Medium Traffic** (20,000 visitors/mo) | 600 users | 2,400 queries | **$0.25 / mo** | **$2.93 / mo** |
| **High Traffic** (50,000 visitors/mo) | 1,500 users | 6,000 queries | **$0.62 / mo** | **$7.31 / mo** |

*¹ Calculated using **Llama 3.1 8B Instant on Groq** ($0.05 / 1M input; $0.08 / 1M output, as of June 26, 2026 — see [Groq Pricing](https://console.groq.com/settings/billing)).*  
*² Calculated using **Llama 3.3 70B Versatile on Groq** ($0.59 / 1M input; $0.79 / 1M output, as of June 26, 2026 — see [Groq Pricing](https://console.groq.com/settings/billing)).*

---

## 3. Pro-Tip: Achieving "0-Token" Answers

You can completely bypass AI costs for your most commonly asked questions by using the plugin's built-in **Custom Q&A** feature:

1. Monitor the **Most Asked Questions** card on your `ClientAI > Dashboard` to spot repetitive queries (e.g., *"What is your email?"* or *"Where is your pricing?"*).
2. Add those exact questions and answers to your local **Custom Q&A** database.
3. The next time a visitor asks that question, the chatbot returns your answer directly from the local database in under **5 milliseconds**, utilizing **0 AI tokens and costing $0.00**.

---

## 4. Switching to Pay-As-You-Go Paid Tiers

While providers like Groq or Cerebras offer generous free tiers, they have strict Rate Limits (Requests Per Minute) and are prone to cold starts. To ensure a stable, sub-second production experience on your site, it is highly recommended to upgrade to their Paid/Developer Tiers.

> **💡 Minimum Balance Reassurance:** Paid API accounts usually require a minimum pre-paid deposit of **$10.00** to activate (snapshot as of June 26, 2026; confirm with your provider). Because your monthly usage is so efficient, a $10.00 pre-paid balance would easily last **over 12 months** of active usage on a low-traffic business site before requiring a top-up.

---
*ClientAI is a fully self-hosted, open-source WordPress chatbot released under GPLv2.*