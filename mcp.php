<?php
declare(strict_types=1);

/*
 * MCP server (Model Context Protocol) for AI agents: https://qr.viennastruggle.at/mcp
 * Transport: Streamable HTTP, JSON responses (no SSE stream needed), stateless.
 * Auth: optional "Authorization: Bearer qr_…" (an API key from the account page). Without a key the agent
 * can still create single codes (anonymous quota) and gets an edit link for the human.
 */
require_once __DIR__ . '/lib/app.php';
require_once __DIR__ . '/lib/api.php';

const MCP_VERSIONS = ['2025-06-18', '2025-03-26', '2024-11-05'];
const MCP_SERVER = ['name' => 'qr-codes', 'title' => 'qr: QR codes that never expire', 'version' => '1.0.0'];

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, X-API-Key, Content-Type, Mcp-Session-Id, Mcp-Protocol-Version');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('X-Robots-Tag: noindex');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // no server-initiated stream; a short note for humans who open the URL
    http_response_code(405);
    header('Allow: POST');
    header('Content-Type: text/plain; charset=utf-8');
    echo "This is an MCP server for AI agents (Streamable HTTP, POST JSON-RPC).\nAdd it to your AI app as a remote MCP server with this URL: " . base_url() . "/mcp\nDocs: " . base_url() . "/en/developers\n";
    exit;
}

function mcp_tools(): array
{
    $style = ['type' => 'object', 'description' => 'Optional look. Dark colors only; every style is test-scanned.', 'properties' => [
        'shape' => ['type' => 'string', 'enum' => QR_SHAPES, 'description' => 'square modules or round dots'],
        'corners' => ['type' => 'string', 'enum' => QR_EYES],
        'color' => ['type' => 'string', 'enum' => array_keys(QR_COLORS)],
        'dots_color' => ['type' => 'string', 'description' => 'Custom hex color like #1f4fd8 (must be dark)'],
        'corners_color' => ['type' => 'string'],
        'emoji' => ['type' => 'string', 'enum' => QR_EMOJIS, 'description' => 'Emoji in the middle (Twemoji code point), e.g. 1f3a8 = 🎨, 1f3b8 = 🎸, 1faa9 = 🪩'],
    ]];
    $code = ['type' => 'string', 'description' => 'The short code, e.g. "k7Fq2" (the part after ?c= in the short URL)'];
    $editKey = ['type' => 'string', 'description' => 'For codes created without an API key: the edit_url (or its key) returned at creation'];
    return [
        ['name' => 'create_qr_code', 'title' => 'Create a QR code',
            'description' => 'Create a dynamic QR code for a link. The code never expires and its target can be changed later. Returns the short URL, PNG/SVG image URLs and the QR image. Without an API key it also returns an edit_url: give that to the user, it is their only way to change the code later.',
            'inputSchema' => ['type' => 'object', 'required' => ['target_url'], 'properties' => [
                'target_url' => ['type' => 'string', 'description' => 'Full http(s):// URL the code should open'],
                'title' => ['type' => 'string', 'description' => 'Optional name, e.g. "Poster Westbahnhof"'],
                'style' => $style]],
            'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false, 'openWorldHint' => false]],
        ['name' => 'update_qr_code', 'title' => 'Change where a QR code leads',
            'description' => 'Change the target URL (or title, or pause/activate) of an existing code. Printed copies keep working and now lead to the new target. Needs the API key of the owner or the edit_url of an anonymous code.',
            'inputSchema' => ['type' => 'object', 'required' => ['code'], 'properties' => [
                'code' => $code, 'target_url' => ['type' => 'string'], 'title' => ['type' => 'string'],
                'active' => ['type' => 'boolean', 'description' => 'false pauses the code (scanners see a friendly note)'], 'edit_key' => $editKey]],
            'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false]],
        ['name' => 'get_qr_code_stats', 'title' => 'QR code scan statistics',
            'description' => 'Scans, daily unique visitors, scans per day, top cities and devices of a code. Anonymous and privacy-friendly (no IPs, no cookies).',
            'inputSchema' => ['type' => 'object', 'required' => ['code'], 'properties' => [
                'code' => $code, 'days' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 365, 'default' => 30], 'edit_key' => $editKey]],
            'annotations' => ['readOnlyHint' => true, 'openWorldHint' => false]],
        ['name' => 'list_qr_codes', 'title' => 'List my QR codes',
            'description' => 'List the codes of the account behind the API key, newest first, with scan counts. Needs an API key.',
            'inputSchema' => ['type' => 'object', 'properties' => ['limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20], 'offset' => ['type' => 'integer', 'default' => 0]]],
            'annotations' => ['readOnlyHint' => true, 'openWorldHint' => false]],
        ['name' => 'create_qr_codes_bulk', 'title' => 'Create many QR codes',
            'description' => 'Create several codes at once, optionally in a new campaign (e.g. one code per poster location). Needs an API key; the maximum per call depends on the plan.',
            'inputSchema' => ['type' => 'object', 'required' => ['items'], 'properties' => [
                'campaign' => ['type' => 'string', 'description' => 'Optional campaign name'],
                'items' => ['type' => 'array', 'items' => ['type' => 'object', 'required' => ['target_url'], 'properties' => ['target_url' => ['type' => 'string'], 'title' => ['type' => 'string']]]]]],
            'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'openWorldHint' => false]],
        ['name' => 'get_limits', 'title' => 'Plan and quotas',
            'description' => 'Shows the current plan (anonymous, free or pro) and its quotas.',
            'inputSchema' => ['type' => 'object', 'properties' => new stdClass()],
            'annotations' => ['readOnlyHint' => true, 'openWorldHint' => false]],
    ];
}

/** Runs one tool. Returns MCP CallToolResult. */
function mcp_call(string $name, array $args, ?array $user): array
{
    $text = fn(array $data, string $lead) => ['type' => 'text', 'text' => $lead . "\n\n" . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)];
    switch ($name) {
        case 'create_qr_code':
            $c = api_create($user, $args, 'mcp');
            $link = q('SELECT * FROM links WHERE code = ?', [$c['code']])->fetch();
            $lead = "Created QR code {$c['short_url']} → {$c['target_url']}. PNG: {$c['image_png']} · SVG (for print): {$c['image_svg']}"
                . (isset($c['edit_url']) ? "\nIMPORTANT: give the user this private edit link, it is the only way to change the code later: {$c['edit_url']}" : '')
                . (isset($c['warning']) ? "\nNote: {$c['warning']}" : '');
            return ['content' => [$text($c, $lead), ['type' => 'image', 'data' => api_png_base64($link), 'mimeType' => 'image/png']], 'structuredContent' => $c];
        case 'update_qr_code':
            $link = api_owned($user, (string) ($args['code'] ?? ''), api_edit_key((string) ($args['edit_key'] ?? '')));
            api_meter($user, 'update', 0, 'mcp');
            $c = api_update($link, array_intersect_key($args, array_flip(['target_url', 'title', 'active'])));
            return ['content' => [$text($c, "Updated {$c['short_url']} → {$c['target_url']}" . ($c['active'] ? '' : ' (paused)'))], 'structuredContent' => $c];
        case 'get_qr_code_stats':
            $link = api_owned($user, (string) ($args['code'] ?? ''), api_edit_key((string) ($args['edit_key'] ?? '')));
            api_meter($user, 'stats', 0, 'mcp');
            $s = api_stats($link, (int) ($args['days'] ?? 30));
            return ['content' => [$text($s, "{$s['scans_in_period']} scans in the last {$s['days']} days ({$s['unique_visitors_in_period']} unique visitors), {$s['scans_total']} in total.")], 'structuredContent' => $s];
        case 'list_qr_codes':
            if (!$user) throw new ApiError('Listing codes needs an API key. Create one at ' . base_url() . '/app/account.php#api', 401, 'unauthorized');
            api_meter($user, 'list', 0, 'mcp');
            $l = api_list($user, (int) ($args['limit'] ?? 20), (int) ($args['offset'] ?? 0));
            $lines = array_map(fn($c) => "- {$c['title']}: {$c['short_url']} → {$c['target_url']} ({$c['scans']} scans" . ($c['active'] ? '' : ', paused') . ')', $l['codes']);
            return ['content' => [['type' => 'text', 'text' => "{$l['total']} codes in total. Newest first:
" . implode("
", $lines)]], 'structuredContent' => $l];
        case 'create_qr_codes_bulk':
            if (!$user) throw new ApiError('Bulk creation needs an API key. Create one at ' . base_url() . '/app/account.php#api', 401, 'unauthorized');
            $codes = api_bulk($user, (array) ($args['items'] ?? []), (string) ($args['campaign'] ?? ''), 'mcp');
            $lines = array_map(fn($c) => "- {$c['title']}: {$c['short_url']} → {$c['target_url']} (PNG {$c['image_png']})", $codes);
            return ['content' => [['type' => 'text', 'text' => 'Created ' . count($codes) . " codes:
" . implode("
", $lines)]], 'structuredContent' => ['codes' => $codes]];
        case 'get_limits':
            $plan = api_plan($user);
            $d = ['plan' => $plan, 'limits' => api_limits($plan)];
            return ['content' => [$text($d, "Plan: $plan")], 'structuredContent' => $d];
    }
    throw new ApiError("Unknown tool: $name", 404, 'unknown_tool');
}

function mcp_handle(array $msg, ?array $user): ?array
{
    $id = $msg['id'] ?? null;
    $method = (string) ($msg['method'] ?? '');
    $params = is_array($msg['params'] ?? null) ? $msg['params'] : [];
    $ok = fn($result) => ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    $err = fn(int $code, string $m) => ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $m]];
    if ($id === null && str_starts_with($method, 'notifications/')) return null; // notifications get no answer

    switch ($method) {
        case 'initialize':
            $want = (string) ($params['protocolVersion'] ?? '');
            return $ok([
                'protocolVersion' => in_array($want, MCP_VERSIONS, true) ? $want : MCP_VERSIONS[0],
                'capabilities' => ['tools' => ['listChanged' => false]],
                'serverInfo' => MCP_SERVER,
                'instructions' => 'Creates free dynamic QR codes that never expire; the target can be changed later. '
                    . 'Use create_qr_code for a link; show the user the image and short URL. Without an API key, ALWAYS give the user the edit_url from the result. '
                    . 'For SVG print files use image_svg. Quotas depend on the plan (get_limits). Made in Vienna, hosted in the EU, no tracking cookies.',
            ]);
        case 'ping':
            return $ok(new stdClass());
        case 'tools/list':
            return $ok(['tools' => mcp_tools()]);
        case 'tools/call':
            $name = (string) ($params['name'] ?? '');
            $args = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];
            try {
                return $ok(mcp_call($name, $args, $user));
            } catch (ApiError $e) {
                if ($e->errorCode === 'unknown_tool') return $err(-32602, $e->getMessage());
                return $ok(['isError' => true, 'content' => [['type' => 'text', 'text' => $e->getMessage()]]]);
            }
        case 'resources/list':
            return $ok(['resources' => []]);
        case 'prompts/list':
            return $ok(['prompts' => []]);
    }
    return $err(-32601, "Method not found: $method");
}

header('Content-Type: application/json; charset=utf-8');
try {
    $user = api_user();
} catch (ApiError $e) {
    http_response_code($e->status);
    echo json_encode(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32001, 'message' => $e->getMessage()]]);
    exit;
}
$in = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($in)) {
    http_response_code(400);
    echo json_encode(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32700, 'message' => 'Parse error']]);
    exit;
}
try {
    if (array_is_list($in)) { // older clients may batch
        $out = array_values(array_filter(array_map(fn($m) => is_array($m) ? mcp_handle($m, $user) : null, $in)));
    } else {
        $out = mcp_handle($in, $user);
    }
} catch (Throwable $e) {
    error_log('qr mcp error: ' . $e->getMessage());
    $out = ['jsonrpc' => '2.0', 'id' => $in['id'] ?? null, 'error' => ['code' => -32603, 'message' => 'Internal error']];
}
if ($out === null || $out === []) { http_response_code(202); exit; }
echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
