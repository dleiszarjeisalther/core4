<?php require_once __DIR__.'/../includes/auth.php'; logout_user(); header('Location: '.rtrim(app_base_path(),'/').'/auth/login.php'); exit;
