# MCP server: listing it so agents find it

The server is live at **https://qr.viennastruggle.at/mcp** (Streamable HTTP, stateless, no key needed).
Docs for people: https://qr.viennastruggle.at/en/developers · `/de/entwickler`.

## What agents can do

| Tool | Without key | Free key | Pro |
|---|---|---|---|
| `create_qr_code` (style: dots, corners, colors, emoji) | 10 codes/day, returns an edit link for the human | 100/day | 5,000/day |
| `update_qr_code`, `get_qr_code_stats` | with the edit link | own codes | own codes |
| `list_qr_codes`, `create_qr_codes_bulk` | – | bulk ≤ 20/call | bulk ≤ 500/call |
| requests per hour | 30 | 120 | 2,000 |

Quotas live in `api_limits()` in `lib/plan.php`. That's the knob for paid plans later.
Admins switch a user to Pro on the Users page; "Request Pro" mails the master admin.

## 1. Official MCP Registry (feeds many clients and directories)

`server.json` in the repo root is ready (namespace `io.github.mandymozart/qr`, so it's verified with your GitHub login):

```sh
# install the publisher (see https://github.com/modelcontextprotocol/registry, "publisher" docs)
mcp-publisher login github      # opens GitHub in the browser, log in as mandymozart
mcp-publisher publish           # run in the repo root, uses server.json
```

If `publish` complains about the schema, run `mcp-publisher init` once and copy the `remotes` block from our `server.json` into the generated file. Bump `version` for every later change.

## 2. Directories (submit by hand, ~10 min each)

- **Smithery** (smithery.ai): "Add server" → remote URL `https://qr.viennastruggle.at/mcp`.
- **Glama** (glama.ai/mcp/servers): indexes GitHub repos with MCP servers, submit the repo.
- **mcp.so**: "Submit" → name, URL, short description.
- **PulseMCP** (pulsemcp.com): "Submit server".
- **awesome-mcp-servers** on GitHub: a pull request adding one line under "Other tools / Utilities".

Suggested one-liner:
> **qr**: create free dynamic QR codes that never expire, change their target later, privacy-friendly scan stats. Remote MCP, no key needed. https://qr.viennastruggle.at/mcp

## 3. Check that it works

```sh
curl -s -X POST https://qr.viennastruggle.at/mcp -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'
```

Or with the official inspector: `npx @modelcontextprotocol/inspector` → Transport "Streamable HTTP" → the URL above.

## Watch

- Admin → Guest codes shows codes created anonymously through the API/MCP (pause or delete abuse).
- `api_calls` table: who calls what (kept 3 days).
