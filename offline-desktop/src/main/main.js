const { app, BrowserWindow, ipcMain, dialog } = require('electron');
const fs = require('fs');
const path = require('path');
const { createDatabase } = require('./services/database');
const { createExamService } = require('./services/exam-service');
const { createSyncService } = require('./services/sync-service');

let mainWindow;
let db;
let examService;
let syncService;
let store;

function createWindow() {
  mainWindow = new BrowserWindow({
    width: 1180,
    height: 780,
    minWidth: 960,
    minHeight: 640,
    title: 'CBT Offline',
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false
    }
  });

  mainWindow.loadFile(path.join(__dirname, '../renderer/index.html'));
}

app.whenReady().then(() => {
  const dbPath = path.join(app.getPath('userData'), 'cbt-offline.db');
  store = createJsonStore(path.join(app.getPath('userData'), 'settings.json'), {
    serverUrl: '',
    deviceName: 'CBT Offline Client'
  });
  db = createDatabase(dbPath, path.join(process.resourcesPath || app.getAppPath(), 'schema'));
  examService = createExamService(db);
  syncService = createSyncService(db, store);

  registerIpc();
  createWindow();
});

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') app.quit();
});

app.on('activate', () => {
  if (BrowserWindow.getAllWindows().length === 0) createWindow();
});

function registerIpc() {
  ipcMain.handle('settings:get', () => ({
    serverUrl: store.get('serverUrl'),
    deviceName: store.get('deviceName')
  }));

  ipcMain.handle('settings:save', (_event, settings) => {
    store.set('serverUrl', (settings.serverUrl || '').trim());
    store.set('deviceName', (settings.deviceName || 'CBT Offline Client').trim());
    return { ok: true };
  });

  ipcMain.handle('auth:login', (_event, credentials) => examService.login(credentials));
  ipcMain.handle('auth:logout', () => examService.logout());
  ipcMain.handle('session:get', () => examService.getSession());

  ipcMain.handle('dashboard:get', () => examService.getDashboard());
  ipcMain.handle('teacher:examSubjects', () => examService.getTeacherExamSubjects());
  ipcMain.handle('teacher:questions', (_event, examSubjectId) => examService.getTeacherQuestions(examSubjectId));
  ipcMain.handle('teacher:saveQuestion', (_event, payload) => examService.saveTeacherQuestion(payload));
  ipcMain.handle('teacher:deleteQuestion', (_event, questionId) => examService.deleteTeacherQuestion(questionId));
  ipcMain.handle('exam:start', (_event, examSubjectId) => examService.startAttempt(examSubjectId));
  ipcMain.handle('exam:getAttempt', (_event, attemptId) => examService.getAttempt(attemptId));
  ipcMain.handle('exam:saveAnswer', (_event, payload) => examService.saveAnswer(payload));
  ipcMain.handle('exam:submit', (_event, attemptId) => examService.submitAttempt(attemptId));

  ipcMain.handle('sync:status', () => syncService.getStatus());
  ipcMain.handle('sync:run', () => syncService.runSync());
  ipcMain.handle('sync:importOnline', () => syncService.importFromOnline());
  ipcMain.handle('sync:importFile', async () => {
    const result = await dialog.showOpenDialog(mainWindow, {
      title: 'Import offline CBT data',
      filters: [{ name: 'CBT JSON Export', extensions: ['json'] }],
      properties: ['openFile']
    });

    if (result.canceled || !result.filePaths.length) {
      return { ok: false, message: 'Import cancelled.' };
    }

    return syncService.importFromFile(result.filePaths[0]);
  });
  ipcMain.handle('sync:exportAttempts', async () => {
    const result = await dialog.showSaveDialog(mainWindow, {
      title: 'Export offline CBT results',
      defaultPath: `cbt-offline-results-${new Date().toISOString().slice(0, 10)}.json`,
      filters: [{ name: 'CBT Results JSON', extensions: ['json'] }]
    });

    if (result.canceled || !result.filePath) {
      return { ok: false, message: 'Export cancelled.' };
    }

    return syncService.exportAttempts(result.filePath);
  });
}

function createJsonStore(filePath, defaults) {
  fs.mkdirSync(path.dirname(filePath), { recursive: true });
  let cache = { ...defaults };

  if (fs.existsSync(filePath)) {
    try {
      cache = { ...defaults, ...JSON.parse(fs.readFileSync(filePath, 'utf8')) };
    } catch (_error) {
      cache = { ...defaults };
    }
  }

  function persist() {
    fs.writeFileSync(filePath, JSON.stringify(cache, null, 2));
  }

  return {
    get(key) {
      return cache[key];
    },
    set(key, value) {
      cache[key] = value;
      persist();
    }
  };
}
