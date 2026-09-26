<?php
require_once __DIR__.'/helpers.php';
require_once __DIR__.'/service_client.php';
require_login();
$storage=service('storage');
$action=(string)($_GET['action']??'list');

if($action==='list'){
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>true,'items'=>$storage->items(100)],JSON_UNESCAPED_SLASHES);
    exit;
}
if($action==='download_all'){
    $files=$storage->allFiles();
    if(!$files){ http_response_code(404); exit('No data/files available.'); }
    if(!class_exists('ZipArchive')){ http_response_code(500); exit('ZIP download is not available on this server.'); }
    $tmp=tempnam(sys_get_temp_dir(),'ct4_storage_');
    $zip=new ZipArchive();
    if($zip->open($tmp,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true){ http_response_code(500); exit('Unable to create download package.'); }
    $used=[];
    foreach($files as $file){
        $name=(string)$file['file_name'];
        $base=$name; $i=1;
        while(isset($used[$name])){ $name=pathinfo($base,PATHINFO_FILENAME).'_'.$i.(pathinfo($base,PATHINFO_EXTENSION)?'.'.pathinfo($base,PATHINFO_EXTENSION):''); $i++; }
        $used[$name]=true;
        $zip->addFromString($name,(string)$file['file_data']);
    }
    $zip->close();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="CT4_Data_Files.zip"');
    header('Content-Length: '.filesize($tmp));
    header('X-Content-Type-Options: nosniff');
    readfile($tmp); unlink($tmp); exit;
}
if($action==='view' || $action==='download'){
    $id=(int)($_GET['id']??0);
    $file=$storage->file($id);
    if(!$file){ http_response_code(404); exit('File not found.'); }
    $name=$file['file_name'];
    $safeName=str_replace(["
","
",'"'],'_',$name);
    header('Content-Type: '.($file['file_type']?:'application/octet-stream'));
    header('Content-Length: '.strlen($file['file_data']));
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: '.($action==='download'?'attachment':'inline').'; filename="'.$safeName.'"');
    echo $file['file_data'];
    exit;
}
if($_SERVER['REQUEST_METHOD']==='POST' && $action==='delete'){
    $id=(int)($_POST['id']??0); $u=current_user(); $file=$storage->file($id);
    if(!$file){ http_response_code(404); exit('File not found.'); }
    try{
        db()->prepare('INSERT INTO archive_items(item_type,source_table,source_id,item_name,payload,file_data,file_type,deleted_by) VALUES(?,?,?,?,?,?,?,?)')
          ->execute(['file','data_storage',$id,(string)$file['file_name'],json_encode([
              'file_name'=>$file['file_name'],'source_branch'=>$file['source_branch']??'','file_size'=>$file['file_size']??strlen((string)$file['file_data'])
          ],JSON_UNESCAPED_UNICODE),(string)$file['file_data'],(string)$file['file_type'],(int)$u['id']]);
        $storage->delete($id);
        audit('Data Storage','Delete File','Archived '.(string)$file['file_name']);
        header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok'=>true]); exit;
    }catch(Throwable $e){ http_response_code(500); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); exit; }
}
if($_SERVER['REQUEST_METHOD']==='POST' && $action==='upload'){
    $u=current_user();
    try{
        if(empty($_FILES['data_file']) || $_FILES['data_file']['error']!==UPLOAD_ERR_OK) throw new RuntimeException('Please choose a valid file.');
        if((int)$_FILES['data_file']['size']>10*1024*1024) throw new RuntimeException('File size must not exceed 10 MB.');
        $tmp=$_FILES['data_file']['tmp_name'];
        $data=file_get_contents($tmp);
        if($data===false) throw new RuntimeException('Unable to read the selected file.');
        $mime=function_exists('mime_content_type')?(string)mime_content_type($tmp):(string)($_FILES['data_file']['type']??'application/octet-stream');
        $fileName=(string)$_FILES['data_file']['name'];
        $sourceBranch=trim((string)($_POST['source_branch']??''));
        $storage->save($fileName,$mime,$sourceBranch,(int)$u['id'],$data);

        // Notify active staff accounts that a new data/file transfer is available.
        $staffIds=db()->query("SELECT id FROM users WHERE role='Staff' AND active=1")->fetchAll(PDO::FETCH_COLUMN);
        $title='New Data/File Transfer';
        $message='A new data/file has been transferred to Data Storage'.($sourceBranch!==''?' from '.$sourceBranch:'').': '.$fileName;
        $notice=db()->prepare("INSERT INTO admin_notifications (user_id,type,title,message,sender_name,sender_role) VALUES (?,?,?,?,?,?)");
        foreach($staffIds as $staffId){
            $notice->execute([(int)$staffId,'data_transfer',$title,$message,(string)($u['name']??'User'),(string)($u['role']??'Staff')]);
        }

        audit('Data Storage','Upload File',$fileName);
        flash('success','Data/file stored successfully.');
    }catch(Throwable $e){ flash('error','Unable to store file: '.$e->getMessage()); }
    redirect('/dashboard.php');
}
http_response_code(400);
echo 'Invalid request.';
