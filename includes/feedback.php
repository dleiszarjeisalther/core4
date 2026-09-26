<?php
require_once __DIR__.'/helpers.php';
require_login();
if($_SERVER['REQUEST_METHOD']!=='POST'){ redirect('/dashboard.php'); }
$u=current_user();
$action=(string)($_POST['action']??'send');
$name=trim((string)($u['name']??'')); $role=trim((string)($u['role']??'')); $feedback=trim((string)($_POST['feedback']??''));

try {
    if($action==='reply'){
        if(($u['role']??'')!=='Administrator') throw new RuntimeException('Only an administrator can reply to feedback.');
        $notificationId=(int)($_POST['notification_id']??0);
        if($notificationId<=0 || $feedback==='') throw new RuntimeException('Please provide a reply.');
        $q=db()->prepare("SELECT id,sender_user_id,sender_name,sender_role FROM admin_notifications WHERE id=? AND type='feedback' LIMIT 1");
        $q->execute([$notificationId]); $original=$q->fetch(PDO::FETCH_ASSOC);
        if(!$original) throw new RuntimeException('The selected feedback could not be found.');
        $recipientId=(int)($original['sender_user_id']??0);
        if($recipientId<=0 && $original['sender_name']){
            $find=db()->prepare("SELECT id FROM users WHERE name=? AND role=? AND active=1 ORDER BY id LIMIT 1");
            $find->execute([$original['sender_name'],(string)($original['sender_role']??'Staff')]);
            $recipientId=(int)($find->fetchColumn()?:0);
        }
        if($recipientId<=0) throw new RuntimeException('The staff account for this feedback could not be found.');
        $stmt=db()->prepare("INSERT INTO admin_notifications (user_id,type,title,message,sender_name,sender_role,sender_user_id) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$recipientId,'feedback_reply','Reply to Your Feedback',$feedback,$name,$role,(int)$u['id']]);
        audit('System Administration & Security','Reply to Feedback',($original['sender_name']??'Staff').' — '.$feedback);
        $_SESSION['feedback_sent']=true;
        flash('success','Your reply was sent to the staff member.');
        redirect('/dashboard.php');
    }

    if($name===''||$role===''||$feedback===''){ flash('error','Please complete your name, role and feedback.'); redirect('/dashboard.php'); }
    $stmt=db()->query("SELECT id FROM users WHERE role='Administrator' AND active=1 ORDER BY id LIMIT 1");
    $adminId=$stmt->fetchColumn();
    $stmt=db()->prepare("INSERT INTO admin_notifications (user_id,type,title,message,sender_name,sender_role,sender_user_id) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([
        $adminId !== false ? (int)$adminId : null,
        'feedback',
        'New User Feedback',
        $feedback,
        $name,
        $role,
        (int)($u['id']??0) ?: null
    ]);
    audit('System Administration & Security','Submit Feedback',$name.' ('.$role.')');
    $_SESSION['feedback_sent']=true;
    flash('success','Your feedback was sent to the administrator.');
} catch(Throwable $e){
    error_log('CT4 feedback submission failed: '.$e->getMessage());
    flash('error',$e->getMessage());
}
redirect('/dashboard.php');
