<?php
require_once __DIR__.'/helpers.php';
require_login();
$action=(string)($_GET['action']??'list');
if($action==='list'){
 header('Content-Type: application/json; charset=utf-8');
 $rows=db()->query("SELECT id,item_type,item_name,source_table,source_id,deleted_at FROM archive_items ORDER BY deleted_at DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
 echo json_encode(['ok'=>true,'items'=>$rows],JSON_UNESCAPED_UNICODE); exit;
}
if($_SERVER['REQUEST_METHOD']==='POST' && $action==='recover'){
 $id=(int)($_POST['id']??0);
 $st=db()->prepare('SELECT * FROM archive_items WHERE id=?'); $st->execute([$id]); $a=$st->fetch(PDO::FETCH_ASSOC);
 if(!$a){http_response_code(404); echo json_encode(['ok'=>false,'error'=>'Archived item not found.']); exit;}
 try{
  if($a['item_type']==='file'){
   $p=json_decode($a['payload'],true)?:[];
   $svc=service('storage');
   $svc->save((string)$a['item_name'],(string)$a['file_type'],(string)($p['source_branch']??'Archived Recovery'),(int)(current_user()['id']??0),(string)$a['file_data']);
  } else {
   $allowed=['safety_incidents','compliance_obligations','compliance_audits','health_records','health_safety_files','assets','issuance_records','security_events'];
   if(!in_array($a['source_table'],$allowed,true)) throw new RuntimeException('This record type cannot be recovered automatically.');
   $row=json_decode($a['payload'],true); if(!is_array($row)) throw new RuntimeException('Archived record data is invalid.');
   unset($row['id']);
   $cols=array_keys($row); $marks=implode(',',array_fill(0,count($cols),'?'));
   $sql='INSERT INTO `'.$a['source_table'].'` (`'.implode('`,`',$cols).'`) VALUES ('.$marks.')';
   db()->prepare($sql)->execute(array_values($row));
  }
  db()->prepare('DELETE FROM archive_items WHERE id=?')->execute([$id]);
  audit('Archive','Recover',(string)$a['item_name']);
  header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>true]); exit;
 }catch(Throwable $e){http_response_code(500); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); exit;}
}
http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Invalid request.']);
