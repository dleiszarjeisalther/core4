<?php
require_once __DIR__.'/includes/helpers.php';
require_once __DIR__.'/includes/service_client.php';
require_login();
$reportDate=(string)($_GET['date']??'');
$data=service('reports')->dashboard($reportDate);
$counts=$data['counts'];
$healthChart=$data['health_chart'];
$assetIssuanceChart=$data['asset_issuance_chart'];
$u=current_user();
$loginDate=(string)($_GET['login_date']??'');
$showAllLogins=isset($_GET['login_all']) && $_GET['login_all']==='1';
$loginHistory = (($u['role'] ?? '') === 'Administrator') ? service('reports')->loginHistory($showAllLogins?null:5,$loginDate) : [];
require_once __DIR__.'/includes/config.php';
page_header('Reports, Analysis & Dashboard','dashboard'); show_flash(); ?>
<div class="gw-breadcrumb"><span class="material-symbols-outlined">home</span><span>Great Solomon Manpower Services Inc.</span><span>/</span><strong>Reports, Analysis &amp; Dashboard</strong></div>

<section class="gw-hero dashboard-hero">
  <div>
    <div class="eyebrow">CORE TRANSACTION 4</div>
    <div class="dashboard-title-brand">
      <div class="brand-logo-white dashboard-logo-wrap"><img src="assets/logo2.svg" alt="Great Solomon Manpower Services Inc. logo" class="brand-logo-image"></div>
      <h1>Reports, Analysis &amp; Dashboard</h1>
    </div>
    <p>Central reports, analysis and dashboard for Core Transaction 4<?= $reportDate ? " — showing records for ".e($reportDate) : " — showing all report dates" ?>.</p>
  </div>
</section>

<section class="gw-quick-actions">
<a href="modules/health_safety/index.php">Health &amp; Safety</a>
<a href="modules/legal_compliance/index.php">Legal &amp; Compliance</a>
<?php if (($u['role'] ?? '') === 'Administrator'): ?><a href="modules/system_admin_security/index.php">Security</a><?php endif; ?>
<a href="modules/asset_equipment/index.php">Assets</a>
<form method="get" class="date-filter dashboard-date-filter" aria-label="Report date filter">
  <input type="date" name="date" value="<?=e($reportDate)?>" aria-label="Filter reports by date">
  <button class="gw-btn secondary" type="submit"><span class="material-symbols-outlined">filter_alt</span> Filter</button>
  <?php if($reportDate): ?><a class="gw-btn secondary" href="<?=e(url('/dashboard.php'))?>"><span class="material-symbols-outlined">close</span> Clear</a><?php endif; ?>
</form>
</section>

<section class="gw-stats">
<div class="gw-stat"><div class="gw-stat-top"><span class="gw-stat-label">Safety Incidents</span><div class="gw-stat-icon"><span class="material-symbols-outlined">health_and_safety</span></div></div><div class="gw-stat-value"><?=e($counts['incidents'])?></div><div class="gw-stat-meta warning"><?=e($counts['open_incidents'])?> open / under investigation</div></div>
<div class="gw-stat"><div class="gw-stat-top"><span class="gw-stat-label">Compliance</span><div class="gw-stat-icon"><span class="material-symbols-outlined">gavel</span></div></div><div class="gw-stat-value"><?=e($counts['obligations'])?></div><div class="gw-stat-meta warning"><?=e($counts['overdue'])?> overdue / attention</div></div>
<div class="gw-stat"><div class="gw-stat-top"><span class="gw-stat-label">Active Users</span><div class="gw-stat-icon"><span class="material-symbols-outlined">admin_panel_settings</span></div></div><div class="gw-stat-value"><?=e($counts['users'])?></div><div class="gw-stat-meta positive"><?=e($counts['logins'])?> successful logins recorded</div></div>
<div class="gw-stat"><div class="gw-stat-top"><span class="gw-stat-label">Assets</span><div class="gw-stat-icon"><span class="material-symbols-outlined">inventory_2</span></div></div><div class="gw-stat-value"><?=e($counts['assets'])?></div><div class="gw-stat-meta positive"><?=e($counts['issued'])?> currently issued</div></div>
</section>

<section class="gw-chart-grid" aria-label="Reports and analytics charts">
  <article class="gw-panel gw-dashboard-chart-panel">
    <div class="gw-panel-head"><h2>Health, Safety &amp; Governance</h2><span>Live module tracking</span></div>
    <div class="gw-chart-wrap gw-chart-wrap-large"><canvas id="healthGovernanceBarChart"></canvas></div>
  </article>
  <article class="gw-panel gw-dashboard-chart-panel">
    <div class="gw-panel-head"><h2>Assets, Equipment &amp; Issuance</h2><span>Returned · Not Returned · Overdue · Issued — all dates</span></div>
    <div class="gw-chart-wrap gw-chart-wrap-large"><canvas id="assetIssuancePieChart"></canvas></div>
  </article>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- Pie chart configuration follows the Chart.js pie-chart example: https://www.chartjs.org/docs/latest/samples/other-charts/pie.html -->
<script>
(function () {
  const health = <?=json_encode($healthChart, JSON_UNESCAPED_SLASHES)?>;
  const issuance = <?=json_encode($assetIssuanceChart, JSON_UNESCAPED_SLASHES)?>;
  const barCanvas = document.getElementById('healthGovernanceBarChart');
  const pieCanvas = document.getElementById('assetIssuancePieChart');
  if (barCanvas && window.Chart) {
    new Chart(barCanvas, {
      type: 'bar',
      data: {
        labels: health.labels,
        datasets: [{
          label: 'Health, Safety & Governance records',
          data: health.values,
          borderWidth: 1,
          borderRadius: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
        plugins: { legend: { position: 'top' } }
      }
    });
  }
  if (pieCanvas && window.Chart) {
    new Chart(pieCanvas, {type:'pie',data:{labels:issuance.labels,datasets:[{label:'Issuance History',data:issuance.values,borderWidth:1}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'top'}}}});
  }})();
</script>
<script>
(function(){
  const filter=document.querySelector('.dashboard-date-filter input[name="date"]');
  if(!filter || !document.querySelector('.dashboard-date-filter')) return;
})();
</script>

<?php if (($u['role'] ?? '') === 'Administrator'): ?>
<section id="dashboard-login-history" class="gw-panel login-history-panel" style="margin-top:20px">
  <div class="gw-panel-head"><div><h2>Login History</h2><span><?= $showAllLogins ? 'Showing all matching login records' : 'Showing the latest 5 login records' ?><?= $loginDate ? ' for '.e($loginDate) : '' ?></span></div><span class="material-symbols-outlined" aria-hidden="true">history</span></div>
  <form method="get" class="date-filter" style="padding:0 18px 14px;justify-content:flex-end"><span class="dashboard-date-filter-label"><span class="material-symbols-outlined">filter_alt</span>Login date</span><input type="date" name="login_date" value="<?=e($loginDate)?>"><button class="gw-btn secondary">Filter</button><?php if($loginDate):?><a class="gw-btn secondary" href="<?=e(url('/dashboard.php'))?>#dashboard-login-history">Clear</a><?php endif;?><?php if($showAllLogins):?><a class="gw-btn secondary" href="<?=e(url('/dashboard.php'.($loginDate?'?login_date='.rawurlencode($loginDate):'')))?>#dashboard-login-history">Show latest 5</a><?php else:?><a class="gw-btn primary" href="<?=e(url('/dashboard.php?login_all=1'.($loginDate?'&login_date='.rawurlencode($loginDate):'')))?>#dashboard-login-history">See all login history</a><?php endif;?></form>
  <div class="table-wrap"><table class="data-table"><thead><tr><th>Name</th><th>Gmail</th><th>Role</th><th>Date &amp; Time</th></tr></thead><tbody><?php foreach($loginHistory as $login): ?><tr><td><?=e($login['name'])?></td><td><?=e($login['email'])?></td><td><?=e($login['role'])?></td><td><?=e($login['login_at'])?></td></tr><?php endforeach; ?><?php if(!$loginHistory): ?><tr><td colspan="4" class="empty">No successful logins match the selected filter.</td></tr><?php endif; ?></tbody></table></div>
</section>
<?php endif; ?>

<?php page_footer(); ?>
