const os = require('os');
const path = require('path');
const { createDatabase } = require('../src/main/services/database');
const { createExamService } = require('../src/main/services/exam-service');
const { createSyncService } = require('../src/main/services/sync-service');

try {
  const dbPath = path.join(os.tmpdir(), `cbt-offline-smoke-${Date.now()}.db`);
  const schemaDir = path.join(__dirname, '../schema');
  const db = createDatabase(dbPath, schemaDir);
  const examService = createExamService(db);
  const syncService = createSyncService(db, {
    get(key) {
      const values = {
        serverUrl: '',
        deviceName: 'Smoke Test'
      };
      return values[key];
    },
    set() {}
  });

  const login = examService.login({
    email: 'student@cbtexam.com',
    password: 'Student@2024',
    subjectCode: 'MATH001'
  });

  if (!login.ok) {
    throw new Error(login.message);
  }

  const dashboard = examService.getDashboard();
  if (dashboard.exams.length !== 1) {
    throw new Error(`Expected 1 exam, found ${dashboard.exams.length}`);
  }

  const attemptData = examService.startAttempt(dashboard.exams[0].exam_subject_id);
  if (attemptData.questions.length !== 2) {
    throw new Error(`Expected 2 questions, found ${attemptData.questions.length}`);
  }

  const firstQuestion = attemptData.questions[0];
  examService.saveAnswer({
    attemptId: attemptData.attempt.id,
    questionId: firstQuestion.id,
    selectedAnswer: firstQuestion.correct_answer
  });

  const submitted = examService.submitAttempt(attemptData.attempt.id);
  if (!submitted.attempt.submitted_at) {
    throw new Error('Attempt was not submitted.');
  }

  const status = syncService.getStatus();
  if (status.pending < 2) {
    throw new Error(`Expected pending sync records, found ${status.pending}`);
  }

  examService.logout();
  const teacherLogin = examService.login({
    email: 'teacher@cbtexam.com',
    password: 'Teacher@2024',
    subjectCode: ''
  });

  if (!teacherLogin.ok) {
    throw new Error(teacherLogin.message);
  }

  const teacherExams = examService.getTeacherExamSubjects();
  if (teacherExams.length !== 1) {
    throw new Error(`Expected 1 teacher exam, found ${teacherExams.length}`);
  }

  const savedQuestion = examService.saveTeacherQuestion({
    examSubjectId: teacherExams[0].exam_subject_id,
    question_text: 'What is 5 + 5?',
    option_a: '8',
    option_b: '9',
    option_c: '10',
    option_d: '11',
    correct_answer: 'C'
  });

  if (!savedQuestion.ok || savedQuestion.data.questions.length !== 3) {
    throw new Error('Teacher question was not saved.');
  }

  console.log('Smoke test passed.');
  process.exit(0);
} catch (error) {
  console.error(error);
  process.exit(1);
}
