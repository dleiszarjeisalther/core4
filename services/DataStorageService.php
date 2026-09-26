<?php
final class DataStorageService {
    public function __construct(private PDO $pdo) {}

    public function items(int $limit=100): array {
        $limit=max(1,min(200,$limit));
        return $this->pdo->query("SELECT d.id,d.file_name,d.file_type,d.file_size,d.source_branch,d.created_at,
            COALESCE(u.name,'Unknown') AS uploader_name
            FROM data_storage d LEFT JOIN users u ON u.id=d.uploaded_by
            ORDER BY d.created_at DESC LIMIT {$limit}")->fetchAll();
    }

    public function file(int $id): ?array {
        $q=$this->pdo->prepare("SELECT d.*,COALESCE(u.name,'Unknown') AS uploader_name FROM data_storage d LEFT JOIN users u ON u.id=d.uploaded_by WHERE d.id=?");
        $q->execute([$id]);
        return $q->fetch() ?: null;
    }

    public function allFiles(): array {
        return $this->pdo->query("SELECT id,file_name,file_type,file_data FROM data_storage ORDER BY created_at ASC,id ASC")->fetchAll();
    }

    public function delete(int $id): void { $this->pdo->prepare('DELETE FROM data_storage WHERE id=?')->execute([$id]); }

    public function save(string $name,string $type,string $source,int $userId,string $data): void {
        $name=trim($name);
        if($name==='' || strlen($name)>255) throw new RuntimeException('Please provide a valid file name.');
        if($data==='') throw new RuntimeException('The selected file is empty.');
        $stmt=$this->pdo->prepare("INSERT INTO data_storage(file_name,file_type,file_size,source_branch,uploaded_by,file_data) VALUES(?,?,?,?,?,?)");
        $stmt->bindValue(1,$name);
        $stmt->bindValue(2,$type!==''?$type:'application/octet-stream');
        $stmt->bindValue(3,strlen($data),PDO::PARAM_INT);
        $stmt->bindValue(4,$source!==''?$source:null);
        $stmt->bindValue(5,$userId,PDO::PARAM_INT);
        $stmt->bindValue(6,$data,PDO::PARAM_LOB);
        $stmt->execute();
    }
}
