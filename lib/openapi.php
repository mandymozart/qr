<?php
declare(strict_types=1);

/** OpenAPI 3.1 description of the REST API (served at /api/v1/openapi.json). */
function openapi_spec(): array
{
    $code = ['type' => 'object', 'properties' => [
        'code' => ['type' => 'string'], 'title' => ['type' => 'string'], 'target_url' => ['type' => 'string', 'format' => 'uri'],
        'short_url' => ['type' => 'string', 'format' => 'uri', 'description' => 'The URL inside the QR code. Never changes.'],
        'active' => ['type' => 'boolean'], 'created_at' => ['type' => 'string'],
        'image_png' => ['type' => 'string', 'format' => 'uri'], 'image_svg' => ['type' => 'string', 'format' => 'uri'],
        'manage_url' => ['type' => 'string', 'format' => 'uri', 'description' => 'For account codes: the page in the app.'],
        'edit_url' => ['type' => 'string', 'format' => 'uri', 'description' => 'For anonymous codes: secret link for the human to change the code later. Show it to the user.'],
        'style' => ['type' => 'object'],
    ]];
    $style = ['type' => 'object', 'description' => 'Optional look of the code. Every style is test-scanned; if it does not scan, the default is used.', 'properties' => [
        'shape' => ['type' => 'string', 'enum' => QR_SHAPES],
        'corners' => ['type' => 'string', 'enum' => QR_EYES],
        'color' => ['type' => 'string', 'enum' => array_keys(QR_COLORS)],
        'dots_color' => ['type' => 'string', 'pattern' => '^#[0-9a-fA-F]{6}$', 'description' => 'Custom dark color for the dots (overrides color).'],
        'corners_color' => ['type' => 'string', 'pattern' => '^#[0-9a-fA-F]{6}$'],
        'emoji' => ['type' => 'string', 'enum' => QR_EMOJIS, 'description' => 'Twemoji code point for an emoji in the middle, e.g. 1f3a8 (artist palette).'],
    ]];
    $err = ['description' => 'Error', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['error' => ['type' => 'object', 'properties' => ['code' => ['type' => 'string'], 'message' => ['type' => 'string']]]]]]]];
    $json = fn($schema) => ['content' => ['application/json' => ['schema' => $schema]]];
    $codeParam = ['name' => 'code', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']];
    $editKey = ['name' => 'edit_key', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string'], 'description' => 'For anonymous codes: the key from edit_url.'];
    return [
        'openapi' => '3.1.0',
        'info' => [
            'title' => 'qr API',
            'version' => '1.0.0',
            'description' => "Free dynamic QR codes that never expire. Create a code for any link, change its target later, get scan stats.\n\n"
                . "No key needed to create single codes (anonymous: small quotas, returns an edit_url for the human). "
                . "With a free API key (create one in your account) you can list, change, bulk-create and get stats. "
                . 'Quotas per plan: GET /limits. MCP server for AI agents: ' . base_url() . '/mcp',
        ],
        'servers' => [['url' => base_url() . '/api/v1']],
        'components' => ['securitySchemes' => ['bearer' => ['type' => 'http', 'scheme' => 'bearer'], 'apiKey' => ['type' => 'apiKey', 'in' => 'header', 'name' => 'X-API-Key']]],
        'security' => [[], ['bearer' => []], ['apiKey' => []]],
        'paths' => [
            '/codes' => [
                'post' => ['operationId' => 'createCode', 'summary' => 'Create a QR code for a link',
                    'requestBody' => ['required' => true] + $json(['type' => 'object', 'required' => ['target_url'], 'properties' => [
                        'target_url' => ['type' => 'string', 'format' => 'uri'], 'title' => ['type' => 'string'], 'style' => $style]]),
                    'responses' => ['201' => ['description' => 'Created'] + $json($code), '422' => $err, '429' => $err]],
                'get' => ['operationId' => 'listCodes', 'summary' => 'List your codes (API key)',
                    'parameters' => [['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'integer', 'maximum' => 100]], ['name' => 'offset', 'in' => 'query', 'schema' => ['type' => 'integer']]],
                    'responses' => ['200' => ['description' => 'OK'], '401' => $err]],
            ],
            '/codes/bulk' => ['post' => ['operationId' => 'createCodesBulk', 'summary' => 'Create many codes at once (API key)',
                'requestBody' => ['required' => true] + $json(['type' => 'object', 'required' => ['items'], 'properties' => [
                    'campaign' => ['type' => 'string', 'description' => 'Optional: put the codes into a new campaign with this name.'],
                    'items' => ['type' => 'array', 'items' => ['type' => 'object', 'required' => ['target_url'], 'properties' => ['target_url' => ['type' => 'string'], 'title' => ['type' => 'string']]]]]]),
                'responses' => ['201' => ['description' => 'Created'], '401' => $err, '429' => $err]]],
            '/codes/{code}' => [
                'get' => ['operationId' => 'getCode', 'summary' => 'Get one code', 'parameters' => [$codeParam, $editKey], 'responses' => ['200' => ['description' => 'OK'] + $json($code), '404' => $err]],
                'patch' => ['operationId' => 'updateCode', 'summary' => 'Change where a code leads (or its title / active state)', 'parameters' => [$codeParam, $editKey],
                    'requestBody' => ['required' => true] + $json(['type' => 'object', 'properties' => ['target_url' => ['type' => 'string'], 'title' => ['type' => 'string'], 'active' => ['type' => 'boolean']]]),
                    'responses' => ['200' => ['description' => 'Updated'] + $json($code), '404' => $err, '422' => $err]],
                'delete' => ['operationId' => 'deleteCode', 'summary' => 'Delete a code (printed copies stop working)', 'parameters' => [$codeParam, $editKey], 'responses' => ['200' => ['description' => 'Deleted'], '404' => $err]],
            ],
            '/codes/{code}/stats' => ['get' => ['operationId' => 'getCodeStats', 'summary' => 'Scan statistics', 'parameters' => [$codeParam, $editKey, ['name' => 'days', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 30]]], 'responses' => ['200' => ['description' => 'OK'], '404' => $err]]],
            '/limits' => ['get' => ['operationId' => 'getLimits', 'summary' => 'Your plan and quotas', 'responses' => ['200' => ['description' => 'OK']]]],
        ],
    ];
}
