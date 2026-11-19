<?php
declare(strict_types=1);

/*
 * REST API v1 (JSON). Rewritten from /api/v1/<path> by .htaccess. Spec: /api/v1/openapi.json
 *
 *   POST   /api/v1/codes                 create one code (no key needed: anonymous, returns edit_url)
 *   POST   /api/v1/codes/bulk            create many (API key)
 *   GET    /api/v1/codes                 list your codes (API key)
 *   GET    /api/v1/codes/{code}          one code (API key, or ?edit_key= for anonymous codes)
 *   PATCH  /api/v1/codes/{code}          change target_url / title / active
 *   DELETE /api/v1/codes/{code}          delete
 *   GET    /api/v1/codes/{code}/stats    scan stats (?days=30)
 *   GET    /api/v1/limits                your plan and quotas
 */
require_once __DIR__ . '/lib/app.php';
require_once __DIR__ . '/lib/api.php';
require_once __DIR__ . '/lib/openapi.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, X-API-Key, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('X-Robots-Tag: noindex');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

function respond(mixed $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

$path = trim((string) ($_GET['path'] ?? ''), '/');
$method = $_SERVER['REQUEST_METHOD'];
$body = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($body)) $body = $_POST;

try {
    if ($path === 'openapi.json') respond(openapi_spec());

    $user = api_user();

    if ($path === 'limits' && $method === 'GET') {
        $plan = api_plan($user);
        respond(['plan' => $plan, 'limits' => api_limits($plan)]);
    }
    if ($path === 'codes' && $method === 'POST') respond(api_create($user, $body, 'rest'), 201);
    if ($path === 'codes/bulk' && $method === 'POST') {
        if (!$user) throw new ApiError('Bulk creation needs an API key.', 401, 'unauthorized');
        respond(['codes' => api_bulk($user, (array) ($body['items'] ?? []), (string) ($body['campaign'] ?? ''), 'rest')], 201);
    }
    if ($path === 'codes' && $method === 'GET') {
        if (!$user) throw new ApiError('Listing codes needs an API key.', 401, 'unauthorized');
        api_meter($user, 'list', 0, 'rest');
        respond(api_list($user, (int) ($_GET['limit'] ?? 50), (int) ($_GET['offset'] ?? 0)));
    }
    if (preg_match('~^codes/([A-Za-z0-9_-]{2,40})(/stats)?$~', $path, $m)) {
        $link = api_owned($user, $m[1], api_edit_key((string) ($_GET['edit_key'] ?? $body['edit_key'] ?? '')));
        if (!empty($m[2]) && $method === 'GET') {
            api_meter($user, 'stats', 0, 'rest');
            respond(api_stats($link, (int) ($_GET['days'] ?? 30)));
        }
        if ($method === 'GET') { api_meter($user, 'get', 0, 'rest'); respond(api_code($link)); }
        if ($method === 'PATCH') { api_meter($user, 'update', 0, 'rest'); respond(api_update($link, $body)); }
        if ($method === 'DELETE') { api_meter($user, 'delete', 0, 'rest'); api_delete($link); respond(['deleted' => $m[1]]); }
    }
    throw new ApiError('Unknown endpoint. See ' . base_url() . '/api/v1/openapi.json', 404, 'not_found');
} catch (ApiError $e) {
    respond(['error' => ['code' => $e->errorCode, 'message' => $e->getMessage()]], $e->status);
} catch (Throwable $e) {
    error_log('qr api error: ' . $e->getMessage());
    respond(['error' => ['code' => 'server_error', 'message' => 'Something went wrong on our side.']], 500);
}
