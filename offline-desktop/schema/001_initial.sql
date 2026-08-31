CREATE TABLE IF NOT EXISTS app_meta (
  key TEXT PRIMARY KEY,
  value TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS users (
  id TEXT PRIMARY KEY,
  server_id INTEGER,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  full_name TEXT NOT NULL,
  role TEXT NOT NULL CHECK (role IN ('admin', 'teacher', 'student')),
  class_id TEXT,
  subject_code TEXT,
  updated_at TEXT NOT NULL,
  deleted_at TEXT
);

CREATE TABLE IF NOT EXISTS classes (
  id TEXT PRIMARY KEY,
  server_id INTEGER,
  name TEXT NOT NULL,
  description TEXT,
  marks_per_question REAL NOT NULL DEFAULT 1,
  updated_at TEXT NOT NULL,
  deleted_at TEXT
);

CREATE TABLE IF NOT EXISTS subjects (
  id TEXT PRIMARY KEY,
  server_id INTEGER,
  name TEXT NOT NULL,
  subject_code TEXT NOT NULL UNIQUE,
  description TEXT,
  updated_at TEXT NOT NULL,
  deleted_at TEXT
);

CREATE TABLE IF NOT EXISTS exams (
  id TEXT PRIMARY KEY,
  server_id INTEGER,
  title TEXT NOT NULL,
  description TEXT,
  is_published INTEGER NOT NULL DEFAULT 0,
  published_at TEXT,
  updated_at TEXT NOT NULL,
  deleted_at TEXT
);

CREATE TABLE IF NOT EXISTS exam_subjects (
  id TEXT PRIMARY KEY,
  server_id INTEGER,
  exam_id TEXT NOT NULL,
  subject_id TEXT NOT NULL,
  class_id TEXT NOT NULL,
  time_limit INTEGER NOT NULL DEFAULT 60,
  is_published INTEGER NOT NULL DEFAULT 0,
  published_at TEXT,
  show_calculator INTEGER NOT NULL DEFAULT 0,
  updated_at TEXT NOT NULL,
  deleted_at TEXT,
  FOREIGN KEY (exam_id) REFERENCES exams(id),
  FOREIGN KEY (subject_id) REFERENCES subjects(id),
  FOREIGN KEY (class_id) REFERENCES classes(id)
);

CREATE TABLE IF NOT EXISTS questions (
  id TEXT PRIMARY KEY,
  server_id INTEGER,
  exam_subject_id TEXT NOT NULL,
  order_position INTEGER NOT NULL DEFAULT 0,
  question_text TEXT NOT NULL,
  option_a TEXT NOT NULL,
  option_b TEXT NOT NULL,
  option_c TEXT NOT NULL,
  option_d TEXT NOT NULL,
  option_e TEXT,
  option_f TEXT,
  correct_answer TEXT NOT NULL CHECK (correct_answer IN ('A','B','C','D','E','F')),
  updated_at TEXT NOT NULL,
  deleted_at TEXT,
  FOREIGN KEY (exam_subject_id) REFERENCES exam_subjects(id)
);

CREATE TABLE IF NOT EXISTS comprehension_passages (
  id TEXT PRIMARY KEY,
  server_id INTEGER,
  exam_subject_id TEXT NOT NULL,
  passage_title TEXT,
  passage_content TEXT NOT NULL,
  start_question_number INTEGER NOT NULL,
  end_question_number INTEGER NOT NULL,
  updated_at TEXT NOT NULL,
  deleted_at TEXT,
  FOREIGN KEY (exam_subject_id) REFERENCES exam_subjects(id)
);

CREATE TABLE IF NOT EXISTS exam_attempts (
  id TEXT PRIMARY KEY,
  server_id INTEGER,
  student_id TEXT NOT NULL,
  exam_id TEXT NOT NULL,
  exam_subject_id TEXT NOT NULL,
  started_at TEXT NOT NULL,
  submitted_at TEXT,
  score REAL,
  total_questions INTEGER NOT NULL,
  sync_status TEXT NOT NULL DEFAULT 'pending' CHECK (sync_status IN ('pending','synced','failed')),
  updated_at TEXT NOT NULL,
  FOREIGN KEY (student_id) REFERENCES users(id),
  FOREIGN KEY (exam_id) REFERENCES exams(id),
  FOREIGN KEY (exam_subject_id) REFERENCES exam_subjects(id)
);

CREATE TABLE IF NOT EXISTS student_answers (
  id TEXT PRIMARY KEY,
  server_id INTEGER,
  attempt_id TEXT NOT NULL,
  question_id TEXT NOT NULL,
  selected_answer TEXT CHECK (selected_answer IN ('A','B','C','D','E','F')),
  is_correct INTEGER,
  answered_at TEXT NOT NULL,
  sync_status TEXT NOT NULL DEFAULT 'pending' CHECK (sync_status IN ('pending','synced','failed')),
  updated_at TEXT NOT NULL,
  UNIQUE (attempt_id, question_id),
  FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id),
  FOREIGN KEY (question_id) REFERENCES questions(id)
);

CREATE TABLE IF NOT EXISTS sync_queue (
  id TEXT PRIMARY KEY,
  entity_type TEXT NOT NULL,
  entity_id TEXT NOT NULL,
  operation TEXT NOT NULL CHECK (operation IN ('upsert','delete')),
  payload TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','synced','failed')),
  attempts INTEGER NOT NULL DEFAULT 0,
  last_error TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);
CREATE INDEX IF NOT EXISTS idx_exam_subjects_class_subject ON exam_subjects(class_id, subject_id);
CREATE INDEX IF NOT EXISTS idx_questions_exam_subject ON questions(exam_subject_id, order_position);
CREATE INDEX IF NOT EXISTS idx_attempts_student_subject ON exam_attempts(student_id, exam_subject_id);
CREATE INDEX IF NOT EXISTS idx_answers_attempt ON student_answers(attempt_id);
CREATE INDEX IF NOT EXISTS idx_sync_queue_status ON sync_queue(status, updated_at);
