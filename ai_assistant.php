<?php
require_once __DIR__.'/includes/helpers.php';
require_login();
page_header('AI System Assistant','ai'); ?>
<div class="gw-breadcrumb"><span class="material-symbols-outlined">home</span><span>Core Transaction 4</span><span>/</span><strong>AI System Assistant</strong></div>

<section class="gw-hero dashboard-hero ai-hero">
  <div>
    <div class="eyebrow">GEMINI AI • CORE TRANSACTION 4</div>
    <div class="dashboard-title-brand">
      <div class="brand-logo-white dashboard-logo-wrap"><img src="assets/logo2.svg" alt="Great Solomon Manpower Services Inc. logo" class="brand-logo-image"></div>
      <h1>AI System Assistant</h1>
    </div>
    <p>Ask questions about CT4 modules, workflows, records, dashboards, documentation, and security.</p>
  </div>
  <div class="ai-hero-badge"><span class="material-symbols-outlined">auto_awesome</span><span>Gemini powered</span></div>
</section>

<section class="ai-workspace" aria-label="AI System Assistant">
  <aside class="ai-info-panel">
    <div class="ai-info-icon"><span class="material-symbols-outlined">smart_toy</span></div>
    <h2>CT4 Assistant</h2>
    <p class="ai-info-copy">A focused help desk for the Great Solomon Manpower Services Inc. system.</p>

    <div class="ai-info-section">
      <div class="ai-section-label">You can ask about</div>
      <div class="ai-topic"><span class="material-symbols-outlined">dashboard</span><span>Dashboard &amp; reports</span></div>
      <div class="ai-topic"><span class="material-symbols-outlined">health_and_safety</span><span>Health &amp; safety</span></div>
      <div class="ai-topic"><span class="material-symbols-outlined">gavel</span><span>Legal &amp; compliance</span></div>
      <div class="ai-topic"><span class="material-symbols-outlined">admin_panel_settings</span><span>System administration</span></div>
      <div class="ai-topic"><span class="material-symbols-outlined">inventory_2</span><span>Assets &amp; issuance</span></div>
    </div>

    <div class="ai-security-card">
      <span class="material-symbols-outlined">verified_user</span>
      <div><strong>Protected context</strong><p>Passwords, OTPs, API keys, and other secrets are excluded from AI context.</p></div>
    </div>
  </aside>

  <div class="ai-chat-shell">
    <div class="ai-chat-head">
      <div class="ai-chat-brand">
        <div class="ai-avatar"><span class="material-symbols-outlined">auto_awesome</span></div>
        <div><strong>CT4 System Assistant</strong><span><span class="ai-online-dot"></span>Ready to help</span></div>
      </div>
      <button class="gw-btn secondary ai-clear" type="button" id="aiClear"><span class="material-symbols-outlined">delete_sweep</span>Clear chat</button>
    </div>

    <div id="aiMessages" class="ai-messages">
      <div class="ai-message assistant">
        <div class="ai-msg-icon"><span class="material-symbols-outlined">auto_awesome</span></div>
        <div><strong>CT4 AI</strong><p>Hello! I can help you navigate Core Transaction 4. Choose a topic below or type your question.</p></div>
      </div>
    </div>

    <div class="ai-suggestions">
      <div class="ai-suggestions-label">Quick questions</div>
      <div class="ai-suggestion-list">
        <button type="button" data-prompt="What are the four main operational modules in CT4 and what does each one contain?"><span class="material-symbols-outlined">view_module</span>Explain the modules</button>
        <button type="button" data-prompt="Give me a summary of the current system activity and record counts."><span class="material-symbols-outlined">monitoring</span>System activity</button>
        <button type="button" data-prompt="How does the login and security workflow work in CT4?"><span class="material-symbols-outlined">lock</span>Login &amp; security</button>
        <button type="button" data-prompt="What can the Reports, Analysis & Dashboard module show?"><span class="material-symbols-outlined">analytics</span>Dashboard help</button>
      </div>
    </div>

    <form id="aiForm" class="ai-input-row" autocomplete="off">
      <div class="ai-input-wrap">
        <textarea id="aiInput" name="message" rows="1" maxlength="4000" placeholder="Type your question about CT4..." aria-label="Ask the CT4 AI Assistant"></textarea>
        <span class="ai-input-hint">Enter to send • Shift + Enter for a new line</span>
      </div>
      <button class="ai-send" type="submit" title="Send message" aria-label="Send message"><span class="material-symbols-outlined">send</span></button>
    </form>
    <div class="ai-disclaimer"><span class="material-symbols-outlined">lock</span> AI receives authorized system summaries only. Sensitive credentials and authentication secrets are never sent to Gemini.</div>
  </div>
</section>
<?php page_footer(); ?>
