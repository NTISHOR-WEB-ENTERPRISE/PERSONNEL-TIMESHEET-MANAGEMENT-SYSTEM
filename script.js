// ═══════════════════════════════════
//  STATE
// ═══════════════════════════════════
const state = {
  role: 'employee',
  user: null,
  clockedIn: false,
  clockInTime: null,
  clockLog: [],
  currentWeek: new Date(),
  timesheetData: {},
  pendingRejectId: null,
  currentApprovalTab: 'pending',
  currentAdminTab: 'personnel',
};

const users = {
  employee: { name: 'John Doe', initials: 'JD', role: 'Employee', dept: 'Engineering', color: '#1d4ed8' },
  manager:  { name: 'Sarah Kim',  initials: 'SK', role: 'Manager',  dept: 'Operations',  color: '#7c3aed' },
  admin:    { name: 'Alex Chen',  initials: 'AC', role: 'Admin',    dept: 'IT',          color: '#059669' },
};

const personnel = [
  { id:1, name:'John Doe',      initials:'JD', role:'Employee', dept:'Engineering', email:'john@co.com',  color:'#1d4ed8', status:'active' },
  { id:2, name:'Sarah Kim',     initials:'SK', role:'Manager',  dept:'Operations',  email:'sarah@co.com', color:'#7c3aed', status:'active' },
  { id:3, name:'Alex Chen',     initials:'AC', role:'Admin',    dept:'IT',          email:'alex@co.com',  color:'#059669', status:'active' },
  { id:4, name:'Maria Santos',  initials:'MS', role:'Employee', dept:'Marketing',   email:'maria@co.com', color:'#d97706', status:'active' },
  { id:5, name:'David Okafor',  initials:'DO', role:'Employee', dept:'Finance',     email:'david@co.com', color:'#dc2626', status:'active' },
  { id:6, name:'Priya Nair',    initials:'PN', role:'Employee', dept:'HR',          email:'priya@co.com', color:'#0891b2', status:'inactive' },
];

const approvals = [
  { id:1, employee:'John Doe',     initials:'JD', color:'#1d4ed8', week:'Jun 2–8, 2025',   hours:42.5, submitted:'Jun 9',  status:'pending'  },
  { id:2, employee:'Maria Santos', initials:'MS', color:'#d97706', week:'Jun 2–8, 2025',   hours:38.0, submitted:'Jun 9',  status:'pending'  },
  { id:3, employee:'David Okafor', initials:'DO', color:'#dc2626', week:'May 26–Jun 1, 2025', hours:40.0, submitted:'Jun 2', status:'approved' },
  { id:4, employee:'Priya Nair',   initials:'PN', color:'#0891b2', week:'May 26–Jun 1, 2025', hours:36.5, submitted:'Jun 2', status:'rejected', reason:'Missing entries for Wed–Thu.' },
];

const myTimesheets = [
  { week:'Jun 2–8, 2025',   hours:42.5, submitted:'Jun 9',  status:'pending'  },
  { week:'May 26–Jun 1, 2025', hours:40.0, submitted:'Jun 2', status:'approved' },
  { week:'May 19–25, 2025', hours:38.5, submitted:'May 26', status:'approved'  },
  { week:'May 12–18, 2025', hours:39.0, submitted:'May 19', status:'rejected', reason:'Hours exceed allowable without overtime pre-approval.' },
];

const navConfigs = {
  employee: [
    { id:'dashboard',     label:'Dashboard',        icon:'home' },
    { id:'clock',         label:'Clock In/Out',     icon:'clock' },
    { id:'timesheet',     label:'My Timesheet',     icon:'calendar' },
    { id:'my-timesheets', label:'Submission History',icon:'list'  },
  ],
  manager: [
    { id:'dashboard',  label:'Dashboard',      icon:'home'    },
    { id:'approvals',  label:'Approvals',      icon:'check'   },
    { id:'reports',    label:'Reports',        icon:'chart'   },
    { id:'timesheet',  label:'My Timesheet',   icon:'calendar'},
    { id:'clock',      label:'Clock In/Out',   icon:'clock'   },
  ],
  admin: [
    { id:'dashboard',  label:'Dashboard',      icon:'home'    },
    { id:'admin',      label:'Admin Panel',    icon:'settings'},
    { id:'approvals',  label:'Approvals',      icon:'check'   },
    { id:'reports',    label:'Reports',        icon:'chart'   },
    { id:'timesheet',  label:'My Timesheet',   icon:'calendar'},
    { id:'clock',      label:'Clock In/Out',   icon:'clock'   },
  ],
};

const icons = {
  home:     `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>`,
  clock:    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>`,
  calendar: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>`,
  check:    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polyline points="20 6 9 17 4 12"/></svg>`,
  chart:    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>`,
  settings: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>`,
  list:     `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>`,
};

// ═══════════════════════════════════
//  AUTH
// ═══════════════════════════════════
let selectedRole = 'employee';
function selectRole(r, el) {
  selectedRole = r;
  document.querySelectorAll('.role-pill').forEach(p => p.classList.remove('active'));
  el.classList.add('active');
}

function doLogin() {
  state.role = selectedRole;
  state.user = users[selectedRole];
  document.getElementById('auth-screen').style.display = 'none';
  document.getElementById('app').classList.add('active');
  initApp();
}

function doLogout() {
  document.getElementById('auth-screen').style.display = 'flex';
  document.getElementById('app').classList.remove('active');
  state.clockedIn = false;
  state.clockLog = [];
}

// ═══════════════════════════════════
//  INIT
// ═══════════════════════════════════
function initApp() {
  const u = state.user;
  document.getElementById('sb-avatar').textContent = u.initials;
  document.getElementById('sb-avatar').style.background = u.color;
  document.getElementById('sb-name').textContent = u.name;
  document.getElementById('sb-role').textContent = u.role;

  buildNav();
  startClock();
  navigateTo('dashboard');
}

function buildNav() {
  const nav = document.getElementById('sidebar-nav');
  nav.innerHTML = '';
  const items = navConfigs[state.role];
  items.forEach(item => {
    const el = document.createElement('div');
    el.className = 'nav-item';
    el.dataset.view = item.id;
    el.innerHTML = icons[item.icon] + `<span>${item.label}</span>`;
    el.onclick = () => navigateTo(item.id);
    nav.appendChild(el);
  });
}

function navigateTo(viewId) {
  document.querySelectorAll('.nav-item').forEach(n => {
    n.classList.toggle('active', n.dataset.view === viewId);
  });
  document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
  const el = document.getElementById('view-' + viewId);
  if (el) el.classList.add('active');

  const titles = {
    dashboard: 'Dashboard', clock: 'Clock In / Out', timesheet: 'Weekly Timesheet',
    approvals: 'Approvals', reports: 'Reports', admin: 'Admin Panel', 'my-timesheets': 'My Timesheets'
  };
  document.getElementById('topbar-title').textContent = titles[viewId] || viewId;
  document.getElementById('topbar-breadcrumb').textContent = `TimeTrack / ${titles[viewId] || viewId}`;

  // Render view
  if (viewId === 'dashboard') renderDashboard();
  if (viewId === 'clock') renderClock();
  if (viewId === 'timesheet') renderTimesheet();
  if (viewId === 'approvals') renderApprovals();
  if (viewId === 'admin') renderAdmin();
  if (viewId === 'my-timesheets') renderMyTimesheets();
}

// ═══════════════════════════════════
//  LIVE CLOCK
// ═══════════════════════════════════
function startClock() {
  function tick() {
    const now = new Date();
    const t = now.toLocaleTimeString('en-US', { hour12: false });
    const d = now.toLocaleDateString('en-US', { weekday:'long', year:'numeric', month:'long', day:'numeric' });
    document.getElementById('topbar-clock').textContent = t;
    document.getElementById('clock-time').textContent = t;
    document.getElementById('clock-date').textContent = d;
  }
  tick();
  setInterval(tick, 1000);
}

// ═══════════════════════════════════
//  DASHBOARD
// ═══════════════════════════════════
function renderDashboard() {
  const statsEl = document.getElementById('dash-stats');
  const lowerEl = document.getElementById('dash-lower');

  if (state.role === 'employee') {
    statsEl.innerHTML = `
      ${statCard('Hours This Week','38.5h','On track for 40h','up')}
      ${statCard('Days Present','4','This week')}
      ${statCard('Pending Timesheets','1','Awaiting approval','warn')}
      ${statCard('Overtime Hours','0h','This month')}
    `;
    lowerEl.innerHTML = `
      <div class="card">
        <div class="card-header"><span class="card-title">This Week at a Glance</span></div>
        <div class="card-body">
          <div class="chart-bar-wrap">
            ${barRow('Mon','8.5',8.5,100,'#1d4ed8')}
            ${barRow('Tue','8.0',8,94,'#1d4ed8')}
            ${barRow('Wed','7.5',7.5,88,'#1d4ed8')}
            ${barRow('Thu','8.0',8,94,'#1d4ed8')}
            ${barRow('Fri','6.5',6.5,76,'#059669')}
            ${barRow('Sat','0h','0',0,'#e2e5ec')}
            ${barRow('Sun','0h','0',0,'#e2e5ec')}
          </div>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><span class="card-title">Recent Timesheets</span></div>
        <div>
          ${myTimesheets.slice(0,3).map(ts=>`
            <div style="display:flex;align-items:center;padding:12px 20px;border-bottom:1px solid var(--rule);">
              <div style="flex:1;">
                <div style="font-weight:600;font-size:13px;">${ts.week}</div>
                <div style="font-size:11px;color:var(--ink-faint);">${ts.hours}h · Submitted ${ts.submitted}</div>
              </div>
              ${statusBadge(ts.status)}
            </div>
          `).join('')}
        </div>
      </div>
    `;
  } else {
    const pending = approvals.filter(a=>a.status==='pending').length;
    statsEl.innerHTML = `
      ${statCard('Active Employees', personnel.filter(p=>p.status==='active').length,'Total headcount')}
      ${statCard('Pending Approvals', pending,'Timesheets awaiting review','warn')}
      ${statCard('Avg Hours/Week','39.2h','This month','up')}
      ${statCard('Overtime Cases','3','Employees > 40h this week','warn')}
    `;
    lowerEl.innerHTML = `
      <div class="card">
        <div class="card-header"><span class="card-title">Hours by Department</span></div>
        <div class="card-body">
          <div class="chart-bar-wrap">
            ${barRow('Engineering','312h',312,100,'#1d4ed8')}
            ${barRow('Marketing','240h',240,77,'#7c3aed')}
            ${barRow('Finance','198h',198,63,'#059669')}
            ${barRow('HR','156h',156,50,'#d97706')}
            ${barRow('Operations','280h',280,90,'#0891b2')}
          </div>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><span class="card-title">Approval Queue</span></div>
        <div>
          ${approvals.filter(a=>a.status==='pending').map(a=>`
            <div style="display:flex;align-items:center;padding:12px 20px;border-bottom:1px solid var(--rule);">
              <div class="approval-avatar" style="background:${a.color};width:32px;height:32px;font-size:12px;margin-right:12px;">${a.initials}</div>
              <div style="flex:1;">
                <div style="font-weight:600;font-size:13px;">${a.employee}</div>
                <div style="font-size:11px;color:var(--ink-faint);">${a.week} · ${a.hours}h</div>
              </div>
              <span class="badge badge-amber">Pending</span>
            </div>
          `).join('')}
        </div>
      </div>
    `;
  }
}

function statCard(label, val, sub, type='') {
  const badge = type === 'up' ? `<div class="stat-badge up">↑ On track</div>` :
                type === 'warn' ? `<div class="stat-badge warn">⚠ Needs attention</div>` : '';
  return `<div class="stat-card">
    <div class="stat-label">${label}</div>
    <div class="stat-value">${val}</div>
    <div class="stat-sub">${sub}</div>
    ${badge}
  </div>`;
}

function barRow(label, valLabel, val, pct, color) {
  return `<div class="chart-bar-row">
    <div class="chart-bar-label">${label}</div>
    <div class="chart-bar-track">
      <div class="chart-bar-fill" style="width:${pct}%;background:${color};"><span>${valLabel}</span></div>
    </div>
  </div>`;
}

function statusBadge(s) {
  const m = { approved:'badge-green', pending:'badge-amber', rejected:'badge-red' };
  return `<span class="badge ${m[s]||'badge-gray'}">${s.charAt(0).toUpperCase()+s.slice(1)}</span>`;
}

// ═══════════════════════════════════
//  CLOCK IN/OUT
// ═══════════════════════════════════
function renderClock() {
  renderClockLog();
}

function clockIn() {
  state.clockedIn = true;
  state.clockInTime = new Date();
  updateClockUI();
  toast('Clocked in successfully', 'success');
}

function clockOut() {
  if (!state.clockedIn) return;
  const now = new Date();
  const dur = Math.floor((now - state.clockInTime) / 1000);
  state.clockLog.unshift({
    date: formatDate(now), inTime: formatTime(state.clockInTime),
    outTime: formatTime(now), duration: dur, status: 'complete'
  });
  state.clockedIn = false;
  state.clockInTime = null;
  updateClockUI();
  renderClockLog();
  toast('Clocked out. Have a great day!', 'success');
}

function updateClockUI() {
  const ci = document.getElementById('clock-status-badge');
  const t = document.getElementById('clock-status-text');
  const bci = document.getElementById('btn-ci');
  const bco = document.getElementById('btn-co');
  if (state.clockedIn) {
    ci.className = 'clock-status in';
    t.textContent = 'Clocked In';
    bci.disabled = true; bco.disabled = false;
    bci.style.opacity = '.4';
  } else {
    ci.className = 'clock-status out';
    t.textContent = 'Clocked Out';
    bci.disabled = false; bco.disabled = true;
    bci.style.opacity = '1';
  }
}

function renderClockLog() {
  const tbody = document.getElementById('clock-log-body');
  if (!state.clockLog.length) {
    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:32px;color:var(--ink-faint);">No entries today. Clock in to start tracking.</td></tr>`;
    document.getElementById('today-total-badge').textContent = '0h 0m today';
    return;
  }
  let total = 0;
  tbody.innerHTML = state.clockLog.map((e,i)=>{
    total += e.duration;
    const h = Math.floor(e.duration/3600), m = Math.floor((e.duration%3600)/60), s = e.duration%60;
    return `<tr>
      <td class="mono">${i+1}</td>
      <td>${e.date}</td>
      <td class="mono">${e.inTime}</td>
      <td class="mono">${e.outTime}</td>
      <td class="mono">${h}h ${m}m ${s}s</td>
      <td><span class="badge badge-green">Complete</span></td>
    </tr>`;
  }).join('');
  const th = Math.floor(total/3600), tm = Math.floor((total%3600)/60);
  document.getElementById('today-total-badge').textContent = `${th}h ${tm}m today`;
}

// ═══════════════════════════════════
//  TIMESHEET
// ═══════════════════════════════════
const tsRows = ['Regular Hours','Project A','Project B','Training','Other'];
const days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];

function renderTimesheet() {
  buildTimesheetGrid();
  updateWeekLabel();
}

function getWeekStart(d) {
  const dt = new Date(d);
  const day = dt.getDay();
  const diff = (day === 0 ? -6 : 1 - day);
  dt.setDate(dt.getDate() + diff);
  dt.setHours(0,0,0,0);
  return dt;
}

function updateWeekLabel() {
  const ws = getWeekStart(state.currentWeek);
  const we = new Date(ws); we.setDate(we.getDate()+6);
  const opts = { month:'short', day:'numeric' };
  document.getElementById('week-label').textContent =
    `Week of ${ws.toLocaleDateString('en-US', opts)} – ${we.toLocaleDateString('en-US', opts)}, ${we.getFullYear()}`;
}

function changeWeek(dir) {
  state.currentWeek = new Date(state.currentWeek);
  state.currentWeek.setDate(state.currentWeek.getDate() + dir * 7);
  updateWeekLabel();
  buildTimesheetGrid();
}

function goToCurrentWeek() { state.currentWeek = new Date(); changeWeek(0); }

function buildTimesheetGrid() {
  const grid = document.getElementById('timesheet-grid');
  const ws = getWeekStart(state.currentWeek);
  const weekKey = ws.toISOString().slice(0,10);
  if (!state.timesheetData[weekKey]) {
    state.timesheetData[weekKey] = {};
    tsRows.forEach(r => { state.timesheetData[weekKey][r] = Array(7).fill(''); });
  }

  const dayHeaders = days.map((d,i)=>{
    const dt = new Date(ws); dt.setDate(dt.getDate()+i);
    return `<div class="ts-cell ts-header ${i>=5?'ts-weekend':''}">
      <div>${d}</div>
      <div style="font-size:10px;color:var(--ink-faint);font-weight:400;">${dt.getDate()}/${dt.getMonth()+1}</div>
    </div>`;
  }).join('');

  const rows = tsRows.map(row => {
    const cells = days.map((_,i) => {
      const isWe = i>=5;
      const val = state.timesheetData[weekKey][row][i];
      return `<div class="ts-cell ${isWe?'ts-weekend':''}">
        <input class="ts-input" type="number" min="0" max="24" step="0.5"
          value="${val}" placeholder="-"
          onchange="updateTsCell('${weekKey}','${row}',${i},this.value)"
          oninput="recalcTotals('${weekKey}')">
      </div>`;
    }).join('');
    const rowTotal = (state.timesheetData[weekKey][row] || []).reduce((s,v)=>s+(parseFloat(v)||0),0);
    return `<div class="ts-cell ts-label">${row}</div>${cells}<div class="ts-cell ts-total">${rowTotal||'-'}</div>`;
  }).join('');

  const colTotals = days.map((_,i)=>{
    const t = tsRows.reduce((s,r)=>s+(parseFloat(state.timesheetData[weekKey][r]?.[i])||0),0);
    return `<div class="ts-cell ts-total">${t||'-'}</div>`;
  }).join('');

  const grandTotal = tsRows.reduce((s,r)=>{
    return s + (state.timesheetData[weekKey][r]||[]).reduce((a,v)=>a+(parseFloat(v)||0),0);
  },0);

  document.getElementById('ts-total-label').textContent = `Total: ${grandTotal}h`;

  grid.innerHTML = `
    <div style="display:grid;grid-template-columns:160px repeat(7,1fr) 80px;gap:0;">
      <div class="ts-cell ts-header">Category</div>
      ${dayHeaders}
      <div class="ts-cell ts-header">Total</div>
      ${rows}
      <div class="ts-cell ts-label" style="font-weight:700;">Daily Total</div>
      ${colTotals}
      <div class="ts-cell ts-total" style="background:var(--accent-light);color:var(--accent);">${grandTotal}</div>
    </div>`;
}

function updateTsCell(wk, row, idx, val) {
  if (!state.timesheetData[wk]) return;
  state.timesheetData[wk][row][idx] = val;
  recalcTotals(wk);
}

function recalcTotals(wk) {
  const grandTotal = tsRows.reduce((s,r)=>{
    return s + (state.timesheetData[wk][r]||[]).reduce((a,v)=>a+(parseFloat(v)||0),0);
  },0);
  document.getElementById('ts-total-label').textContent = `Total: ${grandTotal}h`;
}

function saveTimesheet() { toast('Timesheet draft saved', 'success'); }
function submitTimesheet() {
  toast('Timesheet submitted for approval ✓', 'success');
  const ws = getWeekStart(state.currentWeek);
  const label = ws.toLocaleDateString('en-US', {month:'short', day:'numeric'});
  myTimesheets.unshift({ week:`${label}–...`, hours: 40, submitted: 'Today', status:'pending' });
}

// ═══════════════════════════════════
//  APPROVALS
// ═══════════════════════════════════
function renderApprovals() {
  const pending = approvals.filter(a=>a.status==='pending').length;
  document.getElementById('pending-count').textContent = pending;
  renderApprovalList(state.currentApprovalTab);
}

function switchApprovalTab(tab, el) {
  state.currentApprovalTab = tab;
  document.querySelectorAll('#view-approvals .tab').forEach(t=>t.classList.remove('active'));
  el.classList.add('active');
  const labels = { pending:'Pending Approvals', approved:'Approved Timesheets', rejected:'Rejected Timesheets' };
  document.getElementById('approval-list-title').textContent = labels[tab];
  renderApprovalList(tab);
}

function renderApprovalList(tab) {
  const list = document.getElementById('approval-list');
  const items = approvals.filter(a=>a.status===tab);
  if (!items.length) {
    list.innerHTML = `<div class="empty-state"><div class="empty-icon">📋</div><div class="empty-title">No ${tab} timesheets</div><div class="empty-desc">Nothing here right now.</div></div>`;
    return;
  }
  list.innerHTML = items.map(a=>`
    <div class="approval-item">
      <div class="approval-avatar" style="background:${a.color};">${a.initials}</div>
      <div class="approval-info">
        <div class="approval-name">${a.employee}</div>
        <div class="approval-meta">${a.week} &nbsp;·&nbsp; <span class="mono">${a.hours}h</span> &nbsp;·&nbsp; Submitted ${a.submitted}${a.reason?`<span style="color:var(--red);"> &nbsp;·&nbsp; ${a.reason}</span>`:''}</div>
      </div>
      <div class="approval-actions">
        ${tab==='pending' ? `
          <button class="btn btn-success btn-sm" onclick="approveTs(${a.id})">Approve</button>
          <button class="btn btn-danger btn-sm" onclick="openReject(${a.id})">Reject</button>
        ` : `${statusBadge(a.status)}`}
      </div>
    </div>
  `).join('');
}

function approveTs(id) {
  const a = approvals.find(x=>x.id===id);
  if (a) { a.status = 'approved'; renderApprovals(); toast(`${a.employee}'s timesheet approved`, 'success'); }
}

function openReject(id) {
  state.pendingRejectId = id;
  document.getElementById('reject-reason').value = '';
  openModal('modal-reject');
}

function confirmReject() {
  const a = approvals.find(x=>x.id===state.pendingRejectId);
  if (a) {
    a.status = 'rejected';
    a.reason = document.getElementById('reject-reason').value || 'No reason provided.';
    closeModal('modal-reject');
    renderApprovals();
    toast(`${a.employee}'s timesheet rejected`, 'error');
  }
}

// ═══════════════════════════════════
//  REPORTS
// ═══════════════════════════════════
function generateReport(type) {
  const output = document.getElementById('report-output');
  const body = document.getElementById('report-body');
  const title = document.getElementById('report-title');
  output.style.display = 'block';
  output.scrollIntoView({ behavior: 'smooth', block: 'start' });

  const reportDefs = {
    hours: {
      title: 'Hours Summary — June 2025',
      html: () => `
        <table><thead><tr><th>Employee</th><th>Department</th><th>Regular Hrs</th><th>Overtime Hrs</th><th>Total Hrs</th><th>Status</th></tr></thead>
        <tbody>
          <tr><td>John Doe</td><td>Engineering</td><td class="mono">40.0</td><td class="mono text-amber">2.5</td><td class="mono font-bold">42.5</td><td>${statusBadge('approved')}</td></tr>
          <tr><td>Maria Santos</td><td>Marketing</td><td class="mono">38.0</td><td class="mono">0.0</td><td class="mono font-bold">38.0</td><td>${statusBadge('pending')}</td></tr>
          <tr><td>David Okafor</td><td>Finance</td><td class="mono">40.0</td><td class="mono">0.0</td><td class="mono font-bold">40.0</td><td>${statusBadge('approved')}</td></tr>
          <tr><td>Priya Nair</td><td>HR</td><td class="mono">36.5</td><td class="mono">0.0</td><td class="mono font-bold">36.5</td><td>${statusBadge('rejected')}</td></tr>
        </tbody></table>
        <div style="margin-top:16px;display:flex;gap:24px;">
          <div><span class="text-faint">Total Hours:</span> <strong>157h</strong></div>
          <div><span class="text-faint">Overtime:</span> <strong class="text-amber">2.5h</strong></div>
          <div><span class="text-faint">Employees:</span> <strong>4</strong></div>
        </div>`
    },
    attendance: {
      title: 'Attendance Report — June 2025',
      html: () => `
        <table><thead><tr><th>Employee</th><th>Days Present</th><th>Days Absent</th><th>Late Arrivals</th><th>Early Departures</th><th>Attendance %</th></tr></thead>
        <tbody>
          <tr><td>John Doe</td><td class="mono">20</td><td class="mono">1</td><td class="mono">2</td><td class="mono">0</td><td class="mono text-green font-bold">95%</td></tr>
          <tr><td>Maria Santos</td><td class="mono">19</td><td class="mono">2</td><td class="mono">1</td><td class="mono">1</td><td class="mono font-bold">90%</td></tr>
          <tr><td>David Okafor</td><td class="mono">21</td><td class="mono">0</td><td class="mono">0</td><td class="mono">0</td><td class="mono text-green font-bold">100%</td></tr>
          <tr><td>Priya Nair</td><td class="mono">17</td><td class="mono">4</td><td class="mono">3</td><td class="mono">2</td><td class="mono text-red font-bold">81%</td></tr>
        </tbody></table>`
    },
    overtime: {
      title: 'Overtime Report — June 2025',
      html: () => `
        <table><thead><tr><th>Employee</th><th>Dept</th><th>Wk1</th><th>Wk2</th><th>Wk3</th><th>Wk4</th><th>Total OT</th></tr></thead>
        <tbody>
          <tr><td>John Doe</td><td>Engineering</td><td class="mono">2.5</td><td class="mono">0</td><td class="mono">1.5</td><td class="mono">0</td><td class="mono font-bold text-amber">4.0h</td></tr>
          <tr><td>David Okafor</td><td>Finance</td><td class="mono">0</td><td class="mono">3.0</td><td class="mono">0</td><td class="mono">0</td><td class="mono font-bold text-amber">3.0h</td></tr>
          <tr><td>Maria Santos</td><td>Marketing</td><td class="mono">0</td><td class="mono">0</td><td class="mono">0</td><td class="mono">0</td><td class="mono">0h</td></tr>
        </tbody></table>
        <p style="font-size:12px;color:var(--ink-faint);margin-top:12px;">Threshold: 40h/week. Overtime at 1.5× rate.</p>`
    },
    department: {
      title: 'Department Summary — June 2025',
      html: () => `
        <div class="chart-bar-wrap" style="margin-bottom:24px;">
          ${barRow('Engineering','312h',312,100,'#1d4ed8')}
          ${barRow('Marketing','240h',240,77,'#7c3aed')}
          ${barRow('Finance','198h',198,63,'#059669')}
          ${barRow('HR','156h',156,50,'#d97706')}
          ${barRow('Operations','280h',280,90,'#0891b2')}
        </div>
        <table><thead><tr><th>Department</th><th>Headcount</th><th>Total Hours</th><th>Avg Hours/Person</th></tr></thead>
        <tbody>
          <tr><td>Engineering</td><td>4</td><td class="mono">312</td><td class="mono">78</td></tr>
          <tr><td>Operations</td><td>3</td><td class="mono">280</td><td class="mono">93</td></tr>
          <tr><td>Marketing</td><td>3</td><td class="mono">240</td><td class="mono">80</td></tr>
          <tr><td>Finance</td><td>2</td><td class="mono">198</td><td class="mono">99</td></tr>
          <tr><td>HR</td><td>2</td><td class="mono">156</td><td class="mono">78</td></tr>
        </tbody></table>`
    }
  };

  const r = reportDefs[type];
  title.textContent = r.title;
  body.innerHTML = r.html();
  setTimeout(()=>{ body.querySelectorAll('.chart-bar-fill').forEach(b=>{ const w=b.style.width; b.style.width='0'; setTimeout(()=>b.style.width=w,50); }); }, 100);
}

function exportCSV() { toast('Report exported as CSV', 'success'); }
function printReport() { window.print(); }

// ═══════════════════════════════════
//  ADMIN
// ═══════════════════════════════════
function renderAdmin() {
  renderPersonnel();
}

function switchAdminTab(tab, el) {
  state.currentAdminTab = tab;
  document.querySelectorAll('#view-admin .tab').forEach(t=>t.classList.remove('active'));
  el.classList.add('active');
  document.getElementById('admin-personnel-view').style.display = tab==='personnel' ? '' : 'none';
  document.getElementById('admin-settings-view').style.display = tab==='settings' ? '' : 'none';
  if (tab==='personnel') renderPersonnel();
}

function renderPersonnel() {
  const list = document.getElementById('personnel-list');
  list.innerHTML = personnel.map(p=>`
    <div class="personnel-row">
      <div class="personnel-avatar" style="background:${p.color};">${p.initials}</div>
      <div style="flex:1;">
        <div class="personnel-name">${p.name}</div>
        <div class="personnel-dept">${p.dept} · ${p.email}</div>
      </div>
      <span class="badge ${p.role==='Admin'?'badge-purple':p.role==='Manager'?'badge-blue':'badge-gray'}">${p.role}</span>
      <span class="badge ${p.status==='active'?'badge-green':'badge-gray'}" style="margin-left:8px;">${p.status}</span>
      <div class="personnel-actions">
        <button class="btn btn-outline btn-sm" onclick="toast('Edit not implemented in demo', 'error')">Edit</button>
        <button class="btn btn-outline btn-sm" onclick="toggleStatus(${p.id})">${p.status==='active'?'Deactivate':'Activate'}</button>
      </div>
    </div>
  `).join('');
}

function toggleStatus(id) {
  const p = personnel.find(x=>x.id===id);
  if (p) { p.status = p.status==='active'?'inactive':'active'; renderPersonnel(); toast(`${p.name} ${p.status}`, 'success'); }
}

function openAddEmployee() { openModal('modal-add-employee'); }

function addEmployee() {
  const fn = document.getElementById('emp-fname').value.trim();
  const ln = document.getElementById('emp-lname').value.trim();
  if (!fn || !ln) { toast('Please enter a name', 'error'); return; }
  const colors = ['#1d4ed8','#7c3aed','#059669','#d97706','#dc2626','#0891b2'];
  personnel.push({
    id: Date.now(), name:`${fn} ${ln}`,
    initials:(fn[0]+ln[0]).toUpperCase(),
    role: document.getElementById('emp-role').value,
    dept: document.getElementById('emp-dept').value,
    email: document.getElementById('emp-email').value,
    color: colors[Math.floor(Math.random()*colors.length)],
    status: 'active'
  });
  closeModal('modal-add-employee');
  renderPersonnel();
  toast(`${fn} ${ln} added`, 'success');
}

// ═══════════════════════════════════
//  MY TIMESHEETS
// ═══════════════════════════════════
function renderMyTimesheets() {
  const tbody = document.getElementById('my-ts-body');
  tbody.innerHTML = myTimesheets.map(ts=>`
    <tr>
      <td>${ts.week}</td>
      <td class="mono">${ts.hours}h</td>
      <td>${ts.submitted}</td>
      <td>${statusBadge(ts.status)}</td>
      <td>
        ${ts.status==='rejected'?`<span style="font-size:11px;color:var(--red);">Reason: ${ts.reason||'-'}</span>`:
          ts.status==='pending'?`<button class="btn btn-outline btn-sm" onclick="toast('Recall not implemented in demo')">Recall</button>`:'—'}
      </td>
    </tr>
  `).join('');
}

// ═══════════════════════════════════
//  MODAL HELPERS
// ═══════════════════════════════════
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(m=>m.addEventListener('click',e=>{ if(e.target===m) m.classList.remove('open'); }));

// ═══════════════════════════════════
//  TOAST
// ═══════════════════════════════════
function toast(msg, type='') {
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  el.textContent = msg;
  document.getElementById('toast-container').appendChild(el);
  setTimeout(()=>el.remove(), 3000);
}

// ═══════════════════════════════════
//  UTILS
// ═══════════════════════════════════
function formatTime(d) { return d.toLocaleTimeString('en-US', {hour12:false}); }
function formatDate(d) { return d.toLocaleDateString('en-US', {weekday:'short', month:'short', day:'numeric'}); }

// Seed some clock log entries
state.clockLog = [
  { date:'Mon, Jun 9', inTime:'08:01:34', outTime:'17:03:22', duration:32508, status:'complete' },
];