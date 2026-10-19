<?php
/** Standalone installer view: all installer styles and interaction live here. */
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install Advanced Web Tools</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;450;500;550;600;650;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
<style>
:root {
    font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
    color:#202124;
    background:#fff;
    font-synthesis:none;
    --accent:#0071e3;
    --muted:#72757c;
    --line:#e6e7eb;
    --soft:#f5f5f7;
    --blue-soft:#edf5ff
}
* {
    box-sizing:border-box
}
body {
    margin:0
}
button,input,select {
    font:inherit
}
button,a,input,select {
    outline-offset:4px
}
button {
    cursor:pointer
}
button:disabled {
    cursor:default;
    opacity:.45
}
button:focus-visible,a:focus-visible {
    outline:3px solid #8dc4fb
}
button i,a i {
    pointer-events:none
}
button {
    border:0
}
a {
    color:var(--accent)
}
[hidden] {
    display:none!important
}
.shell {
    display:grid;
    grid-template-columns:292px minmax(0,1fr);
    height:100dvh;
    min-height:520px
}
.sidebar {
    position:relative;
    background:var(--soft);
    border-right:1px solid var(--line);
    padding:42px 26px 24px;
    display:flex;
    flex-direction:column;
    overflow-y:auto
}
.brand {
    padding:0 14px 35px
}
.brand img {
    display:block;
    width:64px;
    height:64px;
    object-fit:contain;
    border-radius:15px;
    margin-bottom:17px
}
.brand strong {
    display:block;
    font-size:15px;
    letter-spacing:-.35px
}
.brand span {
    display:block;
    color:var(--muted);
    font-size:12px;
    margin-top:6px
}
.steps {
    list-style:none;
    margin:0;
    padding:0;
    display:grid;
    gap:8px
}
.step-item {
    display:flex;
    align-items:center;
    gap:13px;
    padding:13px 14px;
    border-radius:11px;
    color:var(--muted)
}
.step-item.active {
    background:#ebeff4;
    color:#444951
}
.step-number {
    border:1px solid #d6d8dc;
    border-radius:50%;
    height:28px;
    width:28px;
    flex-shrink:0;
    display:grid;
    place-items:center;
    font-size:12px;
    font-weight:600
}
.active .step-number {
    background:var(--accent);
    border-color:var(--accent);
    color:white
}
.complete .step-number {
    border-color:transparent;
    color:var(--accent);
    background:#e6ebf2
}
.step-label {
    font-size:13px;
    font-family:inherit;
    font-weight:500;
    letter-spacing:-.1px;
    display:block
}
.step-description {
    font-size:12px;
    font-family:inherit;
    font-weight:400;
    display:block;
    margin-top:5px;
    line-height:1.5;
    color:var(--muted)
}
.sidebar-bottom {
    margin-top:auto;
    padding:30px 14px 0;
    font-size:11px;
    line-height:1.6;
    color:var(--muted)
}
.text-button {
    padding:0;
    background:none;
    color:var(--muted);
    font-size:12px;
    text-align:left
}
.sidebar-bottom p {
    margin:15px 0 0
}
.main {
    min-width:0;
    display:flex;
    flex-direction:column;
    height:100%;
    overflow:hidden
}
.topbar {
    display:flex;
    align-items:center;
    justify-content:space-between;
    min-height:85px;
    padding:0 52px;
    flex-shrink:0
}
.eyebrow {
    font-size:11px;
    font-weight:500;
    text-transform:uppercase;
    letter-spacing:.9px;
    color:var(--muted)
}
.help-button {
    background:transparent;
    border:1px solid var(--line);
    border-radius:20px;
    padding:9px 14px;
    font-size:12px;
    color:#565960;
    display:flex;
    gap:7px;
    align-items:center
}
.scroll-area {
    overflow-y:auto;
    flex:1;
    padding:28px 52px 48px
}
.content {
    max-width:790px;
    margin:auto
}
h1 {
    font-size:clamp(28px,3vw,39px);
    font-weight:650;
    letter-spacing:-1.45px;
    line-height:1.15;
    margin:0 0 15px
}
p.intro {
    font-size:15px;
    line-height:1.75;
    color:var(--muted);
    margin:0 0 32px;
    max-width:660px
}
.cards {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:16px
}
.card {
    position:relative;
    display:flex;
    flex-direction:column;
    align-items:flex-start;
    padding:26px 24px;
    border:1px solid #dcdfe4;
    border-radius:13px;
    cursor:pointer;
    background:#fff;
    transition:border-color .15s,box-shadow .15s;
    min-height:218px
}
.card:has(input:checked) {
    border-color:var(--accent);
    box-shadow:0 0 0 1px var(--accent);
    background:#fcfdff
}
.card:has(input:focus-visible) {
    outline:3px solid #8dc4fb;
    outline-offset:4px
}
.card input {
    position:absolute;
    opacity:0;
    pointer-events:none
}
.card .card-icon {
    color:var(--accent);
    font-size:26px;
    margin:1px 0 28px
}
.card h2 {
    font-size:18px;
    letter-spacing:-.5px;
    margin:0 0 11px;
    font-weight:600
}
.card p {
    font-size:12px;
    line-height:1.8;
    color:var(--muted);
    margin:0
}
.selection {
    position:absolute;
    top:22px;
    right:20px;
    width:19px;
    height:19px;
    border-radius:50%;
    border:1px solid #d3d6dc;
    background:#fff
}
.card:has(input:checked) .selection {
    border:5px solid var(--accent)
}
.badge {
    font-size:10px;
    line-height:1;
    font-weight:600;
    letter-spacing:.2px;
    background:var(--blue-soft);
    color:var(--accent);
    padding:6px 8px;
    border-radius:5px;
    display:inline-block;
    margin-top:16px
}
.note {
    display:flex;
    gap:10px;
    align-items:flex-start;
    margin-top:24px;
    color:var(--muted);
    font-size:12px;
    line-height:1.8
}
.note i {
    margin-top:4px;
    color:var(--accent)
}
.requirements {
    border:1px solid var(--line);
    border-radius:11px;
    margin-top:30px;
    padding:16px 19px;
    font-size:12px
}
.requirements summary {
    cursor:pointer;
    font-weight:500
}
.requirements ul {
    list-style:none;
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:10px;
    padding:7px 0 0;
    margin-bottom:0;
    color:var(--muted)
}
.requirements i {
    color:var(--accent);
    width:18px
}
.requirements .missing {
    color:#a33030
}
.requirements .missing i {
    color:inherit
}
.fields {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:22px 20px
}
.field {
    display:flex;
    flex-direction:column;
    gap:8px;
    min-width:0
}
.field.wide {
    grid-column:1/-1
}
.field label {
    font-size:12px;
    font-weight:550
}
.field label .optional {
    font-weight:400;
    color:var(--muted);
    margin-left:5px
}
.field input,.field select {
    width:100%;
    border:1px solid #d9dce1;
    border-radius:8px;
    background:#fff;
    padding:12px;
    color:#202124;
    font-size:13px;
    min-height:43px
}
.field input:focus,.field select:focus {
    border-color:var(--accent);
    outline:3px solid var(--blue-soft)
}
.field small {
    color:var(--muted);
    font-size:11px;
    line-height:1.7
}
.group-title {
    font-size:14px;
    font-weight:600;
    letter-spacing:-.2px;
    margin:29px 0 18px
}
.checkbox-row {
    display:flex;
    gap:10px;
    align-items:center;
    font-size:13px;
    color:#50535b;
    margin-bottom:25px
}
.checkbox-row input {
    accent-color:var(--accent);
    width:16px;
    height:16px
}
.error {
    background:#fff4f3;
    border:1px solid #f1d7d3;
    border-radius:10px;
    padding:15px 18px;
    margin:0 0 25px;
    color:#963a30;
    font-size:13px;
    line-height:1.7;
    overflow-wrap:anywhere
}
.status-box {
    border:1px solid var(--line);
    border-radius:13px;
    padding:25px;
    margin:22px 0
}
.status-box h2 {
    font-size:16px;
    margin:0 0 10px
}
.status-box p {
    font-size:13px;
    line-height:1.8;
    color:var(--muted);
    margin:0
}
.status-box>i {
    display:block;
    font-size:26px;
    color:var(--accent);
    margin-bottom:20px
}
.package-list {
    padding:0;
    list-style:none;
    margin:16px 0 0
}
.package-list li {
    display:flex;
    gap:10px;
    font-size:13px;
    padding:10px 0;
    border-top:1px solid var(--line)
}
.package-list i {
    color:var(--accent)
}
.footer {
    flex-shrink:0;
    border-top:1px solid var(--line);
    padding:22px 52px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:20px;
    background:#fff;
    min-height:88px
}
.footer-note {
    color:var(--muted);
    font-size:11px;
    text-align:center;
    line-height:1.5
}
.button {
    display:inline-flex;
    gap:10px;
    align-items:center;
    justify-content:center;
    border-radius:9px;
    padding:12px 22px;
    min-width:112px;
    font-size:13px;
    font-weight:500;
    text-decoration:none
}
.primary {
    background:var(--accent);
    color:white
}
.primary:hover {
    background:#0064ca
}
.secondary {
    background:var(--soft);
    color:#565960
}
.spinner {
    width:15px;
    height:15px;
    border:2px solid #c3dfff;
    border-top-color:var(--accent);
    border-radius:50%;
    display:inline-block;
    animation:spin 1s linear infinite
}
.busy-line {
    display:flex;
    gap:12px;
    align-items:center;
    color:var(--muted);
    font-size:13px;
    margin-top:22px
}
.finish-icon {
    display:grid;
    place-items:center;
    width:64px;
    height:64px;
    background:var(--blue-soft);
    color:var(--accent);
    font-size:28px;
    border-radius:18px;
    margin-bottom:28px
}
dialog {
    border:1px solid var(--line);
    border-radius:16px;
    padding:30px;
    max-width:520px;
    width:calc(100% - 36px);
    box-shadow:0 22px 90px #17243c26
}
dialog::backdrop {
    background:#18212c44;
    backdrop-filter:blur(4px)
}
dialog h2 {
    font-size:22px;
    letter-spacing:-.6px;
    margin:0 0 14px
}
dialog p {
    font-size:13px;
    line-height:1.85;
    color:var(--muted)
}
.dialog-footer {
    display:flex;
    justify-content:flex-end;
    margin-top:25px
}
.database-option {
    width:100%;
    text-align:left;
    display:flex;
    gap:15px;
    align-items:center;
    padding:18px;
    background:#fff;
    border:1px solid var(--line);
    border-radius:10px;
    margin:12px 0;
    color:inherit
}
.database-option>i {
    font-size:21px;
    color:var(--accent);
    width:27px
}
.database-option strong {
    font-size:14px;
    display:block
}
.database-option small {
    font-size:11px;
    display:block;
    margin-top:6px;
    color:var(--muted)
}
noscript {
    display:block;
    padding:24px;
    color:#963a30
}
@keyframes spin {
    to {
        transform:rotate(360deg)
    }
}
@media(min-width:1500px) {
    .scroll-area {
        padding-top:65px
    }
}
@media(max-width:1000px) {
    .shell {
        grid-template-columns:240px 1fr
    }
    .sidebar {
        padding:30px 15px 20px
    }
    .topbar {
        padding:0 30px
    }
    .scroll-area {
        padding:20px 30px 35px
    }
    .footer {
        padding:20px 30px
    }
    .cards {
        gap:12px
    }
    .card {
        padding:23px 20px
    }
}
@media(max-width:720px) {
    .shell {
        grid-template-columns:1fr;
        grid-template-rows:auto minmax(0,1fr);
        min-height:400px
    }
    .sidebar {
        padding:15px 20px;
        overflow:visible;
        display:block;
        border-right:0;
        border-bottom:1px solid var(--line)
    }
    .brand {
        padding:0;
        display:flex;
        align-items:center;
        gap:11px
    }
    .brand img {
        width:34px;
        height:34px;
        border-radius:8px;
        margin:0
    }
    .brand span {
        display:none
    }
    .brand strong {
        font-size:13px
    }
    .steps {
        display:flex;
        gap:6px;
        margin-top:15px
    }
    .step-item {
        padding:0;
        flex:1;
        border-radius:3px;
        overflow:hidden;
        height:4px;
        background:#dcdfe5
    }
    .step-item.active,.step-item.complete {
        background:var(--accent)
    }
    .step-item>* {
        display:none
    }
    .sidebar-bottom {
        position:absolute;
        top:22px;
        right:20px;
        margin:0;
        padding:0;
    }
    .sidebar-bottom p {
        display:none;
    }
    .sidebar-bottom .text-button {
        font-size:11px;
    }
    .topbar {
        min-height:66px;
        padding:0 24px
    }
    .scroll-area {
        padding:20px 24px 30px
    }
    .footer {
        padding:17px 24px;
        min-height:80px
    }
    .footer-note {
        display:none
    }
    .cards {
        grid-template-columns:1fr
    }
    .card {
        min-height:0;
        padding:23px
    }
    .card .card-icon {
        margin-bottom:20px
    }
    .fields {
        grid-template-columns:1fr
    }
    .field.wide {
        grid-column:auto
    }
    h1 {
        font-size:30px
    }
    .requirements ul {
        grid-template-columns:1fr 1fr
    }
}
@media(prefers-reduced-motion:reduce) {
    * {
        scroll-behavior:auto!important;
        transition:none!important
    }
    .spinner {
        animation:none
    }
}
</style>
</head>
<body>
<noscript>Enable JavaScript to use the web installer. You can also install from your terminal with php awt install.</noscript>
<div class="shell">
<aside class="sidebar" aria-label="Installation progress">
<div class="brand"><img src="https://avatars.githubusercontent.com/u/152005400?s=200&amp;v=4" alt="AWT"><div><strong>Advanced Web Tools</strong><span>A little setup. Endless possibilities.</span></div></div>
<ol class="steps" id="steps"></ol>
<div class="sidebar-bottom"><button type="button" class="text-button" id="restart"><i class="fa-solid fa-arrow-rotate-left" aria-hidden="true"></i> Start again</button><p>AWT <?= htmlspecialchars((string) AWT_VERSION, ENT_QUOTES, 'UTF-8') ?><br>Made for what comes next.</p></div>
</aside>
<main class="main">
<header class="topbar"><span class="eyebrow" id="step-count"></span><button type="button" class="help-button" id="help"><i class="fa-regular fa-circle-question" aria-hidden="true"></i> Help</button></header>
<div class="scroll-area" id="scroll-area"><div class="content"><div id="error" class="error" role="alert" hidden></div><form id="installer-form"><div id="screen"></div><button type="submit" hidden tabindex="-1" aria-hidden="true"></button></form><div class="busy-line" id="busy" role="status" hidden><span class="spinner" aria-hidden="true"></span><span id="busy-text">Saving your choices…</span></div></div></div>
<footer class="footer"><button type="button" id="back" class="button secondary"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back</button><span class="footer-note" id="footer-note">Your next project starts here.</span><button type="submit" form="installer-form" id="next" class="button primary">Next <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button><a href="/" id="open-site" class="button primary" hidden>Open website <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a></footer>
</main>
</div>
<dialog id="help-dialog" aria-labelledby="help-title"><h2 id="help-title">A little guidance.</h2><p id="help-copy"></p><div class="dialog-footer"><button type="button" class="button primary" data-close="help-dialog">Got it</button></div></dialog>
<dialog id="database-dialog" aria-labelledby="database-title"><h2 id="database-title">Choose your database.</h2><p>Connect AWT to an existing, empty database on your server.</p><div id="database-options"></div><div class="dialog-footer"><button type="button" class="button secondary" data-close="database-dialog">Cancel</button></div></dialog>
<dialog id="restart-dialog" aria-labelledby="restart-title"><h2 id="restart-title">Start fresh?</h2><p>Your setup changes will be rolled back, including installed packages and the database tables created during setup.</p><div class="dialog-footer" style="gap:10px"><button type="button" class="button secondary" data-close="restart-dialog">Keep going</button><button type="button" class="button primary" id="confirm-restart">Start again</button></div></dialog>
<script>
'use strict';
let state = <?= json_encode($initial, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>;
const csrf = <?= json_encode($csrf, JSON_HEX_TAG | JSON_THROW_ON_ERROR) ?>;
const $ = id => document.getElementById(id);
const escape = value => String(value ?? '').replace(/[&<>"']/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[character]));
const stepInfo = [
 ['Welcome', 'Choose your starting point', 'Welcome to Advanced Web Tools.', 'Choose an installation profile below. We’ll take care of the essentials so you can focus on what you want to build.', 'Choose AWT CMS for a complete website with visual editing and an administration dashboard. Choose AWT Framework to build a custom application with the modules you need.'],
 ['Database', 'A home for your data', 'Give your project a home.', 'Choose where AWT should keep your data. SQLite is ready to go, with nothing extra to configure.', 'SQLite stores your data in awt_db.sqlite in the project root and needs no credentials. Choose Other to use MySQL or PostgreSQL. Create an empty database first, then enter its connection details. Continuing creates the framework tables.'],
 ['Settings', 'Make it your own', 'Make it yours.', 'Start with a name. You can also add your contact details and adjust how your website handles sessions.', 'Only the website name is required. The website path is usually /. Contact details are optional. HTTPS-only cookies are recommended for a public website; switch them off only when developing over HTTP.'],
 ['Administrator', 'Your first account', 'You’re in control.', 'Create an administrator account to manage your website. Keep these details somewhere safe.', 'AWT CMS requires an administrator account. Framework installations can skip this step. Use a unique password between 12 and 72 bytes. First and last names are optional.'],
 ['Packages', 'Bring it all together', 'Add the finishing touches.', 'We’ll install your packages and their dependencies, then enable everything for you.', 'The CMS profile automatically selects a release compatible with your framework and installs all of its dependencies. With Framework, you can provide a trusted HTTP or HTTPS package ZIP URL, or continue without a package. Wait for installation to finish before moving on.'],
 ['Ready', 'Your next chapter', 'Welcome to AWT.', 'Your installation is complete. Everything is in place. Go ahead and make something your own.', 'Your database configuration has been saved. The installer is now closed and your website is ready to open.']
];
let busy = false;
let databaseType = 'sqlite';
const drafts = {};
const hints = {'Website Name':'The name shown across your website.', 'Hostname Path':'Use / for the root, or a path such as /my-site/.', 'Session HTTPS Only':'Send session cookies only over HTTPS.', 'Session HTTP Only':'Keep session cookies inaccessible to JavaScript.', 'Session ID Regeneration Time':'Time in seconds between session ID renewals. Default: 900.', 'Session SameSite':'Restrict session cookies on requests from other websites.'};
function field(name, label, value = '', type = 'text', required = false, hint = '', wide = false) {
 const id = 'field-' + name.replace(/[^a-zA-Z0-9]/g, '-');
 return `<div class="field ${wide ? 'wide' : ''}"><label for="${id}">${escape(label)}${required ? '' : '<span class="optional">Optional</span>'}</label><input id="${id}" name="${escape(name)}" type="${type}" value="${type === 'password' ? '' : escape(value)}" ${required ? 'required' : ''} ${type === 'password' ? 'autocomplete="new-password" minlength="12" maxlength="72"' : 'maxlength="255"'}>${hint ? `<small>${escape(hint)}</small>` : ''}</div>`;
}
function choice(value, name, description, icon, selected, badge = '') {
 return `<label class="card"><input type="radio" name="${state.step === 1 ? 'profile' : 'database_card'}" value="${value}" ${selected ? 'checked' : ''}><span class="selection" aria-hidden="true"></span><i class="fa-solid ${icon} card-icon" aria-hidden="true"></i><h2>${escape(name)}</h2><p>${escape(description)}</p>${badge ? `<span class="badge">${escape(badge)}</span>` : ''}</label>`;
}
function remember() {
 const values = Object.fromEntries(new FormData($('installer-form')));
 for (const key of Object.keys(values)) if (key.includes('password')) delete values[key];
 drafts[state.step] = values;
}
function render() {
 const step = state.step;
 const info = stepInfo[step - 1];
 $('steps').innerHTML = stepInfo.map((item,index) => `<li class="step-item ${index + 1 === step ? 'active' : index + 1 < step ? 'complete' : ''}" ${index + 1 === step ? 'aria-current="step"' : ''}><span class="step-number">${index + 1 < step ? '<i class="fa-solid fa-check" aria-hidden="true"></i>' : index + 1}</span><span><span class="step-label">${item[0]}</span><span class="step-description">${item[1]}</span></span></li>`).join('');
 $('step-count').textContent = `Step ${step} of 6 · ${info[0]}`;
 let html = `${step === 6 ? '<div class="finish-icon"><i class="fa-solid fa-check" aria-hidden="true"></i></div>' : ''}<h1 tabindex="-1" id="screen-title">${info[2]}</h1><p class="intro">${info[3]}</p>`;
 if (step === 1) {
  html += '<div class="cards">' + state.profiles.map(profile => choice(profile.id, profile.name, profile.description, profile.icon, state.profile === profile.id, profile.id === 'cms' ? 'Ready to create' : 'Built for developers')).join('') + '</div>';
  const missing = Object.values(state.requirements).some(value => !value);
  html += `<details class="requirements" ${missing ? 'open' : ''}><summary>${missing ? 'A few server requirements need your attention' : 'Your server is ready for AWT'}</summary><ul>${Object.entries(state.requirements).map(([name,enabled]) => `<li class="${enabled ? '' : 'missing'}"><i class="fa-solid ${enabled ? 'fa-check' : 'fa-xmark'}" aria-hidden="true"></i> ${escape(name)}${enabled ? '' : ' — missing'}</li>`).join('')}</ul></details>`;
 } else if (step === 2) {
  html += '<div class="cards">' + choice('sqlite', 'SQLite', 'Simple, local, and ready when you are. AWT creates and configures the database for you.', 'fa-database', databaseType === 'sqlite', 'Default') + choice('other', databaseType === 'sqlite' ? 'Other' : databaseType === 'mysql' ? 'MySQL' : 'PostgreSQL', 'Use an existing MySQL or PostgreSQL database. A good fit for a managed database server.', 'fa-server', databaseType !== 'sqlite') + '</div><div id="connection-fields"></div>';
 } else if (step === 3) {
  let category = '';
  for (const setting of state.settings) {
   if (category !== setting.category) {
    if (category) html += '</div>';
    category = setting.category;
    html += `<h2 class="group-title">${escape(category)}</h2><div class="fields">`;
   }
   const value = drafts[3]?.[setting.name] ?? setting.value;
   if (setting.value_type === 'boolean') {
    const id = 'setting-' + setting.id;
    html += `<div class="field"><label for="${id}">${escape(setting.name)}</label><select id="${id}" name="${escape(setting.name)}"><option value="true" ${value === 'true' ? 'selected' : ''}>On</option><option value="false" ${value === 'false' ? 'selected' : ''}>Off</option></select><small>${escape(hints[setting.name] || '')}</small></div>`;
   } else html += field(setting.name, setting.name === 'Website Name' ? 'Website name' : setting.name, value, setting.value_type === 'number' ? 'number' : setting.name === 'Contact Email' ? 'email' : 'text', setting.name === 'Website Name', hints[setting.name] || '');
  }
  if (category) html += '</div>';
 } else if (step === 4) {
  if (state.profile === 'framework') html += '<label class="checkbox-row"><input type="checkbox" name="skip_admin" id="skip-admin"> Set up an administrator later</label>';
  html += `<div id="admin-fields" class="fields">${field('email','Email address',drafts[4]?.email,'email',true)}${field('username','Username',drafts[4]?.username,'text',true)}${field('firstname','First name',drafts[4]?.firstname)}${field('lastname','Last name',drafts[4]?.lastname)}${field('password','Password','','password',true,'Use 12–72 bytes. A long, unique passphrase works well.')}${field('password_confirmation','Confirm password','','password',true)}</div>`;
  if (state.admin_created) html += '<div class="note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><span>An administrator has already been created. Enter new details to replace it.</span></div>';
 } else if (step === 5) {
  if (state.packages_done) html += `<div class="status-box"><i class="fa-solid fa-check" aria-hidden="true"></i><h2>${state.packages.length ? 'Everything is installed and enabled.' : 'Your framework is ready.'}</h2><p>${state.packages.length ? 'Your packages and dependencies are ready to use. Choose Next to finish setup.' : 'You can add packages whenever you’re ready. Choose Next to finish setup.'}</p><ul class="package-list">${state.packages.map(name => `<li><i class="fa-solid fa-check" aria-hidden="true"></i>${escape(name)}<span style="margin-left:auto;color:var(--muted)">Enabled</span></li>`).join('')}</ul></div>`;
  else if (state.profile === 'cms') html += '<div class="status-box"><i class="fa-solid fa-box-open" aria-hidden="true"></i><h2>Getting AWT CMS ready.</h2><p>Downloading the CMS package and installing its dependencies. This may take a few minutes. Keep this page open.</p></div>';
  else html += `<div class="fields">${field('package_url','Package ZIP URL',drafts[5]?.package_url,'url',false,'Have a package in mind? Paste its HTTP or HTTPS ZIP URL. Leave this blank to start with the framework.',true)}</div><div class="note"><i class="fa-solid fa-cube" aria-hidden="true"></i><span>Dependencies install automatically. All packages installed here will be enabled.</span></div>`;
 } else html += `<div class="status-box"><h2>${state.profile === 'cms' ? 'Your CMS. Your canvas.' : 'A foundation for your ideas.'}</h2><p>${state.profile === 'cms' ? 'Sign in with your administrator account to start shaping your website.' : 'Start building your application, explore the framework, or add your first package.'}</p></div>`;
 $('screen').innerHTML = html;
 $('back').disabled = busy || step === 1 || step === 6;
 $('next').hidden = step === 6;
 $('open-site').hidden = step !== 6;
 $('restart').hidden = step === 6;
 $('footer-note').textContent = step === 5 && !state.packages_done ? 'Dependencies are included. Every package is enabled.' : step === 6 ? 'You’re ready to build.' : 'Your next project starts here.';
 if (step === 2) renderConnection();
 if (step === 4 && $('skip-admin')) $('skip-admin').addEventListener('change', () => {
  const skip = $('skip-admin').checked;
  $('admin-fields').hidden = skip;
  $('admin-fields').querySelectorAll('input').forEach(input => input.disabled = skip);
 });
 if (step === 1) document.querySelectorAll('[name="profile"]').forEach(input => input.addEventListener('change', () => state.profile = input.value));
 if (step === 2) document.querySelectorAll('[name="database_card"]').forEach(input => input.addEventListener('change', () => {
  if (input.value === 'sqlite') { remember(); databaseType = 'sqlite'; render(); }
  else { input.checked = false; document.querySelector('[value="sqlite"]').checked = databaseType === 'sqlite'; openDatabaseDialog(); }
 }));
 updateButtons();
}
function renderConnection() {
 if (databaseType === 'sqlite') {
  $('connection-fields').innerHTML = state.drivers.includes('sqlite') ? '' : '<div class="note">Enable PHP pdo_sqlite to use SQLite, or choose another database.</div>';
  return;
 }
 $('connection-fields').innerHTML = `<h2 class="group-title">Connection details <button type="button" class="text-button" id="change-database" style="color:var(--accent);margin-left:12px">Change database</button></h2><div class="fields">${field('host','Hostname',drafts[2]?.host || 'localhost','text',false,'Usually localhost, or the address provided by your host.')}${field('database_name','Database name',drafts[2]?.database_name,'text',true,'Use an existing, empty database.')}${field('username','Database username',drafts[2]?.username,'text',true)}${field('password','Database password','','password',false)}</div>`;
 // Database passwords are supplied by the host and have no administrator length rule.
 document.querySelector('[name="password"]').removeAttribute('minlength');
 document.querySelector('[name="password"]').removeAttribute('maxlength');
 $('change-database').addEventListener('click', openDatabaseDialog);
}
function openDatabaseDialog() {
 remember();
 $('database-options').innerHTML = [['mysql','MySQL','MySQL or MariaDB'],['postgresql','PostgreSQL','PostgreSQL server']].map(([id,name,description]) => {
  const available = state.drivers.includes(id === 'postgresql' ? 'pgsql' : id);
  return `<button type="button" class="database-option" data-db="${id}" ${available ? '' : 'disabled'}><i class="fa-solid fa-database" aria-hidden="true"></i><span><strong>${name}</strong><small>${available ? description : 'Enable PHP pdo_' + (id === 'postgresql' ? 'pgsql' : id) + ' to continue'}</small></span></button>`;
 }).join('');
 document.querySelectorAll('[data-db]').forEach(button => button.addEventListener('click', () => { databaseType = button.dataset.db; $('database-dialog').close(); render(); }));
 $('database-dialog').showModal();
}
function updateButtons() {
 let disabled = busy || state.rollback_error;
 if (state.step === 1) disabled ||= Object.values(state.requirements).some(value => !value);
 if (state.step === 2) disabled ||= !state.drivers.includes(databaseType === 'postgresql' ? 'pgsql' : databaseType);
 $('next').disabled = disabled;
 $('next').innerHTML = busy ? '<span class="spinner" aria-hidden="true"></span> Please wait' : `${state.step === 5 && !state.packages_done ? 'cms' === state.profile ? 'Install CMS' : 'Continue' : 'Next'} <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>`;
 $('back').disabled = busy || state.step === 1 || state.step === 6 || state.rollback_error;
 $('restart').disabled = busy;
 $('help').disabled = busy;
}
async function request(action, values = {}) {
 if (busy) return false;
 busy = true;
 $('error').hidden = true;
 $('busy').hidden = false;
 $('busy-text').textContent = action === 'packages' ? 'Installing packages and dependencies. This may take a few minutes…' : state.step === 2 && action === 'next' ? 'Connecting to your database and preparing the framework…' : action === 'restart' || action === 'back' && state.step === 3 ? 'Restoring your setup…' : 'Saving your choices…';
 $('installer-form').setAttribute('aria-busy', 'true');
 updateButtons();
 try {
  const response = await fetch('/', {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json','Accept':'application/json'}, body:JSON.stringify({action,csrf,step:state.step,...values})});
  const contentType = response.headers.get('content-type') || '';
  if (!contentType.includes('application/json')) throw new Error('The server could not complete this request. Reload the page to check your setup progress.');
  const result = await response.json();
  if (result.step) {
   const previousStep = state.step;
   state = result;
   if (action === 'restart' || !response.ok && result.step === 1) { for (const key of Object.keys(drafts)) delete drafts[key]; databaseType = 'sqlite'; }
   render();
   if (previousStep !== state.step) { $('scroll-area').scrollTop = 0; $('screen-title').focus(); }
  }
  if (!response.ok) throw new Error(result.error || 'Something needs your attention. Please try again.');
  return true;
 } catch (error) {
  $('error').textContent = error.message;
  $('error').hidden = false;
  $('scroll-area').scrollTop = 0;
  return false;
 } finally {
  busy = false;
  $('busy').hidden = true;
  $('installer-form').removeAttribute('aria-busy');
  updateButtons();
 }
}
$('installer-form').addEventListener('submit', async event => {
 event.preventDefault();
 if (busy || $('next').disabled) return;
 remember();
 const values = Object.fromEntries(new FormData($('installer-form')));
 if (state.step === 2) values.database_type = databaseType;
 if (state.step === 3) { values.settings = {...values}; }
 if (state.step === 4) values.skip_admin = $('skip-admin')?.checked || false;
 if (state.step === 5 && !state.packages_done) { await request('packages', values); return; }
 const success = await request('next', values);
 if (success && state.step === 5 && state.profile === 'cms' && !state.packages_done) await request('packages');
});
$('back').addEventListener('click', () => { remember(); request('back'); });
$('help').addEventListener('click', () => { $('help-copy').textContent = stepInfo[state.step-1][4]; $('help-dialog').showModal(); });
$('restart').addEventListener('click', () => $('restart-dialog').showModal());
$('confirm-restart').addEventListener('click', () => { $('restart-dialog').close(); request('restart'); });
 document.querySelectorAll('[data-close]').forEach(button => button.addEventListener('click', () => $(button.dataset.close).close()));
render();
if (state.rollback_error || state.notice) {
 $('error').textContent = state.rollback_error ? 'Setup needs recovery. Check server permissions and database access, then choose Start again to retry.' : state.notice;
 $('error').hidden = false;
}
if (state.step === 5 && state.profile === 'cms' && !state.packages_done && !state.rollback_error) request('packages');
</script>
</body>
</html>
