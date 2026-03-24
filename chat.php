<?php
/**
 * chat.php – Groq API Proxy for Daily Bible SPS
 * 
 * Expects POST request with JSON:
 * {
 *   "message": "user message",
 *   "history": [{"role": "user/assistant", "content": "..."}]  (optional)
 * }
 *
 * Returns JSON: { "reply": "AI response" } or { "error": "..." }
 */

// ─── Configuration ─────────────────────────────────────────
// Read API key from environment variable (most secure)
$apiKey = getenv('GROQ_API_KEY');
if (!$apiKey) {
    // Fallback for development only – never commit real keys!
    $apiKey = ''; // ← DO NOT PUT YOUR KEY HERE IN PRODUCTION
}
if (empty($apiKey)) {
    http_response_code(500);
    die(json_encode(['error' => 'Server configuration: missing GROQ_API_KEY']));
}

define('GROQ_MODEL',   'llama3-70b-8192');
define('MAX_CHARS',    500);

$systemPrompt = 'أنت مساعد روحاني لطيف ودافي على منصة "Daily Bible SPS" التعليمية الروحية.
تكلم بالعربي المصري العامية الدافئة.
ردودك قصيرة ومفيدة وملهمة (٣–٥ جمل كحد أقصى).
استخدم آيات من الكتاب المقدس لما يكون مناسب.
أسلوبك: حنين، مشجع، وقريب من القلب. لا تكن رسمياً أو بارداً.';

// ─── CORS headers (adjust in production) ───────────────────
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ─── Only POST allowed ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// ─── Parse request body ────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$userMessage = trim($body['message'] ?? '');
if ($userMessage === '') {
    http_response_code(400);
    echo json_encode(['error' => 'message is required']);
    exit;
}
if (mb_strlen($userMessage) > MAX_CHARS) {
    http_response_code(400);
    echo json_encode(['error' => 'Message too long']);
    exit;
}

// Build message history (optional)
$history = [];
if (isset($body['history']) && is_array($body['history'])) {
    foreach ($body['history'] as $msg) {
        if (isset($msg['role'], $msg['content'])) {
            $history[] = [
                'role'    => $msg['role'],
                'content' => mb_substr($msg['content'], 0, MAX_CHARS)
            ];
        }
    }
    // Keep last 10 turns for context length safety
    $history = array_slice($history, -10);
}

// Add current user message
$history[] = ['role' => 'user', 'content' => $userMessage];

// Prepare Groq API request
$payload = json_encode([
    'model'       => GROQ_MODEL,
    'messages'    => array_merge(
        [['role' => 'system', 'content' => $systemPrompt]],
        $history
    ),
    'max_tokens'  => 400,
    'temperature' => 0.75
]);

// ─── Call Groq API via cURL ────────────────────────────────
$ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

// ─── Handle errors ─────────────────────────────────────────
if ($curlErr) {
    http_response_code(502);
    echo json_encode(['error' => 'Network error: ' . $curlErr]);
    exit;
}

if ($httpCode !== 200) {
    http_response_code(502);
    echo json_encode(['error' => "Groq API error (HTTP $httpCode)"]);
    exit;
}

$data = json_decode($response, true);
$reply = $data['choices'][0]['message']['content'] ?? null;

if (!$reply) {
    http_response_code(500);
    echo json_encode(['error' => 'Empty response from AI']);
    exit;
}

echo json_encode(['reply' => trim($reply)]);
