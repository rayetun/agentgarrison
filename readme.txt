=== AgentGarrison – AI Bot Control & LLM Optimizer ===
Contributors:      rayetun
Donate link:       https://wise.com/pay/me/mdrayhanu2
Tags:              geo, llm optimizer, ai-seo, llms.txt, ai-bot
Requires at least: 6.2
Tested up to:      7.1
Requires PHP:      7.4
Stable tag:        1.1.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

See whether AI assistants cite your site, control which AI bots can crawl it, and optimize content for ChatGPT, Perplexity, Claude & Gemini.

== Description ==

**AgentGarrison** is an AI visibility toolkit for WordPress. It helps your site get discovered, understood, and cited by AI assistants such as ChatGPT, Perplexity, Gemini, and Claude, while giving you precise control over which AI crawlers can access your content.

**Citation Monitor is the standout feature of AgentGarrison:** it queries AI assistants for your target questions and reports whether your site is actually cited, with a per-competitor breakdown, so you can measure and track your AI visibility over time — not only publish signals for AI to read, but see the result.

**It combines fifteen modules in one plugin:** AI bot access control, AI agent control (Abilities/MCP governance for WordPress 7.1), read-only MCP content tools for AI agents, llms.txt generation with a built-in validator, Markdown output for AI agents, an AI-ready Q&A block, local bot analytics with a live activity feed, LLM referral tracking, an AI readability content scorer, structured data with E-E-A-T trust signals, a citation monitor, a honeypot bot trap, email reports, white-label reports, and a composite AI Visibility Score.

**Everything runs on your own WordPress install:** analytics, logs, and settings are stored in your own database. The only feature that can reach out to an AI provider is the optional Citation Monitor, and it is built for the WordPress 7.0 AI Client: when the site owner configures a provider under Settings → Connectors, AgentGarrison uses the core AI Client and never handles credentials itself. 
On WordPress 6.x (or when no core provider is set up) it falls back to your own API key. Every other feature works with no account and no external service.

= How AgentGarrison is organised =

* **Modular**: each feature is a module you can enable or disable individually, so only what you use runs on your site
* **Local first**: bot analytics, referral logs, and scores are stored in your own database
* **Free features work out of the box**: no signup, no API key required
* **Built on WordPress standards**: on WordPress 7.0+ it uses the core AI Client for any AI provider access, so the site owner configures the provider once under Settings → Connectors and credentials are managed by core, not the plugin
* **Plays well with SEO plugins**: schema output automatically defers overlapping types when another SEO plugin is active, to prevent duplicate markup

---

= 🤖 AI Bot Control =

Take back control of who can crawl your site. AgentGarrison ships with a built-in database of **26+ known AI bots** across five categories — AI Trainers, AI Assistants, AI Search Engines, General Scrapers, and SEO Crawlers.

* **Category-level rules** — allow or block entire categories with one click (e.g. block all AI training bots while keeping AI search bots)
* **Per-bot overrides** — fine-tune individual bots like GPTBot, ClaudeBot, or Bytespider independently of their category
* **Plain-English category notes** — each category explains what blocking it means so you can make informed decisions
* **robots.txt enforcement** — rules are injected into your live `/robots.txt` automatically via WordPress's native filter
* **X-Robots-Tag header injection** — optional HTTP header block for bots that ignore robots.txt (`noai, noimageai`)
* **Import wizard** — reads your existing `robots.txt` on first setup and maps it to the known bot list so no previous configuration is lost
* **Per-bot "Last Seen" timestamps** — see exactly when each bot last visited your site

---

= 🛡️ AI Agent Control (WordPress 7.1) =

WordPress 7.1 mapped the Abilities API onto MCP, so AI agents can now *run actions* on your site — not just read it. Agent Control is the write/act counterpart to Bot Control: govern which Abilities agents may access over MCP (allow or block a whole category, or override individual Abilities — built on the core `mcp_exposed_abilities` filter, off by default so activation never disrupts an existing setup), and keep a local audit log of every Ability execution (Ability, who, source, outcome), pruned on your retention schedule. Dormant on WordPress 6.x; activates automatically once the Abilities API is present.

---

= 🔌 MCP for Agents =

Let AI agents *query* your published content over the Model Context Protocol, using the standard WordPress Abilities API — no scraping. Registers three read-only tools (search content, fetch a page as clean Markdown, site overview) under the `agentgarrison` namespace, so Agent Control governs them alongside every other Ability. Only already-public content is exposed — drafts, private, password-protected, and llms.txt-excluded posts never are. Activates automatically on WordPress 6.9+; a no-op on older versions.

---

= 📄 llms.txt Generator =

`llms.txt` is the emerging standard that tells AI models what your site is about and which pages matter most.

* **Auto-generated at `/llms.txt` and `/llms-full.txt`** — always up to date, no manual editing
* **Customisable site context** — write one sentence describing your site; AI models use it when answering questions about your topic
* **Spec-compliant output** — proper Markdown with an H1 title, summary blockquote, and `[name](url)` hyperlink lists, exactly as the llms.txt standard (llmstxt.org) requires
* **Excluded section** — pages you exclude are listed in their own section so AI models know they're deliberately hidden
* **Health check** — AgentGarrison pings your llms.txt every 12 hours and alerts you on the dashboard if it returns an error or goes stale
* **Auto-respects Yoast and Rank Math noindex** — noindexed pages are automatically excluded
* **robots.txt discovery line** — automatically adds `Llms-txt:` to your robots.txt
* **Custom additions** — append your own links or sections (docs, pricing, partner sites) to the end of the auto-generated file, or switch on full manual override to write the entire file yourself
* **Validator & insights** — checks your file against the spec and shows file size, estimated tokens, and the included/excluded page breakdown
* **Live preview** — see your generated llms.txt inside the admin panel, with copy and download buttons
* **Static file option (opt-in)** — for hosts that 404 the virtual file (e.g. WP Engine), optionally write a real llms.txt to your web root; off by default

---

= 📝 Markdown for Agents =

Serve clean, token-efficient Markdown to AI agents on your normal URLs. Runs entirely on your own site, free, on any host. Off by default; enable it in Settings, Modules.

* **HTTP content negotiation**: when an agent requests a page with `Accept: text/markdown`, AgentGarrison returns Markdown instead of HTML on the same canonical URL (the correct way, not a separate `.md` suffix that ignores the header). Roughly 5 to 6 times fewer tokens.
* **Discovery**: adds a `<link rel="alternate" type="text/markdown">` tag and `Link` header, plus a bookmarkable `?rayetun_ag_md=1` URL, so agents can find the Markdown version.
* **YAML frontmatter**: optional title, URL, date, author, image, categories, and tags.
* **`X-Markdown-Tokens` header**: lets agents budget context before reading.
* **Dependency-free conversion**: a built-in HTML to Markdown converter (no external library) that preserves headings, lists, links, images, blockquotes, and code.
* **Respects your exclusions**: honours the same per-post "exclude from llms.txt" flag, and sends `Vary: Accept` so caches stay correct.

---

= ❓ AI Q&A Block =

A Gutenberg block for question-and-answer content, designed to be extraction-friendly for AI engines.

* **Native collapsible block**: readers see a clean, accessible question they can expand, built on the standard HTML `<details>` element (no JavaScript required on the front end).
* **Automatic FAQ structured data**: the block's markup is picked up by AgentGarrison's Schema module and emitted as FAQPage JSON-LD, the format AI assistants and search engines pull answers from. One emitter, no duplicate schema.
* **No build tooling, no bloat**: a lightweight, dependency-free block. Enabled by default and completely invisible until you add one.

---


= 📊 AI Bot Analytics =

Know exactly which AI systems are crawling your site, how often, and which pages they're most interested in.

* **Real-time bot identification** — matches incoming User-Agent strings against all 26+ known bots and logs every visit
* **Visit deduplication** — prevents database bloat when a bot crawls 500 pages in one session
* **Traffic spike alerts** — email notification when any bot's 24-hour visit rate is 3× above its 7-day baseline
* **Interactive charts** — timeline and donut charts (Chart.js, bundled locally) show bot traffic trends over 7, 30, and 90-day windows
* **Top pages table** — see exactly which of your pages AI bots are targeting most
* **CSV export** — download your raw bot visit data for further analysis

---

= 🔗 LLM Referral Tracker =

When someone asks ChatGPT a question and it recommends your article, that visitor clicks through to your site. AgentGarrison catches those visits.

* **Detects referrals from ChatGPT, Perplexity, Gemini, Claude, and Copilot** — via HTTP Referer header and UTM source parameters
* **Platform breakdown** — see which AI platform sends the most traffic and which pages get cited
* **Post list column** — an "AI Citations" count badge appears next to each article in your post list
* **CSV export** — download your full referral log

---

= ✍️ Content Readability Scorer =

AI models prefer content that is clear, well-structured, and fact-dense. The Content Scorer gives every post a 0–100 AI Readability Score across five dimensions:

1. **Answer Density (25%)** — does your content directly answer the topic in the first 150 words?
2. **Header Hierarchy (20%)** — is your H2/H3 structure logical and sequential?
3. **Fact Density (20%)** — do you include specific numbers, dates, and named entities?
4. **Reading Level (20%)** — approximate grade level optimised for AI comprehension
5. **Clarity (15%)** — penalises vague language that confuses AI models

Scores appear in a post editor sidebar metabox, an "AI Score" post list column, and a bulk dashboard with filtering and pagination. Fix the lowest-scoring posts first for the fastest gains.

---

= 🗂️ Schema & Structured Data =

Help AI models and Google understand your content with a connected JSON-LD `@graph` — nodes linked by `@id` the way Yoast and Rank Math do it, which LLMs parse best.

* **Article** — applied to posts, pages, and custom post types
* **Product** — price, availability, SKU, and ratings for WooCommerce products
* **FAQPage** — auto-detected from question headings, paragraphs, and `<details>/<summary>` blocks, no shortcodes needed
* **HowTo** — auto-detected from "How to" titles with an ordered step list
* **BreadcrumbList** — generated from page hierarchy or primary category
* **WebSite + SearchAction** — site-wide node enabling Google's sitelinks search box
* **Speakable** — marks the title and intro for voice assistants and AI answer extraction
* **LocalBusiness** — optional node with business type, address, geo, hours, and price range for sites with a physical location
* **Organization** — declare your brand, URL, description, and logo once; it appears site-wide
* **Author (Person)** — structured author data linked to articles, with optional credentials (job title, expertise, profile links) from the user profile
* **Trust & Editorial signals (E-E-A-T)** — publishingPrinciples, ethicsPolicy, correctionsPolicy, ownership/funding, founding date, and official profile links (sameAs), plus per-post "reviewed by / last reviewed" — the trust signals AI engines and Google use to decide whether to cite you
* **Per-post control** — a Schema metabox lets you exclude any post, disable specific types, or set a content reviewer, with a live JSON-LD preview
* **Full-page preview** — see the complete `@graph` for any post right in the admin
* **Schema validator** — reports which types are emitted, flags missing recommended properties, shows a Yoast/Rank Math conflict report, and links straight to Google's Rich Results Test
* **Smart conflict detection** — automatically skips Article schema when Yoast SEO or Rank Math is active to prevent duplicate markup

---

= 🔍 AI Visibility Score =

A single composite 0–100 score — shown on the dashboard as a ring gauge — that reflects your overall AI-readiness across five pillars: Bot Access, llms.txt quality, Analytics activity, Content Quality, and Schema setup. Grade A–F at a glance with an actionable checklist to improve each pillar.

---

= Compatible With =

* Yoast SEO
* Rank Math
* AEO God Mode
* All in One SEO
* WooCommerce (product post type support in llms.txt)
* All major WordPress themes

== Installation ==

1. Upload to `/wp-content/plugins/agentgarrison/` or install via Plugins → Add New
2. Activate AgentGarrison
3. Go to **AgentGarrison → Dashboard** in your admin menu
4. Enable the modules you want on the **Settings → Modules** tab
5. Visit **Bot Control** to configure which AI bots can access your site
6. Visit **llms.txt** and click **Regenerate Now**
7. Check your **AI Visibility Score** on the dashboard and follow the checklist

== Frequently Asked Questions ==

= Does AgentGarrison require an external account or API key? =
No external account is needed. Every feature works entirely on your WordPress install. No account, no API key, no data sent to external servers. API keys are only needed if you want live citation data from real AI platforms.

= Will this conflict with Yoast SEO or Rank Math? =
No. AgentGarrison is designed to work alongside your existing SEO plugin. The llms.txt and robots.txt modules use WordPress filter hooks and do not overwrite any files directly.

= What is llms.txt? =
llms.txt is an emerging standard (similar to robots.txt for search engines) that helps AI language models understand your site structure and prioritize your content. AgentGarrison auto-generates it and keeps it updated as you publish.

= My /llms.txt returns a 404 — how do I fix it? =
Some managed hosts (such as WP Engine and other nginx setups) try to serve any .txt request as a static file and return a 404 before WordPress runs. On the llms.txt tab, enable "Write a static llms.txt file to my site root" — AgentGarrison will write a real /llms.txt to your web root so the host serves it directly. This option is off by default; the virtual file works on most hosts. If your host's root isn't writable, ask your host to route /llms.txt through WordPress (the same nginx rule used for the virtual robots.txt), or re-save Permalinks under Settings → Permalinks. The llms.txt page shows a live health-check status so you can confirm it's resolving.

= Which AI bots does AgentGarrison track? =
AgentGarrison tracks 26+ known AI bots including GPTBot (OpenAI), ChatGPT-User (OpenAI), ClaudeBot (Anthropic), PerplexityBot, Google-Extended, Bytespider (TikTok), CCBot (Common Crawl), Diffbot, Amazonbot, Applebot-Extended, YouBot, PetalBot, Meta-ExternalAgent, and more.

= Can I block AI bots from scraping my content for training? =
Yes. In Bot Control, set AI Trainers to "Block" and AgentGarrison will add the appropriate robots.txt Disallow rules. Optionally enable X-Robots-Tag header injection for bots that ignore robots.txt.

= Does AgentGarrison slow down my site? =
No. Bot detection runs only when the User-Agent matches known bot patterns — human visitors are never affected. llms.txt is served via WordPress rewrite rules. All analytics logging includes deduplication to prevent excessive DB writes.

= Is my data sent anywhere? =
By default, no. All data — bot visit logs, referrals, scores — is stored in your own WordPress database, and every core feature works with zero external requests. The only exception is the optional Citation Monitor: on WordPress 7.0+ it uses the built-in core AI Client with whatever provider you configure under Settings → Connectors, and on older sites you can add your own OpenAI or Perplexity API key. Without any provider or key, Citation Monitor runs entirely in Demo Mode using your own content. See the External Services section below for full details.

= What personal data does AgentGarrison store, and how do I comply with GDPR? =
AgentGarrison logs the IP address and User-Agent of bots that crawl your site (and, if you enable the optional Honeypot Bot Trap, any client that hits the hidden trap URL). IP addresses can be personal data, so:

* All of it is stored only in your own database and never transmitted to us or any third party.
* Bot visit records are auto-deleted after the retention period you set in AgentGarrison → Settings (90 days by default).
* The LLM Referral Tracker stores no IP addresses at all.
* AgentGarrison adds suggested text to **Settings → Privacy → Policy Guide**, and integrates with WordPress's built-in **Tools → Export/Erase Personal Data** so you can fulfil data-subject requests.

= Do I need an API key for the Citation Monitor? =
No. The Citation Monitor ships with a Demo Mode that generates realistic citation examples from your own posts, so you can explore the feature with no account or key. API keys are only needed if you want live citation data from real AI platforms.

== Screenshots ==

1. Dashboard — AI Visibility Score ring with pillar breakdown and action checklist
2. Bot Control — visual allowlist/blocklist with category toggles, last-seen timestamps, and per-bot overrides
3. llms.txt Generator — settings panel with live preview and health check status
4. Markdown for Agents - Token-efficient Markdown to AI agents
5. Analytics — bot visit timeline and donut charts (7d/30d/90d) with top pages table
6. Settings — Premium module toggle screen

== Changelog ==

= 1.2.0 =
* New module — MCP for Agents: registers your published content as read-only WordPress Abilities (search content, page-as-Markdown, site overview) so AI agents can query your site over MCP. Governed by Agent Control and exposes only already-public content.
* llms.txt — change history & drift alerts: logs a snapshot each time your llms.txt changes or its reachability flips, shown on the llms.txt screen, and emails you if the file breaks or unexpectedly loses a large share of its pages.
* Analytics — unknown-crawler detection: flags bot-like visitors that crawl several pages but match no known bot (a possible new AI crawler), shown on the Analytics screen. Records the user-agent only, never an IP.
* AI assists (optional): "Suggest with AI" drafts your llms.txt site summary, and "Get AI fixes" suggests concrete edits to raise a post's AI-readability score — both using your own configured AI provider (core AI Client on WP 7.0+, or a BYO key), only when clicked.

= 1.1.0 =
* New module — AI Agent Control: governs which site Abilities are exposed to AI agents over MCP (WordPress 7.1 Abilities API), with category-level and per-Ability allow/block controls (grouped like Bot Control) and a master exposure switch that is off by default. Non-invasive — it never alters exposure unless you opt in to managing it.
* New — Agent Activity Log: records every Ability an AI agent executes on your site (Ability, actor, source, outcome), stored locally and pruned on your retention schedule.
* Both features are dormant on WordPress 6.x and activate automatically once the Abilities API is present.
* Tested up to WordPress 7.1.

= 1.0.0 =
* Initial public release.
* AI Bot Control: category-level and per-bot allow/block for 26+ known AI bots, robots.txt enforcement, optional X-Robots-Tag header, per-bot last-seen timestamps, and a robots.txt import wizard.
* llms.txt Generator: auto-generated /llms.txt and /llms-full.txt with a spec validator, health check, custom additions, manual override, drift detection, and an opt-in static file for hosts that serve .txt statically.
* Markdown for Agents (opt-in): serves clean, token-efficient Markdown to AI agents via Accept: text/markdown content negotiation, with discovery links, YAML frontmatter, and a dependency-free HTML to Markdown converter.
* AI Q&A block: a Gutenberg block for question-and-answer content that renders as a native collapsible block and is auto-detected as FAQ structured data.
* AI Bot Analytics: local bot-visit logging with timeline and donut charts, visit deduplication, traffic-spike email alerts, and CSV export.
* LLM Referral Tracker: detects human visits arriving from ChatGPT, Perplexity, Gemini, Claude, and Copilot.
* Content Readability Scorer: a 0-100 AI readability score per post across five dimensions, with an editor metabox and bulk dashboard.
* Schema and Structured Data: a connected JSON-LD @graph with Article, Product, FAQPage, HowTo, Breadcrumb, and E-E-A-T author trust signals, plus a validator.
* Citation Monitor: track whether AI assistants cite your pages for your questions, with a visibility scorecard and competitor leaderboard. Uses the WordPress 7.0 core AI Client, or your own OpenAI, Anthropic, or Perplexity key; runs in demo mode without a key.
* Honeypot bot trap, email reports, white-label PDF-ready reports, onboarding wizard, multisite network view, and a composite AI Visibility Score with an improvement checklist.
* Privacy: suggested privacy-policy text and integration with WordPress Export/Erase Personal Data tools. Everything runs locally on your own site.

== Upgrade Notice ==
= 1.1.0 =
Adds AI Agent Control for WordPress 7.1 — govern which Abilities AI agents can run over MCP, with a full activity log. Dormant on older WordPress.

= 1.0.0 =
First public release of AgentGarrison.

== External Services ==

Every core AgentGarrison feature works entirely on your own server with no external requests. The only outbound connections are the optional, opt-in integrations below — none of them run unless you explicitly provide an API key.

The optional AI assists — "Suggest with AI" for your llms.txt summary, and "Get AI fixes" in the Content Scorer — use the same AI provider you configure below (the WordPress core AI Client on 7.0+, or your own key). They send only the relevant content (your site name and recent page titles, or the post you are editing) and only when you click the button.

On WordPress 7.0 and later, Citation Monitor uses the built-in core AI Client instead of the direct API calls below. In that case the site owner configures the AI provider once under Settings → Connectors, WordPress core manages the credentials and the outbound request, and AgentGarrison does not send data to any AI provider directly. The direct integrations below apply only on WordPress 6.x, or when a core AI provider is not configured.

**llms.txt Health Check — self-ping (not a third party)**
The health check requests your own site's `/llms.txt` URL using `wp_remote_get()` to confirm it is reachable. This is a request from your server to itself. No data is sent to any third party.

**Anthropic (Claude) — Citation Monitor (optional)**
Only used if you enter an Anthropic API key in Citation Monitor. When a scan runs, AgentGarrison sends your tracked questions to Anthropic's Messages API to determine whether your pages are cited.
Data sent: your tracked question strings and your API key.
Sent when: only when an Anthropic API key is saved and a citation scan runs.
Service URL: https://www.anthropic.com
Terms of Service: https://www.anthropic.com/legal/consumer-terms
Privacy Policy: https://www.anthropic.com/legal/privacy

**OpenAI (ChatGPT) — Citation Monitor (optional)**
Only used if you enter an OpenAI API key in Citation Monitor. When the daily scan runs, AgentGarrison sends your tracked keywords to OpenAI's Chat Completions API to determine whether your pages are cited.
Data sent: your tracked keyword strings and your API key.
Sent when: only when an OpenAI API key is saved and the daily citation scan runs.
Service URL: https://openai.com
Terms of Service: https://openai.com/policies/terms-of-use/
Privacy Policy: https://openai.com/policies/privacy-policy/

**Perplexity — Citation Monitor (optional)**
Only used if you enter a Perplexity API key in Citation Monitor. AgentGarrison sends your tracked keywords to Perplexity's Chat Completions API and reads the returned citations.
Data sent: your tracked keyword strings and your API key.
Sent when: only when a Perplexity API key is saved and the daily citation scan runs.
Service URL: https://www.perplexity.ai
Terms of Service: https://www.perplexity.ai/hub/legal/terms-of-service
Privacy Policy: https://www.perplexity.ai/hub/legal/privacy-policy

On WordPress 7.0+, if you have configured an AI provider (such as Anthropic / Claude) under Settings → Connectors, the Citation Monitor uses the built-in WordPress core AI Client instead of the direct API calls above. WordPress core manages the credential and the outbound request in that case.

Without any AI provider or API key, the Citation Monitor runs in Demo Mode and generates example citations from your own posts — no external requests are made.
