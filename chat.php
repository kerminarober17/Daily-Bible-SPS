<?php
/**
 * chat.php – Groq API Proxy for Daily Bible SPS
 * 
 * Place this file on your server.
 * JS calls: fetch("chat.php", { method: "POST", body: JSON.stringify({ message: "..." }) })
 */

// ─── Config ───────────────────────────────────
define('GROQ_API_KEY', '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'); // ← ضع مفتاحك هنا
define('GROQ_MODEL',   'llama3-70b-8192');
define('MAX_CHARS',    500);

$SYSTEM_PROMPT = 'أنت مساعد روحاني لطيف ودافي على منصة "Daily Bible SPS" التعليمية الروحية.
تكلم بالعربي المصري العامية الدافئة.
ردودك قصيرة ومفيدة وملهمة (٣–٥ جمل كحد أقصى).
استخدم آيات من الكتاب المقدس لما يكون مناسب.
أسلوبك: حنين، مشجع، وقريب من القلب. لا تكن رسمياً أو بارداً.';

// ─── CORS headers (adjust origin in production) ──
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ─── Only accept POST ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// ─── Parse request body ───────────────────────
$body = json_decode(file_get_contents('php://input'), true);

if (empty($body['message']) || !is_string($body['message'])) {
    http_response_code(400);
    echo json_encode(['error' => 'message is required']);
    exit;
}

$userMessage = trim($body['message']);

// Enforce message length
if (mb_strlen($userMessage) > MAX_CHARS) {
    http_response_code(400);
    echo json_encode(['error' => 'Message too long']);
    exit;
}

// Build conversation (optional history support)
$history = [];
if (!empty($body['history']) && is_array($body['history'])) {
    foreach ($body['history'] as $msg) {
        if (isset($msg['role'], $msg['content'])) {
            $history[] = [
                'role'    => $msg['role'],
                'content' => mb_substr($msg['content'], 0, MAX_CHARS)
            ];
        }
    }
    // Keep last 10 turns only (memory safety)
    $history = array_slice($history, -10);
}

$history[] = ['role' => 'user', 'content' => $userMessage];

// ─── Call Groq API via cURL ───────────────────
$payload = json_encode([
    'model'       => GROQ_MODEL,
    'messages'    => array_merge(
        [['role' => 'system', 'content' => $SYSTEM_PROMPT]],
        $history
    ),
    'max_tokens'  => 400,
    'temperature' => 0.75
]);

$ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . GROQ_API_KEY,
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

// ─── Handle errors ────────────────────────────
if ($curlErr) {
    http_response_code(502);
    echo json_encode(['error' => 'Network error', 'reply' => null]);
    exit;
}

if ($httpCode !== 200) {
    http_response_code(502);
    echo json_encode(['error' => "Groq API error: $httpCode", 'reply' => null]);
    exit;
}

// ─── Parse & return reply ─────────────────────
$data  = json_decode($response, true);
$reply = $data['choices'][0]['message']['content'] ?? null;

if (!$reply) {
    http_response_code(500);
    echo json_encode(['error' => 'Empty response from AI', 'reply' => null]);
    exit;
}

echo json_encode(['reply' => trim($reply), 'error' => null]);
