const fs = require('fs');
const path = require('path');
const Database = require('better-sqlite3');

function createDatabase(dbPath, schemaDir) {
  fs.mkdirSync(path.dirname(dbPath), { recursive: true });

  const db = new Database(dbPath);
  db.pragma('journal_mode = WAL');
  db.pragma('foreign_keys = ON');
  db.pragma('busy_timeout = 5000');

  migrate(db, schemaDir);
  seedDevelopmentData(db);

  return db;
}

function migrate(db, schemaDir) {
  db.exec(`
    CREATE TABLE IF NOT EXISTS schema_migrations (
      version TEXT PRIMARY KEY,
      applied_at TEXT NOT NULL
    );
  `);

  const resolvedSchemaDir = fs.existsSync(schemaDir)
    ? schemaDir
    : path.join(__dirname, '../../../schema');

  const files = fs.readdirSync(resolvedSchemaDir)
    .filter((file) => file.endsWith('.sql'))
    .sort();

  const hasMigration = db.prepare('SELECT 1 FROM schema_migrations WHERE version = ?');
  const addMigration = db.prepare('INSERT INTO schema_migrations (version, applied_at) VALUES (?, ?)');

  const apply = db.transaction((file) => {
    const alreadyApplied = hasMigration.get(file);
    if (alreadyApplied) return;

    const sql = fs.readFileSync(path.join(resolvedSchemaDir, file), 'utf8');
    db.exec(sql);
    addMigration.run(file, now());
  });

  files.forEach((file) => apply(file));
}

function seedDevelopmentData(db) {
  const usersCount = db.prepare('SELECT COUNT(*) AS count FROM users').get().count;
  if (usersCount > 0) return;

  const timestamp = now();
  const insertUser = db.prepare(`
    INSERT INTO users (id, server_id, email, password_hash, full_name, role, class_id, subject_code, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
  `);
  const insertClass = db.prepare(`
    INSERT INTO classes (id, server_id, name, description, marks_per_question, updated_at)
    VALUES (?, ?, ?, ?, ?, ?)
  `);
  const insertSubject = db.prepare(`
    INSERT INTO subjects (id, server_id, name, subject_code, description, updated_at)
    VALUES (?, ?, ?, ?, ?, ?)
  `);
  const insertExam = db.prepare(`
    INSERT INTO exams (id, server_id, title, description, is_published, published_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?)
  `);
  const insertExamSubject = db.prepare(`
    INSERT INTO exam_subjects (id, server_id, exam_id, subject_id, class_id, time_limit, is_published, published_at, updated_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
  `);
  const insertQuestion = db.prepare(`
    INSERT INTO questions (
      id, server_id, exam_subject_id, order_position, question_text,
      option_a, option_b, option_c, option_d, correct_answer, updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
  `);

  const runSeed = db.transaction(() => {
    insertClass.run('class-js1', 1, 'JSS 1', 'Junior Secondary 1', 1, timestamp);
    insertSubject.run('subject-math', 1, 'Mathematics', 'MATH001', 'Mathematics', timestamp);
    insertUser.run('student-demo', 1, 'student@cbtexam.com', 'Student@2024', 'Demo Student', 'student', 'class-js1', 'MATH001', timestamp);
    insertUser.run('admin-demo', 2, 'admin@cbtexam.com', 'Admin@2024', 'Offline Admin', 'admin', null, null, timestamp);
    insertUser.run('teacher-demo', 3, 'teacher@cbtexam.com', 'Teacher@2024', 'Demo Teacher', 'teacher', null, 'MATH001', timestamp);
    insertExam.run('exam-demo', 1, 'Offline Sample Exam', 'A sample exam included for testing the desktop client.', 1, timestamp, timestamp);
    insertExamSubject.run('exam-subject-demo', 1, 'exam-demo', 'subject-math', 'class-js1', 20, 1, timestamp, timestamp);

    insertQuestion.run('question-1', 1, 'exam-subject-demo', 0, 'What is 2 + 2?', '3', '4', '5', '6', 'B', timestamp);
    insertQuestion.run('question-2', 2, 'exam-subject-demo', 1, 'Which number is even?', '7', '9', '12', '15', 'C', timestamp);
  });

  runSeed();
}

function now() {
  return new Date().toISOString();
}

module.exports = {
  createDatabase,
  now
};
