<?php
require_once __DIR__.'/includes/auth.php';
$base=rtrim(app_base_path(),'/');
if(current_user()){
    header('Location: '.$base.'/dashboard.php');
} else {
    header('Location: '.$base.'/auth/login.php');
}
exit;
