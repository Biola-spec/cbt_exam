const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('cbt', {
  settings: {
    get: () => ipcRenderer.invoke('settings:get'),
    save: (settings) => ipcRenderer.invoke('settings:save', settings)
  },
  auth: {
    login: (credentials) => ipcRenderer.invoke('auth:login', credentials),
    logout: () => ipcRenderer.invoke('auth:logout'),
    getSession: () => ipcRenderer.invoke('session:get')
  },
  dashboard: {
    get: () => ipcRenderer.invoke('dashboard:get')
  },
  teacher: {
    examSubjects: () => ipcRenderer.invoke('teacher:examSubjects'),
    questions: (examSubjectId) => ipcRenderer.invoke('teacher:questions', examSubjectId),
    saveQuestion: (payload) => ipcRenderer.invoke('teacher:saveQuestion', payload),
    deleteQuestion: (questionId) => ipcRenderer.invoke('teacher:deleteQuestion', questionId)
  },
  exam: {
    start: (examSubjectId) => ipcRenderer.invoke('exam:start', examSubjectId),
    getAttempt: (attemptId) => ipcRenderer.invoke('exam:getAttempt', attemptId),
    saveAnswer: (payload) => ipcRenderer.invoke('exam:saveAnswer', payload),
    submit: (attemptId) => ipcRenderer.invoke('exam:submit', attemptId)
  },
  sync: {
    status: () => ipcRenderer.invoke('sync:status'),
    run: () => ipcRenderer.invoke('sync:run'),
    importOnline: () => ipcRenderer.invoke('sync:importOnline'),
    importFile: () => ipcRenderer.invoke('sync:importFile'),
    exportAttempts: () => ipcRenderer.invoke('sync:exportAttempts')
  }
});
