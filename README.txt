=== Rashid Ahm MCP ===
Version: 2.3.0
Made from scratch by Rashid Ahmad.  Help: rashid.ahm199@outlook.com
Requires WordPress 5.8+ and PHP 7.4+

An AI agency team for WordPress. Connect Claude, ChatGPT or any app that supports remote MCP.
Give each AI account a role (Supervisor, Designer, SEO, Copywriter, Marketing, Translator, QA,
Developer, Store manager, or Solo for one account). Say yes in the chat and the change goes live on
your site. Every change is backed up and can be undone for 30 days.

== The three screens ==
1. Connect AI - make one key per AI account (role + expiry: 24 hours, 3 days, 30 days or 1 year).
   The MCP link and a ready starting command are shown once. A connection check (in the browser and
   from the server) tells you whether your site's REST address is reachable and explains errors such as 403.
2. Expert scores - SEO, AEO/GEO (answer engines and AI chat tools), copywriter, designer, speed, marketing
   and quality scores out of 100,
   measured on your real pages, plus the experts' written reports.
3. Settings - English/Urdu, "ask me before going live" switch, read-only mode, file and PHP edit
   switches, maintenance page, brand profile and your own playbooks.

== Install ==
1. Plugins > Add New > Upload Plugin > choose the zip > Install > Activate.
2. Rashid Ahm MCP > Connect AI > tick roles > Generate keys.
3. Paste each MCP link into an AI app (remote MCP server / custom connector).
4. Paste the starting command in that AI's chat and write the task.

== How changes go live ==
* Drafts, notes and reports need no confirmation.
* Anything visitors can see: the AI tells you its plan and asks. When you say yes it repeats the call with
  confirm=true and the change goes live right away. There is no approval queue.
* Settings > "Ask me in the chat before anything goes live" can be switched OFF to push changes
  immediately without asking (still backed up and undoable).

== Tools (16) ==
brief, skill, batch, content, media, design, elementor, plugins, code, seo, rest, qa, site, inbox
(notes and reports), audit, history (undo and activity).
The seo tool also adds an FAQ block with FAQPage structured data (faq) and JSON-LD schema (schema).

== Built-in playbooks (skill tool) ==
professional_site (launch checklist), workflow, supervisor, designer, design_system, color_palette,
mobile_first, site_structure, banner, image_prompts, elementor, developer, wordpress, security,
performance, copywriter, seo, keyword_research, aeo_geo, marketing, social_marketing, audiences,
ecommerce, translator, qa, strategist. Add your own in Settings.

== Safety ==
* Every change takes a backup first and can be undone for 30 days (pages, Elementor layouts, CSS,
  settings, theme and plugin files). PHP is syntax-checked before it is written.
* Keys are stored hashed, expire, and can be turned off at any time. Treat each MCP link like a password.
* Read-only mode lets AI look but not change anything.
* This plugin cannot edit its own files or install itself.

== Known limits ==
* On managed hosts such as Pantheon Dev, switch to SFTP mode before installing plugins or editing files.
* A firewall or hosting rule can block AI apps from reaching /wp-json/ (they then report 403). The
  connection check on the Connect AI page shows this.
* Expert scores open your pages from the server; if the host blocks that, the page says so instead of
  showing a false score.

== Changelog ==
2.3.0 - AEO/GEO score, FAQ and schema tools, keyword/AEO/mobile/site-structure/launch playbooks,
        stronger SEO, speed and security playbooks. 403 fixes: our routes pass "disable REST API" security
        plugins, browser-like user agent for self-checks, server self-test button.
2.2.0 - Only three screens: Connect AI, Expert scores, Settings. Live Studio, Activity and Help removed.
        Connection check, "ask before going live" switch, new design/color/banner/image/WordPress/social/audience playbooks.
2.1.0 - Approvals removed; tool names with prefixes such as mcp__Rashid__rest are accepted.
2.0.0 - First version of the new plugin.
