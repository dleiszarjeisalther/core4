<?php
require_once __DIR__.'/../../includes/auth.php';
require_once __DIR__.'/../../includes/service_client.php';
header('Content-Type: application/json; charset=utf-8');
if(!current_user()){ http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Authentication required']); exit; }
try {
    $service=$_GET['service']??''; $action=$_GET['action']??'';
    $svc=service($service); $result=null;
    if($_SERVER['REQUEST_METHOD']==='GET'){
        $allowed=['stats','incidents','healthRecords','obligations','audits','assets','issuances','users','logins','dashboard'];
        if(!in_array($action,$allowed,true)||!method_exists($svc,$action)) throw new RuntimeException('Unsupported API operation.');
        $result=$svc->{$action}();
    } else {
        if(!method_exists($svc,'handle')) throw new RuntimeException('This service does not accept mutations.');
        $result=$svc->handle((string)($_POST['action']??''),$_POST,current_user());
    }
    echo json_encode(['ok'=>true,'data'=>$result],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch(Throwable $e){ http_response_code(400); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); }
