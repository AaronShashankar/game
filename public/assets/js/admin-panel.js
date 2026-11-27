// Admin panel. Access is enforced by the server (the page and every
// /api/admin/* endpoint require an admin session) — nothing here decides it.
const esc = (v) => App.escape(v);
const $ = (id) => document.getElementById(id);
const when = (s) => new Date(String(s).replace(' ', 'T'));
const fmt = (n) => Number(n || 0).toLocaleString();

function showError(message) {
  App.toast(message, 'danger');
}

// ---- Tabs ---------------------------------------------------------------------
const LOADERS = {
  dashboard: loadDashboardData, users: loadUsersTable, subjects: loadSubjects, questions: loadQuestionsTab,
  security: loadSecurityLogs, analytics: loadAnalytics,
};
let currentTab = 'dashboard';

function switchTab(tabName) {
  currentTab = tabName;
  document.querySelectorAll('.tab-content').forEach((t) => t.classList.toggle('active', t.id === tabName + '-tab'));
  document.querySelectorAll('.tab-btn').forEach((b) => b.classList.toggle('is-active', b.dataset.tab === tabName));
  $('tabTitle').textContent = document.querySelector(`.tab-btn[data-tab="${tabName}"] span`).textContent;
  (LOADERS[tabName] || (() => {}))();
}
document.querySelectorAll('.tab-btn').forEach((b) => b.addEventListener('click', () => switchTab(b.dataset.tab)));

// ---- Overview -------------------------------------------------------------------
async function loadDashboardData() {
  try {
    const { stats } = await App.api('/api/admin/stats');
    $('totalUsers').textContent = fmt(stats.totalUsers);
    $('totalGames').textContent = fmt(stats.totalGames);
    $('totalCoins').textContent = fmt(stats.totalCoins);
    $('activeUsers').textContent = fmt(stats.activeUsers);
    $('avgLevel').textContent = stats.avgLevel;
    $('totalXP').textContent = fmt(stats.totalXP);
    $('gatesOpened').textContent = fmt(stats.totalGates);
    $('correctAnswers').textContent = fmt(stats.totalCorrect);
    loadRecentActivity();
  } catch (e) {
    showError('Failed to load dashboard data: ' + e.message);
  }
}

async function loadRecentActivity() {
  const box = $('recentActivity');
  try {
    const { activity } = await App.api('/api/admin/activity');
    box.innerHTML = activity.length ? activity.map((a) => `
      <div class="log-entry">
        <span class="dot ${a.type === 'info' ? 'dot-accent' : 'dot-success'}"></span>
        <div class="grow">${esc(a.message)}</div>
        <time>${esc(when(a.time).toLocaleString())}</time>
      </div>`).join('') : '<div class="empty">No activity yet.</div>';
  } catch (e) {
    box.innerHTML = '<div class="empty">Failed to load recent activity.</div>';
  }
}

// ---- Players ------------------------------------------------------------------
async function loadUsersTable() {
  const body = $('usersTableBody');
  try {
    const { users } = await App.api('/api/admin/users');
    if (!users.length) {
      body.innerHTML = '<tr><td colspan="11" class="empty">No players yet.</td></tr>';
      return;
    }
    body.innerHTML = '';
    users.forEach((u) => {
      const row = document.createElement('tr');
      row.innerHTML = `
        <td><strong style="font-weight:500">${esc(u.username)}</strong></td>
        <td class="num">${u.level}</td><td class="num">${fmt(u.coins)}</td><td class="num">${fmt(u.xp)}</td><td class="num">${u.streak}</td>
        <td class="num">${u.total_games}</td><td class="num">${u.powerups_used}</td><td class="num">${u.correct_answers}</td><td class="num">${u.wrong_answers}</td>
        <td class="subtle">${u.last_active ? esc(when(u.last_active).toLocaleDateString()) : '—'}</td>
        <td style="text-align:right"><button class="btn btn-danger btn-sm">${App.icon('trash', 'icon-sm')}Delete</button></td>`;
      // Username goes through a closure, not an inline onclick string.
      row.querySelector('button').addEventListener('click', () => deleteUser(u.id, u.username));
      body.appendChild(row);
    });
  } catch (e) {
    body.innerHTML = `<tr><td colspan="11" class="empty">${esc(e.message)}</td></tr>`;
  }
}

$('addUserForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  try {
    await App.api('/api/admin/users', {
      body: {
        username: $('newUsername').value.trim(),
        password: $('newUserPassword').value,
        email: $('newEmail').value.trim(),
        coins: Number($('initialCoins').value || 0),
        level: Number($('initialLevel').value || 1),
      },
    });
    App.toast('Player added', 'success');
    closeAddUserModal();
    refreshData();
  } catch (err) {
    showError(err.message);
  }
});

async function deleteUser(userId, username) {
  if (!confirm(`Delete player "${username}" and all their progress? This cannot be undone.`)) return;
  try {
    await App.api('/api/admin/users/delete', { body: { user_id: userId } });
    App.toast(`Deleted ${username}`, 'success');
    loadUsersTable();
  } catch (e) {
    showError(e.message);
  }
}

// ---- Subjects -----------------------------------------------------------------
// [{id, name, description, icon, visible, total, active_count, easy, medium, hard}]
let subjectList = [];
let minSubjectQuestions = 7;

async function fetchSubjects() {
  const r = await App.api('/api/admin/subjects');
  subjectList = r.subjects;
  minSubjectQuestions = r.min_questions;
  fillSubjectSelects();
  return subjectList;
}

// The question filter and the question form both list every subject.
function fillSubjectSelects() {
  const filter = $('qSubject');
  const current = filter.value;
  filter.innerHTML = '<option value="">All subjects</option>'
    + subjectList.map((s) => `<option value="${esc(s.name)}">${esc(s.name)}</option>`).join('');
  filter.value = subjectList.some((s) => s.name === current) ? current : '';
  $('qFormSubject').innerHTML = subjectList
    .map((s) => `<option value="${esc(s.name)}">${esc(s.name)}${s.visible ? '' : ' (hidden)'}</option>`).join('');
}

function subjectStatus(s) {
  const n = s.active_count;
  if (s.visible) {
    if (n === 0) return '<span class="chip chip-warning">Visible · no active questions</span>';
    if (n < minSubjectQuestions) return `<span class="chip chip-warning">Visible · only ${n}</span>`;
    return '<span class="chip chip-success">Visible</span>';
  }
  const short = minSubjectQuestions - n;
  return '<span class="chip">Hidden</span>'
    + `<div class="subtle" style="font-size:12px; margin-top:4px">${short > 0 ? `Needs ${short} more question${short === 1 ? '' : 's'}` : 'Ready to show'}</div>`;
}

async function loadSubjects() {
  const body = $('subjectRows');
  try {
    await fetchSubjects();
    if (!subjectList.length) {
      body.innerHTML = '<tr><td colspan="5" class="empty">No subjects yet. Add one to start writing questions.</td></tr>';
      return;
    }
    body.innerHTML = '';
    for (const s of subjectList) {
      const archived = s.total - s.active_count;
      const row = document.createElement('tr');
      row.innerHTML = `
        <td class="wrap"><div class="subject-cell"><span class="icon-box">${App.icon(s.icon)}</span>
          <div><div style="font-weight:500">${esc(s.name)}</div>
          <div class="subtle" style="font-size:13px">${s.description ? esc(s.description) : 'No description'}</div></div></div></td>
        <td class="num"><button class="link link-btn" data-act="view" style="margin:0" title="Show these questions">${fmt(s.active_count)}</button>
          ${archived ? `<div class="subtle" style="font-size:12px">+${fmt(archived)} archived</div>` : ''}</td>
        <td class="subtle" style="font-size:13px">Easy ${s.easy} · Medium ${s.medium} · Hard ${s.hard}</td>
        <td>${subjectStatus(s)}</td>
        <td style="text-align:right; white-space:nowrap">
          <button class="btn btn-ghost btn-sm" data-act="add">${App.icon('plus', 'icon-sm')}Add question</button>
          <button class="btn btn-ghost btn-sm" data-act="visibility">${App.icon(s.visible ? 'eye-off' : 'eye', 'icon-sm')}${s.visible ? 'Hide' : 'Show'}</button>
          <button class="btn btn-ghost btn-sm" data-act="edit">${App.icon('pencil', 'icon-sm')}Edit</button>
          ${s.total === 0 ? `<button class="btn btn-ghost btn-sm" data-act="delete">${App.icon('trash', 'icon-sm')}Delete</button>` : ''}
        </td>`;
      row.querySelector('[data-act=view]').addEventListener('click', () => showSubjectQuestions(s.name));
      row.querySelector('[data-act=add]').addEventListener('click', () => openQuestionModal(null, s.name));
      row.querySelector('[data-act=visibility]').addEventListener('click', () => setSubjectVisible(s, !s.visible));
      row.querySelector('[data-act=edit]').addEventListener('click', () => openSubjectModal(s.id));
      row.querySelector('[data-act=delete]')?.addEventListener('click', () => deleteSubject(s));
      body.appendChild(row);
    }
  } catch (e) {
    body.innerHTML = `<tr><td colspan="5" class="empty">${esc(e.message)}</td></tr>`;
  }
}

function showSubjectQuestions(name) {
  $('qSubject').value = name;
  $('qSearch').value = '';
  switchTab('questions');
}

function openSubjectModal(id = null) {
  const s = id ? subjectList.find((x) => x.id === id) : null;
  $('subjectModalTitle').textContent = s ? 'Edit subject' : 'Add subject';
  $('subjectModalSub').textContent = s
    ? 'Renaming also renames it on its questions and past games.'
    : `It starts hidden. Add at least ${minSubjectQuestions} questions, then show it to players.`;
  $('sId').value = s ? s.id : '';
  $('sName').value = s ? s.name : '';
  $('sDescription').value = s ? (s.description ?? '') : '';
  const icon = document.querySelector(`input[name=sIcon][value="${s ? s.icon : 'book'}"]`) || document.querySelector('input[name=sIcon]');
  icon.checked = true;
  $('subjectModal').classList.add('show');
  $('sName').focus();
}

function closeSubjectModal() {
  $('subjectModal').classList.remove('show');
}

$('subjectForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn = e.target.querySelector('[type=submit]');
  btn.disabled = true;
  try {
    const id = $('sId').value;
    const { subject } = await App.api('/api/admin/subjects', {
      body: {
        id: id || null,
        name: $('sName').value.trim(),
        description: $('sDescription').value.trim(),
        icon: document.querySelector('input[name=sIcon]:checked').value,
      },
    });
    App.toast(id ? 'Subject updated' : `${subject.name} added. Use Add question to write its questions.`, 'success');
    closeSubjectModal();
    loadSubjects();
  } catch (err) {
    showError(err.message);
  } finally {
    btn.disabled = false;
  }
});

async function setSubjectVisible(s, visible) {
  try {
    await App.api('/api/admin/subjects/visibility', { body: { id: s.id, visible } });
    App.toast(visible ? `${s.name} is now on the players' subject list` : `${s.name} is hidden from players`, 'success');
    loadSubjects();
  } catch (e) {
    showError(e.message);
  }
}

async function deleteSubject(s) {
  if (!confirm(`Delete the subject "${s.name}"? It has no questions.`)) return;
  try {
    await App.api('/api/admin/subjects/delete', { body: { id: s.id } });
    App.toast(`Deleted ${s.name}`, 'success');
    loadSubjects();
  } catch (e) {
    showError(e.message);
  }
}

// ---- Question bank ------------------------------------------------------------
let questionCache = new Map();
let searchTimer = null;
const selectedQuestions = new Set();   // ids ticked in the current list

function loadQuestionsTab() {
  fetchSubjects().catch((e) => showError(e.message));
  loadQuestions();
}

async function loadQuestions() {
  const body = $('questionRows');
  const params = new URLSearchParams({
    q: $('qSearch').value.trim(), subject: $('qSubject').value, difficulty: $('qDifficulty').value, status: $('qStatus').value,
  });
  try {
    const { questions } = await App.api('/api/admin/questions?' + params);
    questionCache = new Map(questions.map((q) => [q.id, q]));
    // Keep only ticks that are still on screen, so a delete never hits hidden rows.
    for (const id of [...selectedQuestions]) if (!questionCache.has(id)) selectedQuestions.delete(id);
    $('qCount').textContent = `${questions.length} question${questions.length === 1 ? '' : 's'}${questions.length >= 500 ? ' (first 500, narrow the filters)' : ''}`;
    if (!questions.length) {
      body.innerHTML = '<tr><td colspan="7" class="empty">No questions match these filters.</td></tr>';
      updateQuestionSelection();
      return;
    }
    body.innerHTML = '';
    for (const q of questions) {
      const row = document.createElement('tr');
      row.dataset.id = q.id;
      if (!q.active) row.classList.add('is-archived');
      const answer = q.options[q.answer_idx];
      row.innerHTML = `
        <td class="check-cell"><input type="checkbox" data-act="select" aria-label="Select this question"></td>
        <td class="wrap"><div style="font-weight:500">${esc(q.prompt)}</div>
          <div class="subtle" style="font-size:13px">Answer: ${esc(answer)}${q.explanation ? '' : ' · <span style="color:var(--warning)">no explanation</span>'}</div></td>
        <td>${esc(q.subject)}</td>
        <td><span class="chip">${esc(q.difficulty)}</span>${q.active ? '' : ' <span class="chip">Archived</span>'}</td>
        <td class="num">${fmt(q.times_answered)}</td>
        <td class="num">${q.pct_correct === null ? '—' : q.pct_correct + '%'}</td>
        <td style="text-align:right; white-space:nowrap">
          <button class="btn btn-ghost btn-sm" data-act="edit">${App.icon('pencil', 'icon-sm')}Edit</button>
          <button class="btn btn-ghost btn-sm" data-act="toggle">${App.icon(q.active ? 'archive' : 'refresh', 'icon-sm')}${q.active ? 'Archive' : 'Restore'}</button>
          <button class="btn btn-ghost btn-sm" data-act="delete" aria-label="Delete question">${App.icon('trash', 'icon-sm')}Delete</button>
        </td>`;
      const box = row.querySelector('[data-act=select]');
      box.checked = selectedQuestions.has(q.id);
      box.addEventListener('change', () => {
        box.checked ? selectedQuestions.add(q.id) : selectedQuestions.delete(q.id);
        updateQuestionSelection();
      });
      row.querySelector('[data-act=edit]').addEventListener('click', () => openQuestionModal(q.id));
      row.querySelector('[data-act=toggle]').addEventListener('click', () => setQuestionActive(q, !q.active));
      row.querySelector('[data-act=delete]').addEventListener('click', () => deleteQuestions([q.id]));
      body.appendChild(row);
    }
    updateQuestionSelection();
  } catch (e) {
    body.innerHTML = `<tr><td colspan="7" class="empty">${esc(e.message)}</td></tr>`;
    selectedQuestions.clear();
    updateQuestionSelection();
  }
}

// Syncs the row highlights, the select-all box and the bulk bar with the selection.
function updateQuestionSelection() {
  const n = selectedQuestions.size;
  const shown = questionCache.size;
  document.querySelectorAll('#questionRows tr[data-id]').forEach((row) => {
    row.classList.toggle('is-selected', selectedQuestions.has(Number(row.dataset.id)));
  });
  const all = $('qSelectAll');
  all.checked = shown > 0 && n === shown;
  all.indeterminate = n > 0 && n < shown;
  all.disabled = shown === 0;
  $('qBulkBar').hidden = n === 0;
  $('qSelectedCount').textContent = `${n} question${n === 1 ? '' : 's'} selected`;
}

function clearQuestionSelection() {
  selectedQuestions.clear();
  document.querySelectorAll('#questionRows [data-act=select]').forEach((b) => { b.checked = false; });
  updateQuestionSelection();
}

$('qSelectAll').addEventListener('change', (e) => {
  if (e.target.checked) questionCache.forEach((_, id) => selectedQuestions.add(id));
  else selectedQuestions.clear();
  document.querySelectorAll('#questionRows [data-act=select]').forEach((b) => { b.checked = e.target.checked; });
  updateQuestionSelection();
});

function deleteSelectedQuestions() {
  deleteQuestions([...selectedQuestions]);
}

async function deleteQuestions(ids) {
  if (!ids.length) return;
  const qs = ids.map((id) => questionCache.get(id)).filter(Boolean);
  const answered = qs.filter((q) => q.times_answered > 0).length;
  const what = ids.length === 1 ? `the question "${qs[0]?.prompt ?? ''}"` : `${ids.length} questions`;
  const history = answered
    ? `\n\n${answered === ids.length && ids.length === 1 ? 'It has' : `${answered} of them ${answered === 1 ? 'has' : 'have'}`} been answered by players. `
      + 'Those answers will disappear from past game summaries. Archive instead to keep the history.'
    : '';
  if (!confirm(`Delete ${what}? This cannot be undone.${history}`)) return;
  const bar = $('qBulkBar');
  bar.querySelectorAll('button').forEach((b) => { b.disabled = true; });
  try {
    const { deleted } = await App.api('/api/admin/questions/delete', { body: { ids } });
    ids.forEach((id) => selectedQuestions.delete(id));
    App.toast(`Deleted ${deleted} question${deleted === 1 ? '' : 's'}`, 'success');
    loadQuestions();
  } catch (e) {
    showError(e.message);
  } finally {
    bar.querySelectorAll('button').forEach((b) => { b.disabled = false; });
  }
}

['qSubject', 'qDifficulty', 'qStatus'].forEach((id) => $(id).addEventListener('change', loadQuestions));
$('qSearch').addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(loadQuestions, 250); });

// `subject` preselects the subject for a new question (from the Subjects tab).
function openQuestionModal(id = null, subject = null) {
  if (!subjectList.length) {
    showError('Add a subject first.');
    switchTab('subjects');
    return;
  }
  const q = id ? questionCache.get(id) : null;
  $('questionModalTitle').textContent = q ? 'Edit question' : `Add question${subject ? ' to ' + subject : ''}`;
  $('qId').value = q ? q.id : '';
  $('qFormSubject').value = q ? q.subject : (subject || $('qSubject').value || subjectList[0].name);
  $('qFormDifficulty').value = q ? q.difficulty : 'Medium';
  $('qSaveAnother').hidden = Boolean(q);
  clearQuestionFields(q);
  $('qFormActive').checked = q ? q.active : true;
  $('questionModal').classList.add('show');
  (q || subject ? $('qFormPrompt') : $('qFormSubject')).focus();
}

function clearQuestionFields(q = null) {
  $('qFormPrompt').value = q ? q.prompt : '';
  for (let i = 0; i < 4; i++) $('qOpt' + i).value = q ? (q.options[i] ?? '') : '';
  document.querySelector(`input[name=qCorrect][value="${q ? q.answer_idx : 0}"]`).checked = true;
  $('qFormExplanation').value = q ? (q.explanation ?? '') : '';
}

function closeQuestionModal() {
  $('questionModal').classList.remove('show');
}

$('questionForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const another = Boolean(e.submitter?.dataset.another);
  const buttons = e.target.querySelectorAll('[type=submit]');
  buttons.forEach((b) => { b.disabled = true; });
  try {
    const id = $('qId').value;
    const subject = $('qFormSubject').value;
    await App.api('/api/admin/questions', {
      body: {
        id: id || null,
        subject,
        difficulty: $('qFormDifficulty').value,
        prompt: $('qFormPrompt').value.trim(),
        options: [0, 1, 2, 3].map((i) => $('qOpt' + i).value),
        answer_idx: Number(document.querySelector('input[name=qCorrect]:checked').value),
        explanation: $('qFormExplanation').value.trim(),
        active: $('qFormActive').checked,
      },
    });
    const hidden = subjectList.find((s) => s.name === subject)?.visible === false;
    App.toast((id ? 'Question updated' : 'Question added') + (hidden && !id ? `. ${subject} is still hidden from players.` : ''), 'success');
    if (another) {
      // Keep the subject and difficulty, clear the rest for the next question.
      clearQuestionFields();
      $('qFormPrompt').focus();
    } else {
      closeQuestionModal();
    }
    (currentTab === 'subjects' ? loadSubjects : loadQuestions)();
  } catch (err) {
    showError(err.message);
  } finally {
    buttons.forEach((b) => { b.disabled = false; });
  }
});

async function setQuestionActive(q, active) {
  try {
    await App.api('/api/admin/questions/status', { body: { id: q.id, active } });
    App.toast(active ? 'Question restored' : 'Question archived. It will no longer be asked.', 'success');
    loadQuestions();
  } catch (e) {
    showError(e.message);
  }
}

function openImportModal() {
  $('importErrors').hidden = true;
  $('csvFile').value = '';
  $('importModal').classList.add('show');
}

function closeImportModal() {
  $('importModal').classList.remove('show');
}

async function importCsv() {
  const file = $('csvFile').files[0];
  const errors = $('importErrors');
  errors.hidden = true;
  if (!file) return showError('Choose a CSV file first.');
  if (file.size > 500_000) return showError('That file is over 500 KB. Split it into smaller files.');
  const btn = $('importBtn');
  btn.disabled = true;
  try {
    const r = await App.api('/api/admin/questions/import', { body: { csv: await file.text() } });
    App.toast(`Imported ${r.added} question${r.added === 1 ? '' : 's'}${r.skipped_duplicates ? ` · ${r.skipped_duplicates} already in the bank` : ''}`, 'success');
    closeImportModal();
    loadQuestions();
  } catch (e) {
    errors.textContent = e.message;
    errors.hidden = false;
  } finally {
    btn.disabled = false;
  }
}

function downloadTemplate() {
  const sample = [subjectList[0]?.name || 'Science', 'Easy', 'What is the capital of Peru?', 'Lima', 'Quito', 'Bogotá', '', '1', 'Lima sits on the Pacific coast.'];
  const csv = window.CsvColumns.join(',') + '\n' + sample.map((c) => (/[",\n]/.test(c) ? `"${c.replace(/"/g, '""')}"` : c)).join(',') + '\n';
  const link = document.createElement('a');
  link.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
  link.download = 'mazequest-questions-template.csv';
  link.click();
}

// ---- Security -----------------------------------------------------------------
function checkPasswordStrength() {
  const pw = $('newAdminPassword').value;
  let strength = 0;
  if (pw.length >= 8) strength++;
  if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) strength++;
  if (/\d/.test(pw)) strength++;
  if (/[^a-zA-Z\d]/.test(pw)) strength++;
  $('passwordStrength').className = 'password-strength ' +
    (pw ? ['strength-weak', 'strength-weak', 'strength-medium', 'strength-strong', 'strength-very-strong'][strength] : '');
}

async function changeAdminPassword() {
  const current = $('currentPassword').value;
  const next = $('newAdminPassword').value;
  if (next !== $('confirmPassword').value) return showError('New passwords do not match.');
  if (next.length < 8) return showError('Password must be at least 8 characters long.');
  try {
    await App.api('/api/admin/password', { body: { current_password: current, new_password: next } });
    App.toast('Admin password updated', 'success');
    ['currentPassword', 'newAdminPassword', 'confirmPassword'].forEach((id) => ($(id).value = ''));
    checkPasswordStrength();
  } catch (e) {
    showError(e.message);
  }
}

async function loadSecurityLogs() {
  const box = $('securityLogs');
  try {
    const { logs } = await App.api('/api/admin/security-logs');
    box.innerHTML = logs.length ? logs.map((l) => {
      const dot = /failed|rejected/.test(l.event) ? 'dot-danger' : /success/.test(l.event) ? 'dot-success' : '';
      return `
        <div class="log-entry">
          <span class="dot ${dot}"></span>
          <div class="grow">
            <div><strong style="font-weight:500">${esc(l.event)}</strong> <span class="muted">· ${esc(l.user || 'unknown')}</span></div>
            <div class="subtle" style="font-size:13px">${[l.ip, l.detail].filter(Boolean).map(esc).join(' · ')}</div>
          </div>
          <time>${esc(when(l.timestamp).toLocaleString())}</time>
        </div>`;
    }).join('') : '<div class="empty">No security events logged yet.</div>';
  } catch (e) {
    box.innerHTML = '<div class="empty">Failed to load security logs.</div>';
  }
}

// ---- Analytics (computed from real runs/answers) -------------------------------
async function loadAnalytics() {
  try {
    const { analytics: a } = await App.api('/api/admin/analytics');
    $('avgGameDuration').textContent = a.avgGameDuration;
    $('successRate').textContent = a.successRate;
    $('popularSubject').textContent = a.popularSubject;
    $('dailyActiveUsers').textContent = a.dailyActiveUsers;
    $('retentionRate').textContent = a.completionRate;
    const q = a.hardestQuestions[0];
    $('avgSessionTime').textContent = q ? `${q.prompt} — ${q.pct}% correct` : 'Not enough answers yet';
  } catch (e) {
    showError('Failed to load analytics: ' + e.message);
  }
}

// ---- Data ---------------------------------------------------------------------
function refreshData() {
  (LOADERS[currentTab] || loadDashboardData)();
}

async function exportData() {
  try {
    const { data } = await App.api('/api/admin/export');
    const link = document.createElement('a');
    link.href = URL.createObjectURL(new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' }));
    link.download = `mazequest-export-${new Date().toISOString().slice(0, 10)}.json`;
    link.click();
  } catch (e) {
    showError('Export failed: ' + e.message);
  }
}

// ---- Modal --------------------------------------------------------------------
function openAddUserModal() {
  $('addUserModal').classList.add('show');
  $('newUsername').focus();
}

function closeAddUserModal() {
  $('addUserModal').classList.remove('show');
  $('addUserForm').reset();
}

$('addUserModal').addEventListener('click', (e) => { if (e.target.id === 'addUserModal') closeAddUserModal(); });
$('questionModal').addEventListener('click', (e) => { if (e.target.id === 'questionModal') closeQuestionModal(); });
$('subjectModal').addEventListener('click', (e) => { if (e.target.id === 'subjectModal') closeSubjectModal(); });
$('importModal').addEventListener('click', (e) => { if (e.target.id === 'importModal') closeImportModal(); });
document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  closeAddUserModal();
  closeQuestionModal();
  closeSubjectModal();
  closeImportModal();
});

loadDashboardData();
