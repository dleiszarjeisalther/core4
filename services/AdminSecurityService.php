<?php
final class AdminSecurityService {
    private const MODULE='System Administration & Security';
    public function __construct(private PDO $pdo, private AuditService $audit) {}
    public function users(): array { return $this->pdo->query('SELECT id,name,email,role,active,created_at FROM users ORDER BY id')->fetchAll(); }
    public function events(int $limit=20): array { return $this->audit->latestOverall($limit); }
    public function logins(int $limit=5, string $date=''): array {
        $limit=max(1,min(100,$limit));
        if ($date !== '' && !preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$date)) {
            throw new InvalidArgumentException('Invalid login history date.');
        }
        if ($date !== '') {
            $q=$this->pdo->prepare("SELECT l.*,u.name AS user_name,u.role FROM login_history l LEFT JOIN users u ON u.id=l.user_id WHERE DATE(l.login_at)=? ORDER BY l.login_at DESC LIMIT {$limit}");
            $q->execute([$date]);
            return $q->fetchAll();
        }
        return $this->pdo->query("SELECT l.*,u.name AS user_name,u.role FROM login_history l LEFT JOIN users u ON u.id=l.user_id ORDER BY l.login_at DESC LIMIT {$limit}")->fetchAll();
    }
    public function stats(): array { return [
        'users'=>(int)$this->pdo->query('SELECT COUNT(*) FROM users WHERE active=1')->fetchColumn(),
        'logins'=>(int)$this->pdo->query("SELECT COUNT(*) FROM login_history WHERE status='Success'")->fetchColumn(),
    ]; }
    public function handle(string $action,array $data,?array $user): string {
        if($action==='add_user'){
            $name=trim((string)($data['name']??'')); $email=trim((string)($data['email']??'')); $password=(string)($data['password']??''); $role=(string)($data['role']??'Staff');
            if(!in_array($role,['Administrator','Staff'],true)) throw new RuntimeException('Invalid role selected.');
            if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid name and email address.');
            $dup=$this->pdo->prepare('SELECT id,name,email FROM users WHERE name=? OR email=? LIMIT 1'); $dup->execute([$name,$email]);
            if($existing=$dup->fetch()){
                if(strcasecmp((string)$existing['email'],$email)===0) throw new RuntimeException('This Gmail is already registered in the system. Use a different Gmail.');
                throw new RuntimeException('This name is already registered in the system. Use a different name.');
            }
            if(strlen($password)<6) throw new RuntimeException('Password must be at least 6 characters.');
            $s=$this->pdo->prepare('INSERT INTO users(name,email,password_hash,role,active) VALUES(?,?,?,?,1)'); $s->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT),$role]);
            $this->audit->record($user,self::MODULE,'Create User',$email.' ('.$role.')'); return 'User account created.';
        }
        if($action==='edit_user'){
            $targetId=(int)($data['id']??0); $name=trim((string)($data['name']??'')); $email=trim((string)($data['email']??'')); $password=(string)($data['password']??''); $role=(string)($data['role']??'Staff');
            if($targetId<=0 || $name==='' || !filter_var($email,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid name and email address.');
            if(!in_array($role,['Administrator','Staff'],true)) throw new RuntimeException('Invalid role selected.');
            if($password!=='' && strlen($password)<6) throw new RuntimeException('New password must be at least 6 characters.');
            $check=$this->pdo->prepare('SELECT id FROM users WHERE email=? AND id<>?'); $check->execute([$email,$targetId]); if($check->fetch()) throw new RuntimeException('That email is already in use.');
            if($password!==''){
                $s=$this->pdo->prepare('UPDATE users SET name=?,email=?,role=?,password_hash=? WHERE id=?'); $s->execute([$name,$email,$role,password_hash($password,PASSWORD_DEFAULT),$targetId]);
            } else {
                $s=$this->pdo->prepare('UPDATE users SET name=?,email=?,role=? WHERE id=?'); $s->execute([$name,$email,$role,$targetId]);
            }
            $this->audit->record($user,self::MODULE,'Edit User',$email);
            return 'User account updated.';
        }
        if($action==='set_user_status'){
            $targetId=(int)($data['id']??0); $active=((int)($data['active']??0)===1)?1:0; if($targetId<=0) throw new RuntimeException('Invalid user account.');
            if($targetId===(int)($user['id']??0)&&$active===0) throw new RuntimeException('You cannot deactivate the account currently signed in.');
            $this->pdo->prepare('UPDATE users SET active=? WHERE id=?')->execute([$active,$targetId]);
            $this->audit->record($user,self::MODULE,$active?'Activate User':'Deactivate User','ID '.$targetId); return $active?'User activated.':'User deactivated.';
        }
        throw new RuntimeException('Unsupported Administration & Security action.');
    }
}
