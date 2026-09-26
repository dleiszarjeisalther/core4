<?php
final class AssetEquipmentService {
    private const MODULE='Asset & Equipment Issuance';
    public function __construct(private PDO $pdo, private AuditService $audit) {}
    public function assets(): array {
        return $this->pdo->query("SELECT a.*, CASE WHEN a.status IN ('Maintenance','Retired') THEN 0 ELSE GREATEST(a.quantity - COALESCE((SELECT COUNT(*) FROM asset_issuances i WHERE i.asset_id=a.id AND i.status IN ('Issued','Overdue','Not Returned')),0),0) END AS available_quantity FROM assets a ORDER BY a.id DESC")->fetchAll();
    }
    public function issuances(): array { return $this->pdo->query('SELECT i.*,a.asset_tag,a.name FROM asset_issuances i JOIN assets a ON a.id=i.asset_id ORDER BY i.id DESC')->fetchAll(); }
    public function stats(): array { return [
        'assets'=>(int)$this->pdo->query('SELECT COUNT(*) FROM assets')->fetchColumn(),
        'issued'=>(int)$this->pdo->query("SELECT COUNT(*) FROM asset_issuances WHERE status='Issued'")->fetchColumn(),
        'maintenance'=>(int)$this->pdo->query("SELECT COUNT(*) FROM assets WHERE status='Maintenance'")->fetchColumn(),
    ]; }
    public function handle(string $action,array $data,?array $user): string {
        if($action==='add_asset'){
            $quantity=max(1,(int)($data['quantity']??1));
            $tag=trim((string)($data['asset_tag']??'')); $name=trim((string)($data['name']??'')); if($tag===''||$name==='') throw new RuntimeException('Asset tag and name are required.');
            $status=(string)($data['status']??'Available'); if(!in_array($status,['Available','Issued','Maintenance','Retired'],true)) throw new RuntimeException('Invalid asset status.');
            $s=$this->pdo->prepare('INSERT INTO assets(asset_tag,name,category,serial_number,status,quantity,location) VALUES(?,?,?,?,?,?,?)');
            $s->execute([$tag,$name,trim((string)($data['category']??'')),trim((string)($data['serial_number']??'')),$status,$quantity,trim((string)($data['location']??''))]);
            $this->audit->record($user,self::MODULE,'Register Asset',$tag); return 'Asset registered.';
        }
        if($action==='issue_asset'){
            $assetId=(int)($data['asset_id']??0); $employee=trim((string)($data['employee_name']??'')); if($assetId<=0||$employee===''||empty($data['issued_date'])) throw new RuntimeException('Available asset, employee and issued date are required.');
            $this->pdo->beginTransaction(); try {
                $lock=$this->pdo->prepare('SELECT status,quantity,(SELECT COUNT(*) FROM asset_issuances i WHERE i.asset_id=assets.id AND i.status IN (\'Issued\',\'Overdue\',\'Not Returned\')) AS active_issuances FROM assets WHERE id=? FOR UPDATE'); $lock->execute([$assetId]); $asset=$lock->fetch();
                if(!$asset||$asset['status']!=='Available' || ((int)$asset['quantity']-(int)$asset['active_issuances'])<=0) throw new RuntimeException('Selected asset is no longer available.');
                $s=$this->pdo->prepare('INSERT INTO asset_issuances(asset_id,employee_name,issued_date,expected_return,status,notes) VALUES(?,?,?,?,?,?)');
                $s->execute([$assetId,$employee,$data['issued_date'],($data['expected_return']??'')?:null,'Issued',trim((string)($data['notes']??''))]);
                $remaining=(int)$asset['quantity']-(int)$asset['active_issuances']-1; $this->pdo->prepare("UPDATE assets SET status=? WHERE id=?")->execute([$remaining>0?'Available':'Issued',$assetId]); $this->pdo->commit();
            } catch(Throwable $e){ if($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
            $this->audit->record($user,self::MODULE,'Issue Equipment','Asset ID '.$assetId); return 'Equipment issued.';
        }
        if($action==='update_issuance_status'){
            $role=(string)($user['role']??'');
            if(!in_array($role,['Administrator','Staff'],true)) throw new RuntimeException('Only Administrator or Staff users can change borrowed item status.');
            $id=(int)($data['issuance_id']??0); $status=(string)($data['status']??'');
            if($id<=0) throw new RuntimeException('Invalid issuance record.');
            if(!in_array($status,['Issued','Returned','Overdue','Not Returned'],true)) throw new RuntimeException('Invalid issuance status.');
            $this->pdo->beginTransaction();
            try {
                $r=$this->pdo->prepare('SELECT asset_id,status FROM asset_issuances WHERE id=? FOR UPDATE'); $r->execute([$id]); $row=$r->fetch();
                if(!$row) throw new RuntimeException('Issuance record not found.');
                $returnDate=($status==='Returned')?'CURDATE()':'NULL';
                $this->pdo->prepare("UPDATE asset_issuances SET status=?,return_date=".($status==='Returned'?'CURDATE()':'NULL')." WHERE id=?")->execute([$status,$id]);
                $assetId=(int)$row['asset_id'];
                $active=$this->pdo->prepare("SELECT COUNT(*) FROM asset_issuances WHERE asset_id=? AND status IN ('Issued','Overdue','Not Returned')");
                $active->execute([$assetId]); $activeCount=(int)$active->fetchColumn();
                $this->pdo->prepare("UPDATE assets SET status=CASE WHEN status='Retired' THEN 'Retired' WHEN ? > 0 THEN 'Issued' ELSE 'Available' END WHERE id=?")->execute([$activeCount,$assetId]);
                $this->pdo->commit();
            } catch(Throwable $e){ if($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
            $this->audit->record($user,self::MODULE,'Update Issuance Status','Issuance ID '.$id.' -> '.$status);
            return 'Borrowed item status updated to '.$status.'.';
        }
        throw new RuntimeException('Unsupported Asset & Equipment action.');
    }
}
