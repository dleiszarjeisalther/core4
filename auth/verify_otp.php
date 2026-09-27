<?php
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/mailer.php';

if (current_user()) redirect('/dashboard.php');
$pending=$_SESSION['pending_otp_user']??null;
if (!$pending) redirect('/auth/login.php');

$error='';
$success='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        $action=$_POST['action']??'verify';

        if ($action==='resend') {
            $otp=(string)random_int(100000,999999);
            $hash=password_hash($otp,PASSWORD_DEFAULT);
            $expires=date('Y-m-d H:i:s',time()+(OTP_EXPIRY_MINUTES*60));
            db()->prepare('DELETE FROM otp_requests WHERE user_id=?')->execute([(int)$pending['id']]);
            db()->prepare('INSERT INTO otp_requests(user_id,otp_hash,expires_at,attempts) VALUES(?,?,?,0)')
                ->execute([(int)$pending['id'],$hash,$expires]);
            $_SESSION['temp_otp_code']=$otp;
            try {
                send_otp_email($pending['email'],$pending['name'],$otp);
            } catch(Throwable $e) {}
            $success='A new verification code has been generated and sent.';
        } else {
            $code=preg_replace('/\D/','',$_POST['otp']??'');
            $stmt=db()->prepare('SELECT id,otp_hash,expires_at,attempts FROM otp_requests WHERE user_id=? ORDER BY id DESC LIMIT 1');
            $stmt->execute([(int)$pending['id']]);
            $row=$stmt->fetch();

            if (!$row) {
                $error='Your verification code is no longer available. Please sign in again.';
                unset($_SESSION['temp_otp_code']);
            } elseif (strtotime($row['expires_at']) < time()) {
                $error='Your verification code has expired. Please sign in again.';
                db()->prepare('DELETE FROM otp_requests WHERE id=?')->execute([(int)$row['id']]);
                unset($_SESSION['temp_otp_code']);
            } elseif ((int)$row['attempts'] >= OTP_MAX_ATTEMPTS) {
                $error='Too many incorrect attempts. Please sign in again.';
                unset($_SESSION['temp_otp_code']);
            } elseif (!preg_match('/^\d{6}$/',$code) || !password_verify($code,$row['otp_hash'])) {
                db()->prepare('UPDATE otp_requests SET attempts=attempts+1 WHERE id=?')->execute([(int)$row['id']]);
                $error='Incorrect verification code.';
            } else {
                login_user($pending);
                db()->prepare('DELETE FROM otp_requests WHERE user_id=?')->execute([(int)$pending['id']]);
                unset($_SESSION['pending_otp_user'],$_SESSION['pending_otp_created'],$_SESSION['temp_otp_code']);

                $history=db()->prepare('INSERT INTO login_history(user_id,email,status,ip_address,user_agent) VALUES(?,?,?,?,?)');
                $history->execute([
                    (int)$pending['id'],$pending['email'],'Success',
                    $_SERVER['REMOTE_ADDR']??'Unknown',
                    substr($_SERVER['HTTP_USER_AGENT']??'',0,500)
                ]);
                audit('System Administration & Security','Two-Step Login','Successful password and OTP verification');
                redirect('/dashboard.php');
            }
        }
    } catch(Throwable $e) {
        $error='Unable to process the verification request. Please try again.';
    }
}

if (empty($_SESSION['temp_otp_code'])) {
    $otp=(string)random_int(100000,999999);
    $otpHash=password_hash($otp,PASSWORD_DEFAULT);
    $expires=date('Y-m-d H:i:s',time()+(OTP_EXPIRY_MINUTES*60));
    db()->prepare('DELETE FROM otp_requests WHERE user_id=?')->execute([(int)$pending['id']]);
    db()->prepare('INSERT INTO otp_requests(user_id,otp_hash,expires_at,attempts) VALUES(?,?,?,0)')
        ->execute([(int)$pending['id'],$otpHash,$expires]);
    $_SESSION['temp_otp_code']=$otp;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Verify OTP — Great Solomon Manpower Services Inc.</title>
<link rel="stylesheet" href="../style.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&family=Material+Symbols+Outlined:FILL@0..1&display=swap" rel="stylesheet">
</head>
<body class="auth-body">
<div class="login-page">
  <div class="login-card auth-card otp-card">
    <div class="brand-mark auth-brand">
      <div class="brand-logo-white auth-logo-wrap"><img src="../assets/logo2.svg" alt="Great Solomon Manpower Services Inc. logo" class="brand-logo-image"></div>
      <div class="auth-brand-copy"><strong>Great Solomon Manpower Services Inc.</strong><div class="small-muted">Core Transaction 4</div></div>
    </div>
    <?php $tempOtp = $_SESSION['temp_otp_code'] ?? ''; ?>
    <div class="auth-heading">
      <span class="material-symbols-outlined">shield_lock</span>
      <h1>Verify Your Identity</h1>
      <p>We sent a 6-digit verification code to <strong><?=e($pending['email'])?></strong>.</p>
    </div>

    <?php if(!empty($tempOtp)): ?>
      <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:14px;padding:16px;margin-bottom:18px;text-align:center;box-shadow:0 2px 8px rgba(245,158,11,0.08);">
        <div style="font-size:11px;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:0.06em;display:flex;align-items:center;justify-content:center;gap:6px;margin-bottom:6px;">
          <span class="material-symbols-outlined" style="font-size:18px;color:#d97706;">key</span> Temporary OTP (Development Mode)
        </div>
        <div style="font-size:32px;font-weight:900;letter-spacing:10px;color:#7c2d12;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;margin:6px 0;user-select:all;padding-left:10px;">
          <?=e($tempOtp)?>
        </div>
        <div style="display:flex;align-items:center;justify-content:center;gap:8px;margin-top:8px;">
          <button type="button" onclick="const input=document.getElementById('otp');if(input){input.value='<?=e($tempOtp)?>';input.focus();}" style="background:#fde68a;border:1px solid #f59e0b;padding:5px 14px;border-radius:8px;font-size:12px;font-weight:700;color:#78350f;cursor:pointer;display:inline-flex;align-items:center;gap:4px;">
            <span class="material-symbols-outlined" style="font-size:16px;">content_paste_go</span> Auto-fill OTP
          </button>
        </div>
      </div>
    <?php endif; ?>

    <?php if($error):?><div class="notice error auth-error"><?=e($error)?></div><?php endif;?>
    <?php if($success):?><div class="notice success auth-error"><?=e($success)?></div><?php endif;?>
    <form method="post" class="auth-form">
      <input type="hidden" name="action" value="verify">
      <div class="field otp-field">
        <label for="otp">One-Time Password</label>
        <input id="otp" class="otp-input" type="text" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" oninput="this.value=this.value.replace(/\D/g,'').slice(0,6)" onkeydown="return /[0-9]|Backspace|Delete|ArrowLeft|ArrowRight|Tab|Home|End/.test(event.key) || event.ctrlKey || event.metaKey" autocomplete="one-time-code" placeholder="000000" required autofocus>
      </div>
      <button class="gw-btn primary auth-submit" type="submit"><span class="material-symbols-outlined">verified</span> Verify &amp; Continue</button>
    </form>
    <form method="post" class="resend-form">
      <input type="hidden" name="action" value="resend">
      <button type="submit" class="auth-link">Resend verification code</button>
    </form>
    <a class="auth-link secondary" href="../auth/login.php">Use a different account</a>
    <div class="auth-security-note"><span class="material-symbols-outlined">schedule</span> The code expires in <?=OTP_EXPIRY_MINUTES?> minutes</div>
  </div>
</div>
<script src="../app.js"></script>
<script>
(function(){
  const otp=document.getElementById('otp');
  if(!otp)return;
  otp.addEventListener('input',function(){ this.value=this.value.replace(/\D/g,'').slice(0,6); });
  otp.addEventListener('paste',function(){ setTimeout(()=>{ this.value=this.value.replace(/\D/g,'').slice(0,6); },0); });
})();
</script>
</body>
</html>
