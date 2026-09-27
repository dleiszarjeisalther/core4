<?php
require_once __DIR__.'/../../includes/helpers.php'; require_once __DIR__.'/../../includes/service_client.php'; require_login(); $svc=service('assets');
if($_SERVER['REQUEST_METHOD']==='POST'){try{$message=$svc->handle((string)($_POST['action']??''),$_POST,current_user());flash('success',$message);}catch(Throwable $e){flash('error','Unable to save record: '.$e->getMessage());}redirect('/modules/asset_equipment/index.php');}
$assets=$svc->assets(); $issuances=$svc->issuances();
$u=current_user();
page_header('Asset & Equipment Issuance','assets');show_flash();?>
<div class="gw-breadcrumb"><span>Great Solomon Manpower Services Inc.</span><span>/</span><strong>Asset &amp; Equipment Issuance</strong></div>
<section class="gw-hero"><div><div class="eyebrow">MODULE 4</div><h1>Asset &amp; Equipment Issuance Tracker</h1><p>Track equipment inventory, items available for borrowing, issuance, and returns.</p></div></section>

<section class="gw-quick-actions">
  <a href="#available-items"><span class="material-symbols-outlined">inventory_2</span> Items to Borrow</a>
  <a href="#issue"><span class="material-symbols-outlined">assignment_turned_in</span> Issue Equipment</a>
  <a href="#register-asset"><span class="material-symbols-outlined">add_box</span> Register Asset</a>
  <a href="#issuance-history"><span class="material-symbols-outlined">history</span> Issuance History</a>
</section>

<section class="gw-stats">
  <div class="gw-stat"><span class="gw-stat-label">Total Assets</span><div class="gw-stat-value"><?=count($assets)?></div><div class="gw-stat-meta">Asset registry</div></div>
  <div class="gw-stat"><span class="gw-stat-label">Available to Borrow</span><div class="gw-stat-value"><?=count(array_filter($assets,fn($a)=>($a['status']??'')==='Available' && (int)($a['available_quantity']??0)>0))?></div><div class="gw-stat-meta positive">Ready for issuance</div></div>
  <div class="gw-stat"><span class="gw-stat-label">Issued</span><div class="gw-stat-value"><?=count(array_filter($assets,fn($a)=>$a['status']==='Issued'))?></div><div class="gw-stat-meta">Currently assigned</div></div>
  <div class="gw-stat"><span class="gw-stat-label">Maintenance</span><div class="gw-stat-value"><?=count(array_filter($assets,fn($a)=>$a['status']==='Maintenance'))?></div><div class="gw-stat-meta warning">Needs service</div></div>
</section>

<section class="gw-panel" id="available-items" style="margin-top:20px">
  <div class="gw-panel-head">
    <div>
      <h2>List of Items Available to Borrow</h2>
      <span>Equipment and asset inventory records</span>
    </div>
    <span class="material-symbols-outlined">inventory</span>
  </div>
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <th>Asset Tag</th>
          <th>Item / Description</th>
          <th>Category</th>
          <th>Location</th>
          <th>Total Stock</th>
          <th>Available</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($assets as $a): 
          $avail = (int)($a['available_quantity'] ?? 0);
          $status = $a['status'] ?? 'Available';
          $statusClass = match($status) {
            'Available' => ($avail > 0 ? 'status-pill-success' : 'status-pill-warning'),
            'Issued' => 'status-pill-info',
            'Maintenance' => 'status-pill-warning',
            default => 'status-pill-neutral'
          };
        ?>
        <tr>
          <td><strong><?=e($a['asset_tag'])?></strong></td>
          <td><?=e($a['name'])?><?php if(!empty($a['serial_number'])): ?><br><small style="color:#64748b">SN: <?=e($a['serial_number'])?></small><?php endif; ?></td>
          <td><?=e($a['category'] ?: 'General')?></td>
          <td><?=e($a['location'] ?: 'Head Office')?></td>
          <td><?=e($a['quantity'] ?? 1)?></td>
          <td><strong style="color: <?=$avail>0 ? '#166534' : '#c2410c'?>"><?=$avail?></strong></td>
          <td><span class="status-pill <?=$statusClass?>"><?=e($status)?></span></td>
          <td>
            <?php if($status === 'Available' && $avail > 0): ?>
              <button type="button" class="gw-btn primary" style="padding:4px 10px;font-size:12px;" onclick="selectAssetToBorrow('<?=$a['id']?>')">Issue / Borrow</button>
            <?php else: ?>
              <span style="color:#94a3b8;font-size:12px;">Unavailable</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(!$assets): ?>
        <tr><td colspan="8" class="empty">No equipment or assets registered yet. You can register items below.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="record-form" id="issue" style="margin-top:20px">
  <div class="gw-panel-head"><h2>Issue Equipment</h2><span>Assign an available registered asset to an employee.</span></div>
  <form method="post">
    <input type="hidden" name="action" value="issue_asset">
    <div class="form-grid">
      <div>
        <label>Asset</label>
        <select id="assetSelect" name="asset_id" required>
          <option value="">Select available asset</option>
          <?php foreach($assets as $a): if(($a['status']??'')==='Available' && (int)$a['available_quantity']>0):?>
            <option value="<?=$a['id']?>"><?=e($a['asset_tag'].' — '.$a['name'])?> (<?=e($a['available_quantity'])?> available)</option>
          <?php endif; endforeach;?>
        </select>
      </div>
      <div><label>Employee Name</label><input id="employeeInput" name="employee_name" required placeholder="Employee full name"></div>
      <div><label>Issued Date</label><input type="date" name="issued_date" required value="<?=date('Y-m-d')?>"></div>
      <div><label>Expected Return</label><input type="date" name="expected_return"></div>
      <div class="full"><label>Notes / Purpose</label><textarea name="notes" placeholder="Purpose or condition details..."></textarea></div>
    </div>
    <div class="record-actions"><button class="gw-btn primary">Issue Equipment</button></div>
  </form>
</section>

<section class="record-form" id="register-asset" style="margin-top:20px">
  <div class="gw-panel-head">
    <h2>Register Equipment / Asset</h2>
    <span>Add new equipment or items to the inventory so they can be borrowed.</span>
  </div>
  <form method="post">
    <input type="hidden" name="action" value="add_asset">
    <div class="form-grid">
      <div><label>Asset Tag</label><input name="asset_tag" required placeholder="e.g. AST-0007"></div>
      <div><label>Equipment / Item Name</label><input name="name" required placeholder="e.g. Digital Projector, Hard Hat"></div>
      <div><label>Category</label><input name="category" placeholder="e.g. Computer, Tool, Presentation, Safety"></div>
      <div><label>Serial Number</label><input name="serial_number" placeholder="Optional serial number"></div>
      <div><label>Quantity</label><input type="number" name="quantity" min="1" value="1" required></div>
      <div><label>Location</label><input name="location" placeholder="e.g. Head Office, Warehouse"></div>
    </div>
    <div class="record-actions">
      <button class="gw-btn secondary">Register Item</button>
    </div>
  </form>
</section>

<section class="gw-panel" id="issuance-history" style="margin-top:20px">
  <div class="gw-panel-head"><h2>Issuance History</h2><span><?=count($issuances)?> records</span></div>
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr><th>Asset</th><th>Employee</th><th>Issued</th><th>Expected Return</th><th>Status</th><th>Notes</th><th>Action</th></tr>
      </thead>
      <tbody>
        <?php foreach($issuances as $r):?>
        <tr>
          <td><?=e($r['asset_tag'].' — '.$r['name'])?></td>
          <td><?=e($r['employee_name'])?></td>
          <td><?=e($r['issued_date'])?></td>
          <td><?=e($r['expected_return'])?></td>
          <td><span class="status-pill status-pill-<?=match($r['status']){'Returned'=>'success','Overdue'=>'warning','Not Returned'=>'warning',default=>'info'}?>"><?=e($r['status'])?></span></td>
          <td><?=e($r['notes'])?></td>
          <td>
            <form method="post">
              <input type="hidden" name="action" value="update_issuance_status">
              <input type="hidden" name="issuance_id" value="<?=$r['id']?>">
              <select name="status" onchange="this.form.submit()">
                <option value="Issued" <?=$r['status']==='Issued'?'selected':''?>>Issued</option>
                <option value="Returned" <?=$r['status']==='Returned'?'selected':''?>>Returned</option>
                <option value="Overdue" <?=$r['status']==='Overdue'?'selected':''?>>Overdue</option>
                <option value="Not Returned" <?=$r['status']==='Not Returned'?'selected':''?>>Not Returned</option>
              </select>
            </form>
          </td>
        </tr>
        <?php endforeach;if(!$issuances):?>
        <tr><td colspan="7" class="empty">No issuance records.</td></tr>
        <?php endif;?>
      </tbody>
    </table>
  </div>
</section>

<script>
function selectAssetToBorrow(assetId) {
  const sel = document.getElementById('assetSelect');
  if (sel) {
    sel.value = assetId;
  }
  const issueSection = document.getElementById('issue');
  if (issueSection) {
    issueSection.scrollIntoView({ behavior: 'smooth' });
    const empInput = document.getElementById('employeeInput');
    if (empInput) empInput.focus();
  }
}
</script>

<?php page_footer(); ?>
