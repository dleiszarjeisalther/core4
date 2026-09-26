<?php
final class HealthSafetyService {
    private const MODULE='Health, Safety & Welfare';
    public function __construct(private PDO $pdo, private AuditService $audit) {}

    public function pdoForReporting(): PDO { return $this->pdo; }

    public function incidents(?string $date=null): array {
        if($date && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$date)){ $s=$this->pdo->prepare('SELECT * FROM safety_incidents WHERE incident_date=? ORDER BY incident_date DESC,id DESC'); $s->execute([$date]); return $s->fetchAll(); }
        return $this->pdo->query('SELECT * FROM safety_incidents ORDER BY incident_date DESC,id DESC')->fetchAll();
    }
    public function healthRecords(?string $date=null): array {
        if($date && preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$date)){ $s=$this->pdo->prepare('SELECT * FROM health_records WHERE checkup_date=? ORDER BY checkup_date DESC,id DESC'); $s->execute([$date]); return $s->fetchAll(); }
        return $this->pdo->query('SELECT * FROM health_records ORDER BY checkup_date DESC,id DESC')->fetchAll();
    }
    public function stats(?string $date=null): array {
        if($date && preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)){
 $q=$this->pdo->prepare('SELECT COUNT(*) FROM safety_incidents WHERE incident_date=?');$q->execute([$date]);$i=(int)$q->fetchColumn();
 $q=$this->pdo->prepare("SELECT COUNT(*) FROM safety_incidents WHERE incident_date=? AND status <> 'Closed'");$q->execute([$date]);$o=(int)$q->fetchColumn();
 $q=$this->pdo->prepare('SELECT COUNT(*) FROM health_records WHERE checkup_date=?');$q->execute([$date]);$h=(int)$q->fetchColumn();
}else{$i=(int)$this->pdo->query('SELECT COUNT(*) FROM safety_incidents')->fetchColumn();$o=(int)$this->pdo->query("SELECT COUNT(*) FROM safety_incidents WHERE status <> 'Closed'")->fetchColumn();$h=(int)$this->pdo->query('SELECT COUNT(*) FROM health_records')->fetchColumn();}
return ['incidents'=>$i,'open_incidents'=>$o,'health_records'=>$h];
    }
    public function fileRequests(string $date='', ?int $limit=null): array {
        $where=''; $params=[];
        if($date!=='' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)){ $where=' WHERE request_date=?'; $params[]=$date; }
        $lim=$limit!==null ? ' LIMIT '.max(1,min(200,$limit)) : '';
        $q=$this->pdo->prepare("SELECT * FROM health_safety_files{$where} ORDER BY request_date DESC,id DESC{$lim}"); $q->execute($params); return $q->fetchAll();
    }
    public function storageFile(int $id): ?array { $q=$this->pdo->prepare('SELECT id,file_name,file_type FROM data_storage WHERE id=?'); $q->execute([$id]); return $q->fetch() ?: null; }
    private function archiveRecord(string $table, int $id, string $name, ?array $user): void {
        $stmt=$this->pdo->prepare("SELECT * FROM `$table` WHERE id=? LIMIT 1");
        $stmt->execute([$id]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$row) throw new RuntimeException('Record not found.');
        $this->pdo->prepare('INSERT INTO archive_items(item_type,source_table,source_id,item_name,payload,deleted_by) VALUES(?,?,?,?,?,?)')
            ->execute(['record',$table,$id,$name,json_encode($row,JSON_UNESCAPED_UNICODE),$user['id']??null]);
    }

    public function handle(string $action, array $data, ?array $user): string {
        if($action==='add_incident'){
            $title=trim((string)($data['title']??'')); $employee=trim((string)($data['employee_name']??''));
            if($title===''||$employee===''||empty($data['incident_date'])) throw new RuntimeException('Title, employee and incident date are required.');
            $severity=(string)($data['severity']??'Medium'); $status=(string)($data['status']??'Open');
            if(!in_array($severity,['Low','Medium','High','Critical'],true)) throw new RuntimeException('Invalid severity.');
            if(!in_array($status,['Open','Under Investigation','Closed'],true)) throw new RuntimeException('Invalid status.');
            $s=$this->pdo->prepare('INSERT INTO safety_incidents(title,employee_name,incident_date,severity,status,description) VALUES(?,?,?,?,?,?)');
            $s->execute([$title,$employee,$data['incident_date'],$severity,$status,trim((string)($data['description']??''))]);
            $this->audit->record($user,self::MODULE,'Create Incident',$title); return 'Safety incident saved.';
        }
        if($action==='delete_incident'){
            $id=(int)($data['id']??0); if($id<=0) throw new RuntimeException('Invalid incident.');
            $this->archiveRecord('safety_incidents',$id,'Safety Incident #'.$id,$user);
            $this->pdo->prepare('DELETE FROM safety_incidents WHERE id=?')->execute([$id]);
            $this->audit->record($user,self::MODULE,'Delete Incident','Archived ID '.$id); return 'Incident deleted and moved to Archive.';
        }
        if($action==='request_file' || $action==='release_file'){
 $employee=trim((string)($data['employee_name']??'')); $date=(string)($data['file_date']??date('Y-m-d')); $notes=trim((string)($data['file_notes']??''));
 if($employee===''||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)) throw new RuntimeException('Requester and valid date are required.');
 if($action==='request_file'){
   $file=trim((string)($data['file_name']??'')); $type=trim((string)($data['file_type']??''));
   if($file==='') throw new RuntimeException('Requested file is required.');
   $requesterId=(int)($user['id']??0) ?: null;
   $q=$this->pdo->prepare('INSERT INTO health_safety_files(employee_name,requester_user_id,file_name,file_type,action_type,request_date,notes) VALUES(?,?,?,?,?,?,?)');
   $q->execute([$employee,$requesterId,$file,$type,'Requested',$date,$notes]);
   $this->audit->record($user,self::MODULE,'Pending File Request',$file.' — '.$employee);
   return 'File request received and added to Pending Request Files.';
 }
 $storageIds=$data['storage_file_ids']??[];
 if(!is_array($storageIds)) $storageIds=[$storageIds];
 $storageIds=array_values(array_unique(array_filter(array_map('intval',$storageIds),fn($id)=>$id>0)));
 if(!$storageIds) throw new RuntimeException('Choose at least one data/file from Data Storage first.');
 $match=$this->pdo->prepare("SELECT id,requester_user_id FROM health_safety_files WHERE employee_name=? AND action_type='Requested' ORDER BY id ASC");
 $match->execute([$employee]); $pendingRows=$match->fetchAll();
 $requesterId=$pendingRows[0]['requester_user_id']??null;
 $releasedNames=[];
 $update=$this->pdo->prepare("UPDATE health_safety_files SET action_type='Released',request_date=?,file_name=?,file_type=?,storage_file_id=?,released_at=NOW(),notes=? WHERE id=?");
 $insert=$this->pdo->prepare("INSERT INTO health_safety_files(employee_name,requester_user_id,file_name,file_type,storage_file_id,action_type,request_date,notes,released_at) VALUES(?,?,?,?,?,?,?,?,NOW())");
 $notice=$this->pdo->prepare("INSERT INTO admin_notifications(user_id,type,title,message,sender_name,sender_role) VALUES(?,?,?,?,?,?)");
 foreach($storageIds as $index=>$storageId){
   $stored=$this->storageFile($storageId);
   if(!$stored) throw new RuntimeException('One of the selected data/files is no longer available in Data Storage.');
   $releasedNames[]=$stored['file_name'];
   $pending=$pendingRows[$index]??null;
   $rowRequesterId=$pending['requester_user_id']??$requesterId;
   if($pending){
     $update->execute([$date,$stored['file_name'],$stored['file_type'],$storageId,$notes,(int)$pending['id']]);
   } else {
     $insert->execute([$employee,$rowRequesterId,$stored['file_name'],$stored['file_type'],$storageId,'Released',$date,$notes]);
   }
   if($rowRequesterId){
     $notice->execute([$rowRequesterId,'file_release','File Released','Your requested file has been released: '.$stored['file_name'],(string)($user['name']??'Staff'),(string)($user['role']??'Staff')]);
   }
 }
 $this->audit->record($user,self::MODULE,'Release File',implode(', ',$releasedNames).' — '.$employee);
 return 'successfully sent !';
        }
        if($action==='add_health'){
            $employee=trim((string)($data['employee_name']??'')); if($employee===''||empty($data['checkup_date'])) throw new RuntimeException('Employee and checkup date are required.');
            $fitness=(string)($data['fitness_status']??'Pending'); if(!in_array($fitness,['Fit','Fit with Restrictions','Unfit','Pending'],true)) throw new RuntimeException('Invalid fitness status.');
            $s=$this->pdo->prepare('INSERT INTO health_records(employee_name,checkup_date,record_type,fitness_status,notes) VALUES(?,?,?,?,?)');
            $s->execute([$employee,$data['checkup_date'],trim((string)($data['record_type']??'')),$fitness,trim((string)($data['notes']??''))]);
            $this->audit->record($user,self::MODULE,'Create Health Record',$employee); return 'Health record saved.';
        }
        throw new RuntimeException('Unsupported Health & Safety action.');
    }
}
