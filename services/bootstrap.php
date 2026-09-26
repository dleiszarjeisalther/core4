<?php
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/AuditService.php';
require_once __DIR__.'/HealthSafetyService.php';
require_once __DIR__.'/LegalComplianceService.php';
require_once __DIR__.'/AssetEquipmentService.php';
require_once __DIR__.'/AdminSecurityService.php';
require_once __DIR__.'/ReportsService.php';
require_once __DIR__.'/DataStorageService.php';

function service_container(): array {
    static $services=null; if($services!==null) return $services;
    $pdo=db(); $audit=new AuditService($pdo);
    $health=new HealthSafetyService($pdo,$audit); $legal=new LegalComplianceService($pdo,$audit);
    $assets=new AssetEquipmentService($pdo,$audit); $admin=new AdminSecurityService($pdo,$audit);
    $reports=new ReportsService($health,$legal,$admin,$assets);
    $storage=new DataStorageService($pdo);
    return $services=['audit'=>$audit,'health'=>$health,'legal'=>$legal,'assets'=>$assets,'admin'=>$admin,'reports'=>$reports,'storage'=>$storage];
}
