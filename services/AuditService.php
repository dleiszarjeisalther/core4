<?php
final class AuditService {
    public function __construct(private PDO $pdo) {}

    public function record(?array $user, string $module, string $action, string $details=''): void {
        $stmt=$this->pdo->prepare('INSERT INTO audit_logs(user_id,module,action,details) VALUES(?,?,?,?)');
        $stmt->execute([$user['id']??null,$module,$action,$details]);
    }

    public function latestForModule(string $module, int $limit=5): array {
        $limit=max(1,min(50,$limit));
        $stmt=$this->pdo->prepare("SELECT a.*,u.name FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id WHERE a.module=? ORDER BY a.created_at DESC LIMIT {$limit}");
        $stmt->execute([$module]);
        return $stmt->fetchAll();
    }

    public function latestOverall(int $limit=12): array {
        $limit=max(1,min(100,$limit));
        return $this->pdo->query("SELECT a.*,u.name FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.created_at DESC LIMIT {$limit}")->fetchAll();
    }

    public function latestStaffAdmin(?string $date=null, ?int $limit=5): array {
        $sql="SELECT a.module,a.action,a.details,a.created_at,COALESCE(u.name,'System') AS name,COALESCE(u.email,'—') AS email,COALESCE(u.role,'System') AS role
            FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id
            WHERE (u.role IN ('Administrator','Staff') OR u.id IS NULL)";
        $params=[];
        if ($date !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $sql .= " AND DATE(a.created_at)=?";
            $params[]=$date;
        }
        $sql .= " ORDER BY a.created_at DESC";
        if ($limit !== null) {
            $limit=max(1,min(10000,$limit));
            $sql .= " LIMIT {$limit}";
        }
        $stmt=$this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function successfulLogins(int $limit=5): array {
        $limit=max(1,min(100,$limit));
        return $this->pdo->query("SELECT COALESCE(u.name,'Unknown') AS user_name, COALESCE(u.role,'—') AS role, l.login_at FROM login_history l LEFT JOIN users u ON u.id=l.user_id WHERE l.status='Success' ORDER BY l.login_at DESC LIMIT {$limit}")->fetchAll();
    }
}
