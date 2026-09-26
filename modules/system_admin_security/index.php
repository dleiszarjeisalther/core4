<?php
require_once __DIR__.'/../../includes/helpers.php';
require_once __DIR__.'/../../includes/service_client.php';
require_admin();
$svc=service('admin');
if($_SERVER['REQUEST_METHOD']==='POST'){
    try { $message=$svc->handle((string)($_POST['action']??''),$_POST,current_user()); flash('success',$message); }
    catch(Throwable $e){ flash('error','Unable to save: '.$e->getMessage()); }
    redirect('/modules/system_admin_security/index.php');
}
$users=$svc->users();
$loginDate=trim((string)($_GET['login_date']??''));
$showAllLogins=(($_GET['login_all']??'')==='1');
$loginLimit=$showAllLogins?100:5;
$logins=$svc->logins($loginLimit,$loginDate);
page_header('System Administration & Security','security');show_flash();?>
<div class="gw-breadcrumb"><span>Great Solomon Manpower Services Inc.</span><span>/</span><strong>System Administration &amp; Security</strong></div>
<section class="gw-hero"><div><div class="eyebrow">MODULE 3</div><h1>System Administration &amp; Security</h1><p>Manage users, roles, security events and login history.</p></div></section>
<section class="gw-quick-actions">
<a href="#create-user"><span class="material-symbols-outlined">person_add</span> Create User</a>
<a href="#user-accounts"><span class="material-symbols-outlined">manage_accounts</span> User Accounts</a>
<a href="#login-history"><span class="material-symbols-outlined">login</span> Login History</a>
</section>
<section class="gw-stats">
<div class="gw-stat"><span class="gw-stat-label">Active Users</span><div class="gw-stat-value"><?=count(array_filter($users,fn($u)=>(int)$u['active']===1))?></div><div class="gw-stat-meta">Active accounts</div></div>
<div class="gw-stat"><span class="gw-stat-label">Login History</span><div class="gw-stat-value"><?=count($logins)?></div><div class="gw-stat-meta positive">Latest 30 records</div></div>

</section>

<section class="record-form" id="create-user"><div class="gw-panel-head"><h2>Create User</h2><span>Secure password hash</span></div>
<form method="post"><input type="hidden" name="action" value="add_user"><div class="form-grid">
<div><label>Name</label><input name="name" required></div><div><label>Email</label><input type="email" name="email" required></div>
<div><label>Password</label><input type="password" name="password" required minlength="6"></div><div><label>Role</label><select name="role"><option>Administrator</option><option>Staff</option></select></div>
</div><div class="record-actions"><button class="gw-btn primary">Create User</button></div></form></section>

<section class="gw-panel" id="user-accounts"><div class="gw-panel-head"><h2>User Accounts</h2><span><?=count($users)?> accounts</span></div>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach($users as $u):?><tr><td><?=e($u['name'])?></td><td><?=e($u['email'])?></td><td><?=e($u['role'])?></td><td><?=((int)$u['active']?'Active':'Inactive')?></td><td style="display:flex;gap:6px;align-items:center"><button type="button" class="gw-btn secondary" onclick='showEditUserModal(<?=json_encode((int)$u['id'])?>,<?=json_encode($u['name'])?>,<?=json_encode($u['email'])?>,<?=json_encode($u['role'])?>)'>Edit</button><form method="post"><input type="hidden" name="action" value="set_user_status"><input type="hidden" name="id" value="<?=$u['id']?>"><input type="hidden" name="active" value="<?=((int)$u['active']?0:1)?>"><button class="gw-btn <?=((int)$u['active']?'btn-danger':'primary')?>" <?=((int)$u['id']===(int)current_user()['id'] && (int)$u['active']===1)?'disabled title="Current signed-in account cannot be deactivated"':''?>><?=((int)$u['active']?'Deactivate':'Activate')?></button></form></td></tr><?php endforeach;?>
</tbody></table></div></section>

<section class="gw-panel" id="login-history" style="margin-top:20px">
<div class="gw-panel-head"><div><h2>Login History</h2><span><?= $showAllLogins ? 'Showing all matching login records' : 'Showing the latest 5 login records' ?><?= $loginDate ? ' for '.e($loginDate) : '' ?></span></div><span class="material-symbols-outlined">manage_search</span></div>
<form method="get" class="date-filter" style="padding:0 18px 14px;justify-content:flex-end">
  <span class="dashboard-date-filter-label"><span class="material-symbols-outlined">filter_alt</span>Login date</span>
  <input type="date" name="login_date" value="<?=e($loginDate)?>" aria-label="Filter login history by date">
  <button class="gw-btn secondary" type="submit">Filter</button>
  <?php if($loginDate): ?><a class="gw-btn secondary" href="<?=e(url('/modules/system_admin_security/index.php'))?>#login-history">Clear</a><?php endif; ?>
  <?php if($showAllLogins): ?>
    <a class="gw-btn secondary" href="<?=e(url('/modules/system_admin_security/index.php'.($loginDate?'?login_date='.rawurlencode($loginDate):'')))?>#login-history">Show latest 5</a>
  <?php else: ?>
    <a class="gw-btn primary" href="<?=e(url('/modules/system_admin_security/index.php?login_all=1'.($loginDate?'&login_date='.rawurlencode($loginDate):'')))?>#login-history">See all login history</a>
  <?php endif; ?>
</form>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Date &amp; Time</th><th>Email</th><th>User</th><th>Role</th><th>Status</th></tr></thead><tbody>
<?php foreach($logins as $l):?><tr><td><?=e($l['login_at'])?></td><td><?=e($l['email'])?></td><td><?=e($l['user_name']??'Unknown')?></td><td><?=e($l['role']??'—')?></td><td><?=e($l['status']??'—')?></td></tr><?php endforeach;?>
<?php if(!$logins):?><tr><td colspan="5" class="empty">No login history matches the selected filter.</td></tr><?php endif;?>
</tbody></table></div></section>

<?php page_footer(); ?>
