<?php
declare(strict_types=1);
require_once __DIR__.'/../../includes/helpers.php';
require_login();
require_once __DIR__.'/../../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'error'=>'POST required.']);
    exit;
}

$user = current_user();
$message = trim((string)($_POST['message'] ?? ''));
$historyRaw = (string)($_POST['history'] ?? '[]');

if ($message === '') {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>'Please enter a message.']);
    exit;
}
if (mb_strlen($message) > 4000) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>'Message is too long. Please keep it under 4,000 characters.']);
    exit;
}

/* Per-session throttle: protects the Gemini key and quota without storing chat text. */
$now = time();
$_SESSION['gemini_ai_window'] = array_values(array_filter(
    $_SESSION['gemini_ai_window'] ?? [],
    fn($t) => is_int($t) && $t > $now - 60
));
if (count($_SESSION['gemini_ai_window']) >= 12) {
    http_response_code(429);
    echo json_encode(['ok'=>false,'error'=>'AI rate limit reached. Please wait a minute and try again.']);
    exit;
}
$_SESSION['gemini_ai_window'][] = $now;

$apiKey = trim((string)(defined('GEMINI_API_KEY') ? GEMINI_API_KEY : (getenv('GEMINI_API_KEY') ?: getenv('GOOGLE_API_KEY') ?: '')));
$model = trim((string)(defined('GEMINI_MODEL') ? GEMINI_MODEL : (getenv('GEMINI_MODEL') ?: 'gemini-3.8-flash')));

if ($apiKey === '') {
    http_response_code(503);
    echo json_encode(['ok'=>false,'error'=>'Gemini is not configured. Set GEMINI_API_KEY on the server environment.']);
    exit;
}

/* Build an authorized, non-secret knowledge context from the live application. */
function ai_db_context(): string {
    $pdo = db();
    $parts = [];
    $parts[] = "SYSTEM: Great Solomon Manpower Services Inc. — Core Transaction 4 (CT4).";
    $parts[] = "Modules: Reports, Analysis & Dashboard; AI System Assistant; Health, Safety & Welfare; Legal & Compliance; Asset & Equipment Issuance. Administrators also have System Administration & Security.";

    $tables = [
        'safety_incidents' => 'Health, Safety & Welfare — Incident Report',
        'health_records' => 'Health, Safety & Welfare — Health Records',
        'compliance_obligations' => 'Legal & Compliance — Regulatory Compliance',
        'compliance_audits' => 'Legal & Compliance — Audit Trails',
        'assets' => 'Asset & Equipment — Asset Inventory',
        'asset_issuances' => 'Asset & Equipment — Issuance & Returns',
        'maintenance_records' => 'Asset & Equipment — Maintenance & Custody',
        'security_events' => 'System Administration & Security — Security Events',
        'audit_logs' => 'System Administration & Security — Audit Logs',
        'login_history' => 'System Administration & Security — Login History',
        'users' => 'System Administration & Security — User Management'
    ];

    foreach ($tables as $table=>$label) {
        try {
            $count = (int)$pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            $parts[] = "{$label}: {$count} records.";
        } catch (Throwable $e) {}
    }

    try {
        $rows = $pdo->query("SELECT module, action, details, created_at FROM audit_logs ORDER BY created_at DESC LIMIT 12")->fetchAll();
        if ($rows) {
            $parts[] = "Recent audit activity (metadata only):";
            foreach ($rows as $r) {
                $parts[] = "- ".($r['created_at']??'')." | ".($r['module']??'')." | ".($r['action']??'')." | ".mb_substr((string)($r['details']??''),0,180);
            }
        }
    } catch (Throwable $e) {}

    try {
        $rows = $pdo->query("SELECT status, COUNT(*) c FROM login_history GROUP BY status")->fetchAll();
        if ($rows) {
            $parts[] = "Login history totals by status: ".implode('; ', array_map(fn($r)=>$r['status'].': '.$r['c'], $rows));
        }
    } catch (Throwable $e) {}

    /* Never expose password hashes, OTP hashes, SMTP credentials, API keys, or session secrets. */
    return implode("\n", $parts);
}

function ai_file_context(): string {
    $root = realpath(__DIR__.'/../..');
    $files = [
        'README.md',
        'MICROSERVICES.md',
    ];
    $out = [];
    foreach ($files as $rel) {
        $path = $root.'/'.$rel;
        if (is_file($path)) {
            $text = file_get_contents($path);
            if ($text !== false) {
                $text = preg_replace('/\s+/', ' ', $text);
                $out[] = "DOCUMENT {$rel}:\n".mb_substr($text,0,5000);
            }
        }
    }
    return implode("\n\n", $out);
}

try {
    $history = json_decode($historyRaw, true);
    if (!is_array($history)) $history = [];
    $history = array_slice($history, -8);
    $safeHistory = [];
    foreach ($history as $item) {
        if (!is_array($item)) continue;
        $role = (($item['role'] ?? '') === 'model') ? 'model' : 'user';
        $text = trim((string)($item['text'] ?? ''));
        if ($text !== '') $safeHistory[] = ['role'=>$role,'text'=>mb_substr($text,0,3000)];
    }

    $systemInstruction = <<<TXT
You are ISMERS/CT4 System Assistant for Great Solomon Manpower Services Inc.
You are an in-app support assistant, not a general unrestricted chatbot.
Answer questions about the CT4 system, its modules, workflows, records, dashboards, security features, and the supplied system documentation.
Use the live application context below as the source of truth for current counts and activity.
Be concise, practical, and clear. If a fact is not present in the context, say you do not have enough information rather than inventing it.
Do not reveal, reconstruct, guess, or output passwords, password hashes, OTPs, SMTP credentials, API keys, session tokens, database credentials, or other secrets.
Do not provide another user's private credentials or authentication data. You may explain security concepts and login history at an aggregate level.
The logged-in user's identity and role are provided separately. Respect the user's role and do not claim permissions that are not established by the application.
If asked to perform an action, explain the appropriate module/button and workflow; do not pretend that you performed a database change unless this endpoint actually performs it.
TXT;

    $context = "LOGGED-IN USER: ".($user['name']??'User')." | ROLE: ".($user['role']??'Staff')." | EMAIL: ".($user['email']??'')."\n\n";
    $context .= ai_db_context()."\n\n".ai_file_context();

    $contents = [];
    foreach ($safeHistory as $h) {
        $contents[] = ['role'=>$h['role'], 'parts'=>[['text'=>$h['text']]]];
    }
    $contents[] = ['role'=>'user', 'parts'=>[['text'=>"SYSTEM CONTEXT:\n".$context."\n\nUSER QUESTION:\n".$message]]];

    /* Gemini can temporarily return 503 when a model is busy. Try the configured
       model first, then a small set of current stable Flash models. */
    $models = array_values(array_unique(array_filter([
        $model,
        'gemini-3.7-flash',
        'gemini-3.6-flash',
        'gemini-3.5-flash'
    ])));

    $payload = json_encode([
        'systemInstruction'=>['parts'=>[['text'=>$systemInstruction]]],
        'contents'=>$contents,
        'generationConfig'=>[
            'maxOutputTokens'=>900
        ]
    ], JSON_UNESCAPED_SLASHES);

    $answer = '';
    $usedModel = $model;
    $lastStatus = 503;
    $lastDetail = 'Gemini is temporarily unavailable.';
    $authFailure = false;

    foreach ($models as $candidateModel) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($candidateModel).':generateContent';
        for ($attempt=0; $attempt<2; $attempt++) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST=>true,
                CURLOPT_RETURNTRANSFER=>true,
                CURLOPT_HTTPHEADER=>[
                    'Content-Type: application/json',
                    'x-goog-api-key: '.$apiKey
                ],
                CURLOPT_POSTFIELDS=>$payload,
                CURLOPT_CONNECTTIMEOUT=>10,
                CURLOPT_TIMEOUT=>45
            ]);
            $response = curl_exec($ch);
            $curlError = curl_error($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response === false || $curlError !== '') {
                $lastStatus = 503;
                $lastDetail = 'Unable to connect to the Gemini service.';
                if ($attempt === 0) { usleep(750000); continue; }
                break;
            }

            $json = json_decode($response, true);
            $lastStatus = $status;
            $lastDetail = (string)($json['error']['message'] ?? 'Gemini request failed.');

            if ($status >= 200 && $status < 300) {
                $answer = trim((string)($json['candidates'][0]['content']['parts'][0]['text'] ?? ''));
                if ($answer !== '') {
                    $usedModel = $candidateModel;
                    break 2;
                }
                $lastDetail = 'Gemini returned an empty response.';
                break;
            }

            if ($status === 401 || $status === 403) {
                $authFailure = true;
                break 2;
            }

            /* 503 = temporary capacity issue; 429 = quota/rate limit; 404 =
               model unavailable. Retry/fallback instead of exposing a generic 500. */
            if ($status === 503 || $status === 429) {
                if ($attempt === 0) {
                    usleep(1200000);
                    continue;
                }
                break;
            }
            if ($status === 404) break;
            break;
        }
    }

    if ($answer === '') {
        error_log('Gemini API failure '.$lastStatus.': '.$lastDetail);
        if ($authFailure) {
            throw new RuntimeException('Gemini authentication was rejected. Check the server GEMINI_API_KEY and Google AI Studio project/API access.');
        }
        if ($lastStatus === 404) {
            throw new RuntimeException('No configured Gemini model is available for this API key.');
        }
        if ($lastStatus === 429) {
            http_response_code(429);
            echo json_encode(['ok'=>false,'error'=>'Gemini is temporarily rate-limited. Please wait a moment and try again.']);
            exit;
        }
        http_response_code(503);
        echo json_encode(['ok'=>false,'error'=>'Gemini is temporarily unavailable. The assistant automatically tried the available CT4 Gemini models. Please try again in a few seconds.']);
        exit;
    }

    audit('AI Assistant','Gemini Chat','Asked about system information');
    echo json_encode(['ok'=>true,'answer'=>$answer,'model'=>$usedModel]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
