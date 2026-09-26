<?php
final class LegalComplianceService {
    private const MODULE='Legal & Compliance';
    public function __construct(private PDO $pdo, private AuditService $audit) {}
    public function obligations(): array { return $this->pdo->query('SELECT * FROM compliance_obligations ORDER BY reported_at DESC,created_at DESC,id DESC')->fetchAll(); }
    public function complianceReports(string $date='', ?int $limit=null): array {
        $where=''; $params=[]; if($date!=='' && preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)){ $where=' WHERE DATE(COALESCE(reported_at,created_at))=?'; $params[]=$date; }
        $lim=$limit!==null ? ' LIMIT '.max(1,min(200,$limit)) : ''; $q=$this->pdo->prepare("SELECT * FROM compliance_obligations{$where} ORDER BY COALESCE(reported_at,created_at) DESC,id DESC{$lim}"); $q->execute($params); return $q->fetchAll();
    }
    public function audits(): array { return $this->pdo->query('SELECT * FROM compliance_audits ORDER BY audit_date DESC,id DESC')->fetchAll(); }
    public function stats(): array { return [
        'obligations'=>(int)$this->pdo->query('SELECT COUNT(*) FROM compliance_obligations')->fetchColumn(),
        'overdue'=>(int)$this->pdo->query("SELECT COUNT(*) FROM compliance_obligations WHERE status='Overdue' OR due_date < CURDATE()")->fetchColumn(),
        'audits'=>(int)$this->pdo->query('SELECT COUNT(*) FROM compliance_audits')->fetchColumn(),
    ]; }
    private function archiveRecord(string $table, int $id, string $name, ?array $user): void {
        $stmt=$this->pdo->prepare("SELECT * FROM `$table` WHERE id=? LIMIT 1");
        $stmt->execute([$id]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$row) throw new RuntimeException('Record not found.');
        $this->pdo->prepare('INSERT INTO archive_items(item_type,source_table,source_id,item_name,payload,deleted_by) VALUES(?,?,?,?,?,?)')
            ->execute(['record',$table,$id,$name,json_encode($row,JSON_UNESCAPED_UNICODE),$user['id']??null]);
    }

    public function handle(string $action,array $data,?array $user): string {
        if($action==='add_report'){
            $name=trim((string)($data['report_name']??'')); $role=trim((string)($data['report_role']??'')); $contact=trim((string)($data['contact_no']??'')); $note=trim((string)($data['compliance_note']??''));
            if($name===''||$role===''||$contact===''||$note==='') throw new RuntimeException('Name, role, contact no and compliance are required.');
            $title=$name.' — Compliance Report';
            $s=$this->pdo->prepare('INSERT INTO compliance_obligations(title,report_name,report_role,contact_no,compliance_note,reported_at,category,owner,due_date,priority,status) VALUES(?,?,?,?,?,NOW(),?,?,CURDATE(),?,?,?)');
            $s->execute([$title,$name,$role,$contact,$note,'Compliance',$role,'Medium','Compliant']);
            $this->audit->record($user,self::MODULE,'Create Compliance Report',$name); return 'Compliance report saved.';
        }
        if($action==='add_obligation'){
            $title=trim((string)($data['title']??'')); if($title===''||empty($data['due_date'])) throw new RuntimeException('Title and due date are required.');
            $priority=(string)($data['priority']??'Medium'); $status=(string)($data['status']??'Open');
            if(!in_array($priority,['Low','Medium','High','Critical'],true)||!in_array($status,['Open','In Progress','Compliant','Overdue'],true)) throw new RuntimeException('Invalid compliance values.');
            $s=$this->pdo->prepare('INSERT INTO compliance_obligations(title,category,owner,due_date,priority,status) VALUES(?,?,?,?,?,?)');
            $s->execute([$title,trim((string)($data['category']??'')),trim((string)($data['owner']??'')),$data['due_date'],$priority,$status]);
            $this->audit->record($user,self::MODULE,'Create Compliance Obligation',$title); return 'Compliance obligation saved.';
        }
        if($action==='delete_report' || $action==='delete_obligation'){
            $id=(int)($data['id']??0); if($id<=0) throw new RuntimeException('Invalid compliance report.');
            $stmt=$this->pdo->prepare('SELECT title FROM compliance_obligations WHERE id=? LIMIT 1'); $stmt->execute([$id]); $row=$stmt->fetch(PDO::FETCH_ASSOC);
            $this->archiveRecord('compliance_obligations',$id,(string)($row['title']??('Compliance Report #'.$id)),$user);
            $this->pdo->prepare('DELETE FROM compliance_obligations WHERE id=?')->execute([$id]);
            $this->audit->record($user,self::MODULE,'Delete Compliance Report','Archived ID '.$id); return 'Compliance report deleted and moved to Archive.';
        }
        if($action==='add_audit'){
            $title=trim((string)($data['title']??'')); if($title===''||empty($data['audit_date'])) throw new RuntimeException('Audit title and date are required.');
            $status=(string)($data['status']??'Scheduled'); if(!in_array($status,['Scheduled','In Progress','Completed','Closed'],true)) throw new RuntimeException('Invalid audit status.');
            $s=$this->pdo->prepare('INSERT INTO compliance_audits(title,audit_date,auditor,status,findings) VALUES(?,?,?,?,?)');
            $s->execute([$title,$data['audit_date'],trim((string)($data['auditor']??'')),$status,trim((string)($data['findings']??''))]);
            $this->audit->record($user,self::MODULE,'Create Audit',$title); return 'Compliance audit saved.';
        }
        throw new RuntimeException('Unsupported Legal & Compliance action.');
    }
}
