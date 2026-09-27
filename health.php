<?php
declare(strict_types=1);

http_response_code(200);
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

echo json_encode([
    'status'    => 'ok',
    'app'       => 'Great Solomon Manpower Services Inc. Core Transaction 4',
    'timestamp' => date('c'),
], JSON_UNESCAPED_SLASHES);
