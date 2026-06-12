<?php
/**
 * Tester standalone de proveedores de IA.
 * Uso:   php test_ai.php          (CLI)
 *   o:   abrir http://TU_HOST/test_ai.php  en el browser
 * Lee las keys de application/config/config.php (no las hardcodea).
 * BORRAR después de usar (no dejarlo accesible en producción).
 */

define('BASEPATH', true);
$config = [];
require __DIR__ . '/application/config/config.php';

$cli = (php_sapi_name() === 'cli');
$nl  = $cli ? "\n" : "<br>\n";
function out($s) { global $nl; echo $s . $nl; }
if (!$cli) echo "<pre style='font:14px monospace'>";

function post_json($url, $headers, $payload) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return [$code, $raw, $err];
}

function verdict($name, $code, $raw, $err, $okText) {
    if ($err)        { out("  $name -> CURL ERROR: $err"); return; }
    $ok = ($code === 200);
    $tag = $ok ? "OK ✅" : "FALLA ❌";
    out("  $name -> HTTP $code  $tag");
    if (!$ok) {
        out("     resp: " . substr(preg_replace('/\s+/', ' ', $raw), 0, 220));
    } else {
        out("     dijo: " . trim($okText));
    }
}

out("=== TEST DE PROVEEDORES DE IA ===");
out("par configurado: A=" . ($config['ai_provider_a'] ?? '?') . "  B=" . ($config['ai_provider_b'] ?? '?') . "  modo=" . ($config['ai_mode'] ?? '?'));
out("");

// --- GEMINI ---
$gk = $config['gemini_api_key'] ?? '';
$gm = $config['gemini_model'] ?? 'gemini-2.5-flash';
if (!$gk) { out("  GEMINI -> sin key"); }
else {
    list($c, $r, $e) = post_json(
        "https://generativelanguage.googleapis.com/v1beta/models/{$gm}:generateContent?key=" . urlencode($gk),
        ["Content-Type: application/json"],
        ["contents" => [["parts" => [["text" => "Responde solo: OK"]]]]]
    );
    $j = json_decode($r, true);
    $txt = $j['candidates'][0]['content']['parts'][0]['text'] ?? '';
    verdict("GEMINI ($gm)", $c, $r, $e, $txt);
}

// --- OPENAI ---
$ok_ = $config['openai_api_key'] ?? '';
if (!$ok_) { out("  OPENAI -> sin key"); }
else {
    list($c, $r, $e) = post_json(
        "https://api.openai.com/v1/chat/completions",
        ["Authorization: Bearer {$ok_}", "Content-Type: application/json"],
        ["model" => "gpt-4o", "messages" => [["role" => "user", "content" => "Responde solo: OK"]], "max_tokens" => 5]
    );
    $j = json_decode($r, true);
    $txt = $j['choices'][0]['message']['content'] ?? '';
    verdict("OPENAI (gpt-4o)", $c, $r, $e, $txt);
}

// --- CLAUDE ---
$ck = $config['claude_api_key'] ?? '';
if (!$ck) { out("  CLAUDE -> sin key"); }
else {
    list($c, $r, $e) = post_json(
        "https://api.anthropic.com/v1/messages",
        ["x-api-key: {$ck}", "anthropic-version: 2023-06-01", "Content-Type: application/json"],
        ["model" => "claude-sonnet-4-5-20250929", "max_tokens" => 5, "messages" => [["role" => "user", "content" => "Responde solo: OK"]]]
    );
    $j = json_decode($r, true);
    $txt = $j['content'][0]['text'] ?? '';
    verdict("CLAUDE (sonnet-4-5)", $c, $r, $e, $txt);
}

out("");
out("Regla: el par (A y B) tiene que dar OK ✅ los dos para que el modo dual valide.");
if (!$cli) echo "</pre>";
