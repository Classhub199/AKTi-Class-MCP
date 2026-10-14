# Rashid Ahm MCP - Setup Guide

Made from scratch by Rashid Ahmad. Help: rashid.ahm199@outlook.com

## 1. Install
1. WordPress admin > **Plugins > Add New > Upload Plugin**.
2. Choose `rashid-ahm-mcp.zip`, press **Install Now**, then **Activate**.
3. A menu **Rashid Ahm MCP** appears on the left with three screens: **Connect AI**, **Expert scores**, **Settings**.

On Pantheon (or similar managed hosting) switch the Dev environment to **SFTP mode** first. Without it, WordPress cannot install plugins or write files.

## 2. Connect AI (one key per AI account)
1. Open **Rashid Ahm MCP > Connect AI**.
2. Tick the roles you need. A good team: **Supervisor + Designer + SEO + Copywriter**. With one AI account, tick **Solo**.
3. Choose how long the keys last: 24 hours, 3 days, 30 days or 1 year, then press **Generate keys**.
4. Each key shows an **MCP link** and a **starting command**. **Copy them now.** For safety the link is shown only once. Treat it like a password.
5. In the AI app, add a remote MCP server / custom connector and paste **one link**. Use a different AI account for each role. Whether a plan allows custom connectors depends on the AI app.
6. Paste the **starting command** in that AI's chat and write your task where it says `[write your task here]`.

You can start the roles one after another. They do not have to work at the same time. AI accounts cannot message each other, so tell each one what to do, or ask the Supervisor to check the others' work.

## 3. How a change goes live
1. The AI tells you its plan in the chat and asks: "Do you want this or not?"
2. When you say **yes**, it makes the change and the change goes **live on your website right away**.
3. Drafts need no yes. Only things visitors can see do.
4. Every change is backed up first. Ask the AI "undo the last change" and it restores it (kept for 30 days).

Want it even faster? In **Settings** switch off **Ask me in the chat before anything goes live**. Then the AI pushes changes immediately without asking. Backups and undo still work.

## 4. Connection check
At the bottom of **Connect AI** the plugin tests your site's REST address and explains the result in plain words:
- **403:** a security plugin, firewall or hosting rule blocks `/wp-json/`. Ask your host to allow `/wp-json/rahm/v1/`. (This plugin already gets past "disable REST API for visitors" plugins for its own address only.)
- **401:** something asks for a login before WordPress. Allow that address without a login.
- **404:** go to Settings > Permalinks in WordPress and press Save once.

Press **Run server test** to make the server call its own address as well. It says whether the plugin answers correctly (a fake key must be refused with 401).

If this check is green but your AI app still shows 403, the block is between the AI app and your host (often a firewall that stops requests from outside). Ask your host to allow requests to the address shown on the page.

## 5. Expert scores
Press **Measure now** on a card to score your real pages out of 100 for SEO, **AEO/GEO** (being quoted by answer engines and AI chat tools), copywriting, design, speed, marketing or quality. The AI experts can also post written reports here.

## 6. Settings
- **Language:** English or Urdu.
- **Going live:** ask-first switch, read-only mode, theme and plugin file edits, PHP edits, maintenance page.
- **Brand profile:** name, tone, languages, colors and rules. Every AI reads it.
- **Your own playbooks** (optional): start each with `## name`.

## 7. Built-in playbooks and SEO tools
Each AI loads its playbook with the `skill` tool: a launch-quality checklist, design rules, color palettes, mobile-first rules, site structure, banners, realistic image prompts, WordPress and Elementor safety, security, speed, keyword research, full SEO (internal and external linking, one page and all pages), AEO/GEO, copywriting, social media and ad audiences.

The SEO role can also add an **FAQ with structured data** to any page, set **JSON-LD schema** (Organization, LocalBusiness, Article, Product...), check permalinks, tags, links and the sitemap. Nobody can guarantee rankings or being quoted by an AI; the plugin measures what it can and tells you the rest.

## 8. If something goes wrong
| Problem | What to do |
|---|---|
| "This key has expired" | Generate a new key in Connect AI. |
| Link shows 404 | Settings > Permalinks > Save once. |
| 403 from the AI app | Use the Connection check and Run server test, then ask your host to allow `/wp-json/rahm/v1/`. |
| A change looks wrong | Tell the AI "undo the last change". |
| Expert scores say they could not open pages | Your host blocks the site from calling itself. Ask the host to allow it. |
| Anything else | rashid.ahm199@outlook.com |
