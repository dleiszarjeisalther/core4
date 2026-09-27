<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

function current_user(): ?array { return $_SESSION['user'] ?? null; }

function app_base_path(): string {
    $path=str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME']??'/'));
    if(str_contains($path,'/modules/')) return preg_replace('#/modules/.*$#','',$path) ?: '';
    if(str_contains($path,'/auth/')) return preg_replace('#/auth/.*$#','',$path) ?: '';
    if(str_contains($path,'/includes')) return preg_replace('#/includes.*$#','',$path) ?: '';
    if(str_contains($path,'/services')) return preg_replace('#/services.*$#','',$path) ?: '';
    return ($path==='/' || $path==='.') ? '' : rtrim($path,'/');
}

function require_login(): void {
    if (!current_user()) {
        header('Location: '.rtrim(app_base_path(),'/').'/auth/login.php');
        exit;
    }
    // Enforce the same 5-minute inactivity limit server-side. Login/OTP pages
    // never call require_login(), so they are excluded from this timeout.
    $limit = 5 * 60;
    $last = (int)($_SESSION['last_activity'] ?? time());
    if ((time() - $last) >= $limit) {
        logout_user();
        header('Location: '.rtrim(app_base_path(),'/').'/auth/login.php?reason=inactivity');
        exit;
    }
    $_SESSION['last_activity'] = time();
}


function require_admin(): void {
    require_login();
    $u=current_user();
    if (($u['role'] ?? '') !== 'Administrator') {
        http_response_code(403);
        echo '<!doctype html><html><head><meta charset="utf-8"><title>Access Denied</title></head><body style="font-family:Arial,sans-serif;padding:40px"><h1>Access Denied</h1><p>This area is available to administrators only.</p><p><a href="'.htmlspecialchars(app_base_path().'/dashboard.php',ENT_QUOTES,'UTF-8').'">Return to dashboard</a></p></body></html>';
        exit;
    }
}

function login_user(array $user): void {
    session_regenerate_id(true);
    $_SESSION['last_activity'] = time();
    $_SESSION['user']=[
        'id'=>(int)$user['id'],
        'name'=>$user['name'],
        'email'=>$user['email'],
        'role'=>$user['role']
    ];
}

function logout_user(): void {
    $_SESSION=[];
    if (ini_get('session.use_cookies')) {
        $p=session_get_cookie_params();
        setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);
    }
    session_destroy();
}
