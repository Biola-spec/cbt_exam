const appState = {
  session: null,
  dashboard: null,
  syncStatus: null,
  attemptData: null,
  questionIndex: 0,
  message: '',
  timerInterval: null
};

const root = document.getElementById('app');

init();

async function init() {
  appState.session = await window.cbt.auth.getSession();
  appState.syncStatus = await window.cbt.sync.status();

  if (!appState.session) {
    renderLogin();
    return;
  }

  await loadDashboard();
}

function renderLogin(error = '') {
  root.innerHTML = `
    <nav class="navbar navbar-login">
      <div class="container navbar-inner">
        <div class="navbar-brand"><span class="fa-lite">^</span> CBT Exam System</div>
        <div class="navbar-nav">
          <button class="nav-link-button" id="loginSettingsBtn" type="button"><span class="fa-lite">*</span> Settings & Sync</button>
        </div>
      </div>
    </nav>
    <main class="container login-page-main">
      <div class="login-row">
        <section class="card glass-card fade-in login-card">
          <div class="card-header text-center bg-primary text-white border-0 py-4 login-card-header">
            <h1 class="mb-0 fw-bold header-title"><span class="fa-lite">^</span> CBT Exam System</h1>
            <p class="mb-0 mt-2 text-white-50">Sign in to access your account</p>
          </div>
          <div class="card-body">
            ${error ? `<div class="alert alert-danger"><span class="fa-lite me-2">!</span>${escapeHtml(error)}</div>` : ''}
            <ul class="nav nav-tabs" role="tablist">
              <li class="nav-item" role="presentation">
                <button class="nav-link active" type="button" role="tab"><span class="fa-lite me-1">^</span> Student Login</button>
              </li>
            </ul>
            <div class="tab-content mt-4">
              <div class="tab-pane show active" role="tabpanel">
                <form id="loginForm">
                  <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <div class="input-group">
                      <span class="input-group-text">@</span>
                      <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email" autocomplete="off" required>
                    </div>
                  </div>
                  <div class="mb-3">
                    <label for="subjectCode" class="form-label">Subject Code</label>
                    <div class="input-group">
                      <span class="input-group-text">#</span>
                      <input type="text" class="form-control" id="subjectCode" name="subjectCode" placeholder="Enter subject code" autocomplete="off" required>
                    </div>
                    <div class="form-text">Enter the unique code for the subject you want to access exams for.</div>
                  </div>
                  <div class="d-grid">
                    <button type="submit" class="btn btn-success btn-lg"><span class="fa-lite me-1">&gt;</span> Sign In as Student</button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </section>
      </div>
    </main>
    <footer>
      <div class="container text-center">
        <p class="mb-0">&copy; 2026 CBT Exam System. All rights reserved.</p>
        <p class="mb-0 text-muted"><small>Offline Desktop Client</small></p>
      </div>
    </footer>
  `;

  document.getElementById('loginForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const result = await window.cbt.auth.login({
      email: form.get('email'),
      subjectCode: form.get('subjectCode')
    });

    if (!result.ok) {
      renderLogin(result.message);
      return;
    }

    appState.session = result.session;
    await loadDashboard();
  });

  document.getElementById('loginSettingsBtn').addEventListener('click', renderSettings);
}

async function loadDashboard() {
  appState.dashboard = await window.cbt.dashboard.get();
  appState.syncStatus = await window.cbt.sync.status();
  appState.attemptData = null;
  appState.questionIndex = 0;
  if (appState.timerInterval) {
    clearInterval(appState.timerInterval);
    appState.timerInterval = null;
  }
  renderDashboard();
}

function renderShell(content) {
  const session = appState.session || {};
  root.innerHTML = `
    <nav class="navbar">
      <div class="container navbar-inner">
        <div class="navbar-brand">CBT Exam System</div>
        <div class="navbar-nav">
          <span class="navbar-user">${escapeHtml(session.fullName || '')}</span>
          <button class="nav-link-button" id="settingsBtn" type="button">Settings & Sync</button>
          <button class="nav-link-button text-danger" id="logoutBtn" type="button">Logout</button>
        </div>
      </div>
    </nav>
    ${content}
    <footer>
      <div class="container text-center">
        <p class="mb-0">&copy; 2026 CBT Exam System. All rights reserved.</p>
        <p class="mb-0 text-muted"><small>Offline Desktop Client</small></p>
      </div>
    </footer>
  `;

  document.getElementById('logoutBtn').addEventListener('click', async () => {
    await window.cbt.auth.logout();
    appState.session = null;
    renderLogin();
  });

  document.getElementById('settingsBtn').addEventListener('click', renderSettings);
}

function renderDashboard() {
  const data = appState.dashboard || { exams: [], attempts: [], pendingSync: 0 };

  renderShell(`
    ${renderDashboardHeader('Student Dashboard', `Welcome, ${escapeHtml(appState.session.fullName || '')}`, 'Available Exams')}
    <main class="container mt-4">
      ${appState.message ? `<div class="alert alert-info">${escapeHtml(appState.message)}</div>` : ''}
      <div class="dashboard-row">
        <aside class="sidebar-col">
          <div class="card shadow-sm">
            <div class="card-header">
              <h5 class="mb-0">Navigation</h5>
            </div>
            <div class="list-group">
              <button class="list-group-item active" type="button">Available Exams</button>
              <button class="list-group-item text-danger" id="sidebarLogoutBtn" type="button">Logout</button>
            </div>
          </div>
        </aside>

        <section class="content-col">
          <div class="card shadow-sm fade-in">
            <div class="card-header">
              <h4 class="mb-0">Available Exams</h4>
            </div>
            <div class="card-body">
              ${renderExamList(data.exams)}
              <hr>
              <h5>Exam History</h5>
              ${renderHistory(data.attempts)}
            </div>
          </div>
        </section>
      </div>
    </main>
  `);

  const sidebarLogout = document.getElementById('sidebarLogoutBtn');
  if (sidebarLogout) {
    sidebarLogout.addEventListener('click', async () => {
      await window.cbt.auth.logout();
      appState.session = null;
      renderLogin();
    });
  }

  bindExamButtons();
}

function renderExamList(exams) {
  if (!exams || exams.length === 0) {
    return '<div class="alert alert-info">No exams available.</div>';
  }

  return `
    <div class="exam-card-grid">
      ${exams.map((exam) => `
        <article class="card exam-card ${exam.status === 'completed' ? 'bg-light' : ''}">
          <div class="card-body">
            <div class="exam-card-title-row">
              <h5 class="card-title mb-0">${escapeHtml(exam.title)}</h5>
              ${statusBadge(exam.status)}
            </div>
            <p class="card-text mb-1">${escapeHtml(exam.class_name)} - ${escapeHtml(exam.subject_name)}</p>
            <p class="card-text mb-1"><small class="text-muted">${Number(exam.time_limit)} minutes</small></p>
            <div class="mt-3">
              ${exam.status === 'completed'
                ? `<button class="btn btn-outline-success w-100" data-open-attempt="${escapeHtml(exam.attempt.id)}">View Result</button>`
                : exam.status === 'in_progress'
                  ? `<button class="btn btn-warning w-100" data-open-attempt="${escapeHtml(exam.attempt.id)}">Continue Exam</button>`
                  : `<button class="btn btn-primary w-100" data-start-exam="${escapeHtml(exam.exam_subject_id)}">Start Exam</button>`
              }
            </div>
          </div>
        </article>
      `).join('')}
    </div>
  `;
}

function renderHistory(attempts) {
  if (!attempts || attempts.length === 0) {
    return '<div class="alert alert-info">No submitted exams.</div>';
  }

  return `
    <div class="history-list">
      ${attempts.map((attempt) => `
        <div class="history-row">
          <div>
            <strong>${escapeHtml(attempt.title)}</strong>
            <div class="exam-meta">${escapeHtml(attempt.subject_name)} &middot; ${formatDate(attempt.submitted_at)}</div>
          </div>
          <div class="stat-value">${Number(attempt.score || 0).toFixed(1)} / ${attempt.total_questions}</div>
        </div>
      `).join('')}
    </div>
  `;
}

function renderExam() {
  const data = appState.attemptData;
  const attempt = data.attempt;
  const questions = data.questions;
  const current = questions[appState.questionIndex];
  const currentAnswer = data.answers[current.id]?.selected_answer || '';
  const optionKeys = ['A', 'B', 'C', 'D', 'E', 'F'];

  const passages = data.passages || [];
  let currentPassage = null;
  if (passages.length > 0 && current) {
    const origNum = current.original_number;
    for (const passage of passages) {
      if (origNum >= passage.start_question_number && origNum <= passage.end_question_number) {
        currentPassage = passage;
        break;
      }
    }
  }

  let unansweredCount = 0;
  questions.forEach((q) => {
    if (!data.answers[q.id]) {
      unansweredCount++;
    }
  });

  // Setup timer ticking
  if (appState.timerInterval) {
    clearInterval(appState.timerInterval);
    appState.timerInterval = null;
  }

  if (!attempt.submitted_at && data.timeRemainingSeconds > 0) {
    let secondsLeft = data.timeRemainingSeconds;
    appState.timerInterval = setInterval(async () => {
      secondsLeft--;
      if (secondsLeft <= 0) {
        clearInterval(appState.timerInterval);
        appState.timerInterval = null;
        appState.attemptData = await window.cbt.exam.submit(attempt.id);
        renderExam();
      } else {
        const timerSpan = document.getElementById('timeRemaining');
        if (timerSpan) {
          timerSpan.textContent = 'Time: ' + formatTimeSeconds(secondsLeft);
        }
      }
    }, 1000);
  }

  // Bypass shell to display the exact take_exam layout
  root.innerHTML = `
    <div class="exam-header" style="background-color: #fff; padding: 15px 0; border-bottom: 1px solid #dee2e6; margin-bottom: 30px;">
      <div class="container d-flex justify-content-between align-items-center">
        <div>
          <h4 class="mb-0" style="margin: 0; font-size: 1.5rem; font-weight: 700;">${escapeHtml(attempt.title)}</h4>
          <p class="text-muted mb-0" style="margin: 3px 0 0; font-size: 0.9rem;">${escapeHtml(attempt.subject_name)} | Question ${appState.questionIndex + 1} of ${questions.length}</p>
        </div>
        <div id="timerDisplay" class="timer-box" style="font-size: 24px; font-weight: bold; color: #d9534f;">
          <span id="timeRemaining">Time: ${formatTimeSeconds(attempt.submitted_at ? 0 : data.timeRemainingSeconds)}</span>
        </div>
      </div>
    </div>

    <div class="container mb-5">
      <div class="row exam-layout" style="display: grid; grid-template-columns: 280px minmax(0, 1fr); gap: 1.5rem; align-items: start;">
        <!-- Left Sidebar -->
        <aside class="sidebar-col">
          <div class="card mb-4" style="background: #fff; border: 1px solid var(--line); border-radius: 4px; box-shadow: var(--shadow-sm);">
            <div class="card-header bg-primary text-white" style="background-color: var(--primary) !important; color: #fff !important; padding: 12px 20px; font-weight: 700;">
              <h5 class="mb-0" style="margin: 0; font-size: 1.1rem;">Candidate Info</h5>
            </div>
            <div class="card-body text-center" style="padding: 20px; text-align: center;">
              <svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" fill="#6c757d" class="img-thumbnail rounded-circle mb-3" viewBox="0 0 16 16" style="width: 80px; height: 80px; padding: 4px; border: 1px solid #dee2e6; border-radius: 50%; background-color: #fff; margin-bottom: 1rem;">
                <path d="M11 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/>
                <path fill-rule="evenodd" d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8zm8-7a7 7 0 0 0-5.468 11.37C3.242 11.226 4.805 10 8 10s4.757 1.225 5.468 2.37A7 7 0 0 0 8 1z"/>
              </svg>
              <h6 style="margin: 0 0 1rem; font-size: 1rem; font-weight: 600;">${escapeHtml(appState.session.fullName || 'Candidate')}</h6>
              <hr style="margin: 1rem 0; border: 0; border-top: 1px solid var(--line);">
              <div class="question-nav" style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 6px; margin-bottom: 1rem;">
                ${questions.map((q, index) => {
                  const isAnswered = !!data.answers[q.id];
                  const isCurrent = index === appState.questionIndex;
                  let btnClass = 'btn-outline-secondary';
                  if (isCurrent) btnClass = 'btn-primary current';
                  else if (isAnswered) btnClass = 'btn-success answered';
                  return `
                    <button type="button" 
                            class="btn btn-sm ${btnClass} q-nav-btn"
                            style="aspect-ratio: 1; border-radius: 4px; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; min-height: unset; padding: 4px;"
                            data-question-index="${index}">
                      ${index + 1}
                    </button>
                  `;
                }).join('')}
              </div>
              <hr style="margin: 1rem 0; border: 0; border-top: 1px solid var(--line);">
              <button type="button" class="btn btn-outline-secondary w-100 mb-2" id="sidebarDashboardBtn" style="width: 100%; margin-bottom: 8px;">Dashboard</button>
              ${attempt.submitted_at 
                ? '' 
                : `<button type="button" class="btn btn-danger w-100" id="sidebarSubmitExamBtn" style="width: 100%; background-color: var(--danger); border-color: var(--danger); color: #fff;">Submit Exam</button>`
              }
            </div>
          </div>
        </aside>

        <!-- Main Content Area -->
        <section class="content-col" style="display: grid; gap: 20px;">
          ${currentPassage ? `
            <div class="card mb-4" style="background: #fff; border: 1px solid var(--line); border-radius: 4px; box-shadow: var(--shadow-sm);">
              <div class="card-header bg-info text-white" style="background-color: var(--info) !important; color: #fff !important; padding: 12px 20px; font-weight: 700;">
                <h5 class="mb-0" style="margin: 0; font-size: 1.1rem;">${escapeHtml(currentPassage.passage_title || 'Comprehension Passage')}</h5>
              </div>
              <div class="card-body" style="padding: 20px; max-height: 300px; overflow-y: auto;">
                ${currentPassage.passage_content}
              </div>
            </div>
          ` : ''}

          <div class="card question-card" style="background: #fff; border: 1px solid var(--line); border-radius: 4px; box-shadow: var(--shadow-sm); margin-bottom: 0;">
            <div class="card-body p-4" style="padding: 24px;">
              <div class="d-flex justify-content-between mb-3" style="margin-bottom: 1rem;">
                <h5 class="text-primary" style="margin: 0; font-size: 1.2rem; color: var(--primary);">Question ${appState.questionIndex + 1}</h5>
              </div>

              <div class="question-text mb-4" style="font-size: 1.1rem; line-height: 1.55; margin-bottom: 1.5rem;">
                ${current.question_text}
              </div>

              <div class="options-list" style="display: grid; gap: 12px;">
                ${optionKeys.map((letter) => {
                  const val = current[`option_${letter.toLowerCase()}`];
                  if (!val || !val.trim()) return '';
                  const isSelected = currentAnswer === letter;
                  return `
                    <div class="option-container">
                      <label class="option-label ${isSelected ? 'active' : ''} ${attempt.submitted_at ? 'disabled' : ''}" style="display: flex; align-items: flex-start; padding: 15px; border: 1px solid #ddd; border-radius: 4px; cursor: pointer; transition: all 0.2s;">
                        <input type="radio" name="answer" value="${letter}" ${isSelected ? 'checked' : ''} ${attempt.submitted_at ? 'disabled' : ''} data-option-answer="${letter}" style="margin-top: 4px;">
                        <span class="ms-2" style="margin-left: 10px; font-size: 1rem;"><strong>${letter}.</strong> ${val}</span>
                      </label>
                    </div>
                  `;
                }).join('')}
              </div>

              <div class="d-flex justify-content-between mt-4 border-top pt-3 nav-buttons-row" style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--line);">
                ${appState.questionIndex > 0 ? `
                  <button type="button" class="btn btn-secondary" id="prevQuestionBtn">Previous</button>
                ` : '<div></div>'}
                
                ${appState.questionIndex < questions.length - 1 ? `
                  <button type="button" class="btn btn-primary" id="nextQuestionBtn">Next</button>
                ` : (attempt.submitted_at ? '' : `
                  <button type="button" class="btn btn-success" id="mainSubmitExamBtn" style="background-color: var(--success); border-color: var(--success); color: #fff;">Submit Exam</button>
                `)}
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>

    <!-- Scientific Calculator UI -->
    ${(attempt.show_calculator && !attempt.submitted_at) ? `
      <div style="position: fixed; bottom: 20px; right: 20px; z-index: 1000;">
        <button id="toggleCalc" class="btn btn-primary btn-lg rounded-circle shadow" style="width: 56px; height: 56px; border-radius: 50%; border: none; background: linear-gradient(135deg, #007bff, #0056b3); color: white; font-size: 20px; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(0,123,255,0.4);">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" class="bi bi-calculator" viewBox="0 0 16 16">
            <path d="M12 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1zM4 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2z"/>
            <path d="M4 2.5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-.5.5h-7a.5.5 0 0 1-.5-.5zm2 5.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm0 2.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm-2.5-2.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm0 2.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm5.5-2.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm0 2.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5zm-5.5-5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-7a.5.5 0 0 1-.5-.5z"/>
          </svg>
        </button>
      </div>

      <div id="calcContainer" style="display: none; position: fixed; bottom: 90px; right: 20px; width: 320px; z-index: 9999; background: rgba(30, 34, 45, 0.95); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); overflow: hidden;">
        <div class="card" style="background: transparent; border: none; margin-bottom: 0; box-shadow: none;">
          <div class="card-header d-flex justify-content-between align-items-center" id="calcHeader" style="cursor: move; background: rgba(20, 24, 33, 0.8); border-bottom: 1px solid rgba(255,255,255,0.05); padding: 12px 16px; color: #fff; font-weight: 600; display: flex; justify-content: space-between; align-items: center;">
            <h6 class="mb-0 text-white" style="margin: 0; font-size: 1rem;">Calculator</h6>
            <button type="button" class="btn-close btn-close-white" id="closeCalc" style="background: none; border: none; color: white; font-size: 20px; cursor: pointer; padding: 0;">×</button>
          </div>
          <div class="card-body p-3" style="padding: 16px;">
            <input type="text" id="calcDisplay" class="form-control mb-3 text-end" value="0" readonly style="font-family: 'Courier New', Courier, monospace; font-size: 22px; font-weight: bold; height: 50px; background: #141821; color: #00ffcc; border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; text-shadow: 0 0 10px rgba(0,255,204,0.5); padding: 10px 15px; margin-bottom: 12px; width: 100%; text-align: right;">
            <div class="calc-keys d-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 5px;">
              <button type="button" class="btn-sci" value="sin" style="height: 38px; font-size: 13px; font-weight: 600; border-radius: 6px; border: 1px solid rgba(59,130,246,0.3); background: rgba(59,130,246,0.2); color: #60a5fa; cursor: pointer;">sin</button>
              <button type="button" class="btn-sci" value="cos" style="height: 38px; font-size: 13px; font-weight: 600; border-radius: 6px; border: 1px solid rgba(59,130,246,0.3); background: rgba(59,130,246,0.2); color: #60a5fa; cursor: pointer;">cos</button>
              <button type="button" class="btn-sci" value="tan" style="height: 38px; font-size: 13px; font-weight: 600; border-radius: 6px; border: 1px solid rgba(59,130,246,0.3); background: rgba(59,130,246,0.2); color: #60a5fa; cursor: pointer;">tan</button>
              <button type="button" class="btn-sci" value="log" style="height: 38px; font-size: 13px; font-weight: 600; border-radius: 6px; border: 1px solid rgba(59,130,246,0.3); background: rgba(59,130,246,0.2); color: #60a5fa; cursor: pointer;">log</button>
              
              <button type="button" class="btn-sci" value="sqrt" style="height: 38px; font-size: 13px; font-weight: 600; border-radius: 6px; border: 1px solid rgba(59,130,246,0.3); background: rgba(59,130,246,0.2); color: #60a5fa; cursor: pointer;">√</button>
              <button type="button" class="btn-sci" value="pow" style="height: 38px; font-size: 13px; font-weight: 600; border-radius: 6px; border: 1px solid rgba(59,130,246,0.3); background: rgba(59,130,246,0.2); color: #60a5fa; cursor: pointer;">x²</button>
              <button type="button" class="btn-sci" value="pi" style="height: 38px; font-size: 13px; font-weight: 600; border-radius: 6px; border: 1px solid rgba(59,130,246,0.3); background: rgba(59,130,246,0.2); color: #60a5fa; cursor: pointer;">π</button>
              <button type="button" class="btn-sci" value="exp" style="height: 38px; font-size: 13px; font-weight: 600; border-radius: 6px; border: 1px solid rgba(59,130,246,0.3); background: rgba(59,130,246,0.2); color: #60a5fa; cursor: pointer;">exp</button>

              <button type="button" class="btn-ac" value="all-clear" style="height: 38px; font-size: 13px; font-weight: 600; border-radius: 6px; border: 1px solid rgba(239,68,68,0.3); background: rgba(239,68,68,0.2); color: #f87171; cursor: pointer;">AC</button>
              <button type="button" class="btn-op" value="/" style="height: 38px; font-size: 13px; font-weight: 600; border-radius: 6px; border: 1px solid rgba(245,158,11,0.3); background: rgba(245,158,11,0.2); color: #fbbf24; cursor: pointer;">÷</button>
              <button type="button" class="btn-op" value="*" style="height: 38px; font-size: 13px; font-weight: 600; border-radius: 6px; border: 1px solid rgba(245,158,11,0.3); background: rgba(245,158,11,0.2); color: #fbbf24; cursor: pointer;">×</button>
              <button type="button" class="btn-op" value="-" style="height: 38px; font-size: 13px; font-weight: 600; border-radius: 6px; border: 1px solid rgba(245,158,11,0.3); background: rgba(245,158,11,0.2); color: #fbbf24; cursor: pointer;">-</button>

              <button type="button" class="btn-num" value="7" style="height: 38px; font-size: 13px; font-weight: 600; border-radius: 6px; border: 1px solid rgba(255,255,255,0.05); background: rgba(255,255,255,0.08); color: #f3f4f6; cursor: pointer;">7</button>
              <button type="button" class="btn-num" value="8" style="height: 38px; font-size: 13px; font-weight: 600; border-radius: 6px; border: 1px solid rgba(255,255,255,0.05); background: rgba(255,255,255,0.08); color: #f3f4f6; cursor: pointer;">8</button>
              <button type="button" class="btn-num" value="9" style="height: 38px; font-size: 13px; font-weight: 600; border-radius: 6px; border: 1px solid rgba(255,255,255,0.05); background: rgba(255,255,255,0.08); color: #f3f4f6; cursor: pointer;">9</button>
              <button type="button" class="btn-op" value="+" style="height: 38px; font-size: 13px; font-weight: 600; border-radius: 6px; border: 1px solid rgba(245,158,11,0.3); background: rgba(245,158,11,0.2); color: #fbbf24; cursor: pointer;">+</button>

              <button type="button" class="btn-num" value="4" style="height: 38px; font-size: 13px; font-weight: 600; border-radius: 6px; border: 1px solid rgba(255,255,255,0.05); background: rgba(255,255,255,0.08); color: #f3f4f6; cursor: pointer;">4</button>
              <button type="button" class="btn-num" value="5" style="height: 38px; font-size: 13px; font-weight: 600; border-radius: 6px; border: 1px solid rgba(255,255,255,0.05); background: rgba(255,255,255,0.08); color: #f3f4f6; cursor: pointer;">5</button>
              <button type="button" class="btn-num" value="6" style="height: 38px; font-size: 13px; font-weight: 600; border-radius: 6px; border: 1px solid rgba(255,255,255,0.05); background: rgba(255,255,255,0.08); color: #f3f4f6; cursor: pointer;">6</button>
              <button type="button" class="btn-sci" value="(" style="height: 38px; font-size: 13px; font-weight: 600; border-radius: 6px; border: 1px solid rgba(59,130,246,0.3); background: rgba(59,130,246,0.2); color: #60a5fa; cursor: pointer;">(</button>

              <button type="button" class="btn-num" value="1" style="height: 38px; font-size: 13px; font-weight: 600; border-radius: 6px; border: 1px solid rgba(255,255,255,0.05); background: rgba(255,255,255,0.08); color: #f3f4f6; cursor: pointer;">1</button>
              <button type="button" class="btn-num" value="2" style="height: 38px; font-size: 13px; font-weight: 600; border-radius: 6px; border: 1px solid rgba(255,255,255,0.05); background: rgba(255,255,255,0.08); color: #f3f4f6; cursor: pointer;">2</button>
              <button type="button" class="btn-num" value="3" style="height: 38px; font-size: 13px; font-weight: 600; border-radius: 6px; border: 1px solid rgba(255,255,255,0.05); background: rgba(255,255,255,0.08); color: #f3f4f6; cursor: pointer;">3</button>
              <button type="button" class="btn-sci" value=")" style="height: 38px; font-size: 13px; font-weight: 600; border-radius: 6px; border: 1px solid rgba(59,130,246,0.3); background: rgba(59,130,246,0.2); color: #60a5fa; cursor: pointer;">)</button>

              <button type="button" class="btn-num" value="0" style="height: 38px; font-size: 13px; font-weight: 600; border-radius: 6px; border: 1px solid rgba(255,255,255,0.05); background: rgba(255,255,255,0.08); color: #f3f4f6; cursor: pointer;">0</button>
              <button type="button" class="btn-num" value="." style="height: 38px; font-size: 13px; font-weight: 600; border-radius: 6px; border: 1px solid rgba(255,255,255,0.05); background: rgba(255,255,255,0.08); color: #f3f4f6; cursor: pointer;">.</button>
              <button type="button" class="btn-del" value="backspace" style="height: 38px; font-size: 13px; font-weight: 600; border-radius: 6px; border: 1px solid rgba(236,72,153,0.3); background: rgba(236,72,153,0.2); color: #f472b6; cursor: pointer;">DEL</button>
              <button type="button" class="btn-eq" value="=" style="height: 38px; font-size: 13px; font-weight: 600; border-radius: 6px; border: none; background: linear-gradient(135deg, #10b981, #059669); color: white; cursor: pointer; box-shadow: 0 4px 12px rgba(16,185,129,0.3);"> =</button>
            </div>
          </div>
        </div>
      </div>
    ` : ''}
  `;

  // Bind event listeners for take_exam UI
  const prevBtn = document.getElementById('prevQuestionBtn');
  if (prevBtn) {
    prevBtn.addEventListener('click', () => {
      appState.questionIndex = Math.max(0, appState.questionIndex - 1);
      renderExam();
    });
  }

  const nextBtn = document.getElementById('nextQuestionBtn');
  if (nextBtn) {
    nextBtn.addEventListener('click', () => {
      appState.questionIndex = Math.min(questions.length - 1, appState.questionIndex + 1);
      renderExam();
    });
  }

  document.querySelectorAll('[data-question-index]').forEach((button) => {
    button.addEventListener('click', () => {
      appState.questionIndex = Number(button.dataset.questionIndex);
      renderExam();
    });
  });

  const sidebarDashboard = document.getElementById('sidebarDashboardBtn');
  if (sidebarDashboard) {
    sidebarDashboard.addEventListener('click', () => {
      if (appState.timerInterval) {
        clearInterval(appState.timerInterval);
        appState.timerInterval = null;
      }
      loadDashboard();
    });
  }

  document.querySelectorAll('[data-option-answer]').forEach((input) => {
    input.addEventListener('change', async (e) => {
      const selected = e.target.value;
      await window.cbt.exam.saveAnswer({
        attemptId: attempt.id,
        questionId: current.id,
        selectedAnswer: selected
      });
      appState.attemptData = await window.cbt.exam.getAttempt(attempt.id);
      renderExam();
    });
  });

  const submitExam = async () => {
    let msg = 'Are you sure you want to submit this exam?';
    if (unansweredCount > 0) {
      msg = `You have ${unansweredCount} unanswered questions. Are you sure you want to submit the exam?`;
    }
    if (confirm(msg)) {
      if (appState.timerInterval) {
        clearInterval(appState.timerInterval);
        appState.timerInterval = null;
      }
      appState.attemptData = await window.cbt.exam.submit(attempt.id);
      renderExam();
    }
  };

  const mainSubmitBtn = document.getElementById('mainSubmitExamBtn');
  if (mainSubmitBtn) {
    mainSubmitBtn.addEventListener('click', submitExam);
  }

  const sidebarSubmitBtn = document.getElementById('sidebarSubmitExamBtn');
  if (sidebarSubmitBtn) {
    sidebarSubmitBtn.addEventListener('click', submitExam);
  }

  // Calculator logic bindings
  const toggleCalc = document.getElementById('toggleCalc');
  const calcContainer = document.getElementById('calcContainer');
  const closeCalc = document.getElementById('closeCalc');
  
  if (toggleCalc && calcContainer) {
    toggleCalc.addEventListener('click', () => {
      calcContainer.style.display = calcContainer.style.display === 'block' ? 'none' : 'block';
    });
  }

  if (closeCalc && calcContainer) {
    closeCalc.addEventListener('click', () => {
      calcContainer.style.display = 'none';
    });
  }

  const calcHeader = document.getElementById('calcHeader');
  if (calcHeader && calcContainer) {
    let isDragging = false;
    let currentX = 0;
    let currentY = 0;
    let initialX = 0;
    let initialY = 0;
    let xOffset = 0;
    let yOffset = 0;

    calcHeader.addEventListener('mousedown', (e) => {
      initialX = e.clientX - xOffset;
      initialY = e.clientY - yOffset;
      if (e.target === calcHeader || e.target.parentNode === calcHeader) {
        isDragging = true;
      }
    });

    document.addEventListener('mousemove', (e) => {
      if (isDragging) {
        e.preventDefault();
        currentX = e.clientX - initialX;
        currentY = e.clientY - initialY;
        xOffset = currentX;
        yOffset = currentY;
        calcContainer.style.transform = `translate3d(${currentX}px, ${currentY}px, 0)`;
      }
    });

    document.addEventListener('mouseup', () => {
      initialX = currentX;
      initialY = currentY;
      isDragging = false;
    });
  }

  const calcDisplay = document.getElementById('calcDisplay');
  const keys = document.querySelector('.calc-keys');
  let calcExpr = '';
  let calcResult = null;

  function updateCalcDisplay() {
    if (calcDisplay) {
      calcDisplay.value = calcExpr || '0';
    }
  }

  if (keys) {
    keys.addEventListener('click', (e) => {
      const target = e.target;
      if (!target.matches('button')) return;
      const val = target.value;

      if (val === 'all-clear') {
        calcExpr = '';
        calcResult = null;
        updateCalcDisplay();
        return;
      }

      if (val === 'backspace') {
        if (calcResult !== null) {
          calcExpr = '';
          calcResult = null;
          updateCalcDisplay();
          return;
        }
        if (calcExpr.length > 0) {
          const funcEnds = ['sin(', 'cos(', 'tan(', 'log(', 'sqrt(', 'exp('];
          let foundFunc = false;
          for (const func of funcEnds) {
            if (calcExpr.endsWith(func)) {
              calcExpr = calcExpr.substring(0, calcExpr.length - func.length);
              foundFunc = true;
              break;
            }
          }
          if (!foundFunc) {
            calcExpr = calcExpr.substring(0, calcExpr.length - 1);
          }
        }
        updateCalcDisplay();
        return;
      }

      if (val === '=') {
        if (!calcExpr) return;
        try {
          let evalExpr = calcExpr
            .replace(/PI/g, 'Math.PI')
            .replace(/sin\(/g, 'Math.sin_deg(')
            .replace(/cos\(/g, 'Math.cos_deg(')
            .replace(/tan\(/g, 'Math.tan_deg(')
            .replace(/log\(/g, 'Math.log10(')
            .replace(/sqrt\(/g, 'Math.sqrt(')
            .replace(/exp\(/g, 'Math.exp(')
            .replace(/\^/g, '**');

          Math.sin_deg = (x) => Math.sin(x * Math.PI / 180);
          Math.cos_deg = (x) => Math.cos(x * Math.PI / 180);
          Math.tan_deg = (x) => Math.tan(x * Math.PI / 180);

          const sanitized = evalExpr.replace(/[^0-9+\-*/().\s|Math.PI|Math.sin_deg|Math.cos_deg|Math.tan_deg|Math.log10|Math.sqrt|Math.exp|**]/g, '');
          const evalRes = new Function(`return (${sanitized});`)();
          
          calcResult = parseFloat(Number(evalRes).toFixed(8));
          calcExpr = calcResult.toString();
          updateCalcDisplay();
        } catch (err) {
          alert('Invalid Expression');
          calcExpr = '';
          calcResult = null;
          updateCalcDisplay();
        }
        return;
      }

      if (calcResult !== null) {
        if (!isNaN(val) || val === '.' || val === '(' || ['sin', 'cos', 'tan', 'log', 'sqrt', 'exp'].includes(val)) {
          calcExpr = '';
        } else {
          calcExpr = calcResult.toString();
        }
        calcResult = null;
      }

      if (['sin', 'cos', 'tan', 'log', 'sqrt', 'exp'].includes(val)) {
        calcExpr += val + '(';
      } else if (val === 'pi') {
        calcExpr += 'PI';
      } else if (val === 'pow') {
        calcExpr += '^2';
      } else {
        calcExpr += val;
      }
      updateCalcDisplay();
    });
  }
}

function formatTimeSeconds(totalSeconds) {
  const hours = Math.floor(totalSeconds / 3600);
  const minutes = Math.floor((totalSeconds % 3600) / 60);
  const seconds = totalSeconds % 60;
  return (
    (hours < 10 ? '0' : '') + hours + ':' +
    (minutes < 10 ? '0' : '') + minutes + ':' +
    (seconds < 10 ? '0' : '') + seconds
  );
}

async function renderSettings() {
  const settings = await window.cbt.settings.get();
  const sync = await window.cbt.sync.status();
  
  if (appState.timerInterval) {
    clearInterval(appState.timerInterval);
    appState.timerInterval = null;
  }

  renderShell(`
    <main class="container mt-4">
      <section class="card">
        <div class="card-header">
          <h4 class="mb-0">Settings & Sync Portal</h4>
        </div>
        <div class="card-body">
          ${appState.message ? `<div class="alert ${isErrorMessage(appState.message) ? 'alert-danger' : 'alert-info'}">${escapeHtml(appState.message)}</div>` : ''}
          <form id="settingsForm" class="form-grid">
            <div class="field">
              <label for="serverUrl">Server URL</label>
              <input id="serverUrl" name="serverUrl" value="${escapeHtml(settings.serverUrl || '')}" placeholder="https://school.example.com or https://school.example.com/cbt_exam" required>
            </div>
            <div class="field">
              <label for="deviceName">Device Name</label>
              <input id="deviceName" name="deviceName" value="${escapeHtml(settings.deviceName || '')}" required>
            </div>
            <div class="settings-grid" style="display: flex; gap: 10px; margin-top: 10px;">
              <button class="btn btn-secondary" id="settingsBackBtn" type="button">Back to Dashboard</button>
              <button class="btn btn-primary" type="submit">Save Settings</button>
            </div>
          </form>
          
          <hr>
          
          <h5>Data Import & Synchronization</h5>
          <p class="text-muted">Perform synchronization actions with the online CBT server or load data offline via JSON backups.</p>
          
          <div class="row-cards mt-3" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-top: 1rem;">
            <div class="feature-card" style="padding: 15px; border: 1px solid var(--line); border-radius: 4px; background: var(--light); text-align: center;">
              <h5>Import From Online</h5>
              <p class="text-muted" style="font-size: 0.9rem; min-height: 40px; margin-bottom: 12px;">Download all exam questions, subjects, and comprehension passages from the online server.</p>
              <button class="btn btn-primary btn-sm w-100" id="settingsImportOnlineBtn" type="button">Download Exam Data</button>
            </div>
            
            <div class="feature-card" style="padding: 15px; border: 1px solid var(--line); border-radius: 4px; background: var(--light); text-align: center;">
              <h5>Import JSON Backup</h5>
              <p class="text-muted" style="font-size: 0.9rem; min-height: 40px; margin-bottom: 12px;">Load exam questions and subjects from a downloaded JSON backup file using a USB stick.</p>
              <button class="btn btn-primary btn-sm w-100" id="settingsImportDataBtn" type="button">Import JSON Backup</button>
            </div>
            
            <div class="feature-card" style="padding: 15px; border: 1px solid var(--line); border-radius: 4px; background: var(--light); text-align: center;">
              <h5>Sync Results to Online</h5>
              <p class="text-muted" style="font-size: 0.9rem; min-height: 40px; margin-bottom: 12px;">Upload all pending student answers and completed exam scores back to the online server.</p>
              <button class="btn btn-success btn-sm w-100" id="settingsSyncBtn" type="button" style="background-color: var(--success); border-color: var(--success); color: #fff;">Upload Results</button>
            </div>
            
            <div class="feature-card" style="padding: 15px; border: 1px solid var(--line); border-radius: 4px; background: var(--light); text-align: center;">
              <h5>Export Results (USB)</h5>
              <p class="text-muted" style="font-size: 0.9rem; min-height: 40px; margin-bottom: 12px;">Save student attempts and answers to a results JSON file to transfer manually via USB.</p>
              <button class="btn btn-success btn-sm w-100" id="settingsExportAttemptsBtn" type="button" style="background-color: var(--success); border-color: var(--success); color: #fff;">Export Results JSON</button>
            </div>
          </div>

          <div class="card bg-light mt-4" style="margin-top: 1.5rem; background: var(--light); border: 1px solid var(--line);">
            <div class="card-body" style="padding: 15px;">
              <h6 style="margin-top: 0; font-size: 0.95rem; font-weight: 700;">Status & Queue Information</h6>
              <div class="stat-row" style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid rgba(0,0,0,0.05);">
                <span class="stat-label">Online Server:</span>
                <span class="stat-value" style="font-weight: 700;">${settings.serverUrl ? escapeHtml(settings.serverUrl) : 'Not Configured'}</span>
              </div>
              <div class="stat-row" style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid rgba(0,0,0,0.05);">
                <span class="stat-label">Last Synchronized:</span>
                <span class="stat-value" style="font-weight: 700;">${sync.lastSyncAt ? formatDate(sync.lastSyncAt) : 'Never'}</span>
              </div>
              <div class="stat-row" style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid rgba(0,0,0,0.05);">
                <span class="stat-label">Pending Sync Attempts:</span>
                <span class="badge bg-primary" style="font-weight: bold;">${sync.pending || 0} records</span>
              </div>
              <div class="stat-row" style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: none;">
                <span class="stat-label">Failed uploads:</span>
                <span class="stat-value text-danger" style="font-weight: 700; color: var(--danger);">${sync.failed || 0} records</span>
              </div>
            </div>
          </div>

        </div>
      </section>
    </main>
  `);

  document.getElementById('settingsBackBtn').addEventListener('click', () => {
    appState.message = '';
    if (appState.session) {
      loadDashboard();
    } else {
      renderLogin();
    }
  });
  
  document.getElementById('settingsForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    await window.cbt.settings.save({
      serverUrl: form.get('serverUrl'),
      deviceName: form.get('deviceName')
    });
    appState.message = 'Settings saved.';
    appState.syncStatus = await window.cbt.sync.status();
    renderSettings();
  });

  document.getElementById('settingsImportOnlineBtn').addEventListener('click', async () => {
    appState.message = 'Downloading exam data from online...';
    renderSettings();
    const result = await window.cbt.sync.importOnline();
    appState.message = result.message;
    appState.syncStatus = await window.cbt.sync.status();
    renderSettings();
  });

  document.getElementById('settingsImportDataBtn').addEventListener('click', async () => {
    const result = await window.cbt.sync.importFile();
    appState.message = result.message;
    appState.syncStatus = await window.cbt.sync.status();
    renderSettings();
  });

  document.getElementById('settingsSyncBtn').addEventListener('click', async () => {
    appState.message = 'Uploading results to online server...';
    renderSettings();
    const result = await window.cbt.sync.run();
    appState.message = result.message;
    appState.syncStatus = await window.cbt.sync.status();
    renderSettings();
  });

  document.getElementById('settingsExportAttemptsBtn').addEventListener('click', async () => {
    const result = await window.cbt.sync.exportAttempts();
    appState.message = result.message;
    appState.syncStatus = await window.cbt.sync.status();
    renderSettings();
  });
}

async function runSync() {
  const result = await window.cbt.sync.run();
  appState.message = result.message;
  await loadDashboard();
}

async function importData() {
  const result = await window.cbt.sync.importFile();
  appState.message = result.message;
  await loadDashboard();
}

async function importFromOnline() {
  const result = await window.cbt.sync.importOnline();
  appState.message = result.message;
  await loadDashboard();
}

async function exportAttempts() {
  const result = await window.cbt.sync.exportAttempts();
  appState.message = result.message;
  await loadDashboard();
}

function bindExamButtons() {
  document.querySelectorAll('[data-start-exam]').forEach((button) => {
    button.addEventListener('click', async () => {
      const attempt = await window.cbt.exam.start(button.dataset.startExam);
      appState.attemptData = attempt;
      appState.questionIndex = 0;
      renderExam();
    });
  });

  document.querySelectorAll('[data-open-attempt]').forEach((button) => {
    button.addEventListener('click', async () => {
      appState.attemptData = await window.cbt.exam.getAttempt(button.dataset.openAttempt);
      appState.questionIndex = 0;
      renderExam();
    });
  });
}

function renderDashboardHeader(title, subtitle, currentPage) {
  return `
    <div class="dashboard-header">
      <div class="container dashboard-header-inner">
        <div>
          <h1 class="dashboard-title">${title}</h1>
          <p class="mb-0">${subtitle}</p>
        </div>
        <nav class="breadcrumb">
          <span>Home</span>
          <span>${currentPage}</span>
        </nav>
      </div>
    </div>
  `;
}

function statusBadge(status) {
  if (status === 'completed') return '<span class="badge bg-success">Completed</span>';
  if (status === 'in_progress') return '<span class="badge bg-warning text-dark">In Progress</span>';
  return '<span class="badge bg-primary">Available</span>';
}

function isErrorMessage(message) {
  return /failed|error|cancelled|invalid/i.test(message || '');
}

function formatDate(value) {
  if (!value) return '';
  return new Date(value).toLocaleString();
}

function formatTime(seconds) {
  const safeSeconds = Math.max(0, Number(seconds || 0));
  const minutes = Math.floor(safeSeconds / 60);
  const remainder = safeSeconds % 60;
  return `${String(minutes).padStart(2, '0')}:${String(remainder).padStart(2, '0')}`;
}

function escapeHtml(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function stripHtml(value) {
  return String(value || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
}
