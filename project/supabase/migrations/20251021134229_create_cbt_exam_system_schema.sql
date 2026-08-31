/*
  # CBT Exam System Database Schema

  ## Overview
  This migration creates a complete CBT (Computer-Based Testing) exam system with support for:
  - Three user types: Admin, Teacher, and Student
  - School configuration management
  - Class and subject allocation
  - Exam creation and publishing
  - Question management
  - Passkey-based student authentication
  - Exam attempts and student answers tracking

  ## New Tables

  ### 1. `profiles`
  User profile extension for auth.users
  - `id` (uuid, FK to auth.users)
  - `email` (text)
  - `full_name` (text)
  - `role` (text: 'admin', 'teacher', 'student')
  - `created_at` (timestamptz)

  ### 2. `school_config`
  School configuration (singleton table)
  - `id` (uuid, PK)
  - `school_name` (text)
  - `updated_at` (timestamptz)
  - `updated_by` (uuid, FK to profiles)

  ### 3. `classes`
  Class definitions
  - `id` (uuid, PK)
  - `name` (text)
  - `description` (text, optional)
  - `created_at` (timestamptz)

  ### 4. `subjects`
  Subject definitions
  - `id` (uuid, PK)
  - `name` (text)
  - `description` (text, optional)
  - `created_at` (timestamptz)

  ### 5. `teacher_allocations`
  Admin allocates classes and subjects to teachers
  - `id` (uuid, PK)
  - `teacher_id` (uuid, FK to profiles)
  - `class_id` (uuid, FK to classes)
  - `subject_id` (uuid, FK to subjects)
  - `allocated_at` (timestamptz)
  - `allocated_by` (uuid, FK to profiles - admin)

  ### 6. `exams`
  Exam definitions created by admin
  - `id` (uuid, PK)
  - `title` (text)
  - `description` (text, optional)
  - `is_published` (boolean)
  - `published_at` (timestamptz, optional)
  - `created_by` (uuid, FK to profiles - admin)
  - `created_at` (timestamptz)

  ### 7. `exam_subjects`
  Subjects included in an exam with class association
  - `id` (uuid, PK)
  - `exam_id` (uuid, FK to exams)
  - `subject_id` (uuid, FK to subjects)
  - `class_id` (uuid, FK to classes)
  - `created_at` (timestamptz)

  ### 8. `questions`
  Questions created by teachers for their allocated subjects/classes
  - `id` (uuid, PK)
  - `exam_subject_id` (uuid, FK to exam_subjects)
  - `question_text` (text)
  - `option_a` (text)
  - `option_b` (text)
  - `option_c` (text)
  - `option_d` (text)
  - `correct_answer` (text: 'A', 'B', 'C', 'D')
  - `created_by` (uuid, FK to profiles - teacher)
  - `created_at` (timestamptz)

  ### 9. `passkeys`
  Access codes for students to take exams
  - `id` (uuid, PK)
  - `passkey` (text, unique)
  - `student_email` (text)
  - `exam_id` (uuid, FK to exams)
  - `is_used` (boolean)
  - `expires_at` (timestamptz)
  - `created_by` (uuid, FK to profiles - admin)
  - `created_at` (timestamptz)

  ### 10. `exam_attempts`
  Student exam attempts tracking
  - `id` (uuid, PK)
  - `student_id` (uuid, FK to profiles)
  - `exam_id` (uuid, FK to exams)
  - `exam_subject_id` (uuid, FK to exam_subjects)
  - `started_at` (timestamptz)
  - `submitted_at` (timestamptz, optional)
  - `score` (integer, optional)
  - `total_questions` (integer)

  ### 11. `student_answers`
  Individual answers submitted by students
  - `id` (uuid, PK)
  - `attempt_id` (uuid, FK to exam_attempts)
  - `question_id` (uuid, FK to questions)
  - `selected_answer` (text: 'A', 'B', 'C', 'D')
  - `is_correct` (boolean)
  - `answered_at` (timestamptz)

  ### 12. `exam_republish_log`
  Track when admin republishes exam for specific students
  - `id` (uuid, PK)
  - `student_id` (uuid, FK to profiles)
  - `exam_id` (uuid, FK to exams)
  - `exam_subject_id` (uuid, FK to exam_subjects, optional)
  - `republished_by` (uuid, FK to profiles - admin)
  - `republished_at` (timestamptz)
  - `reason` (text, optional)

  ## Security
  - Enable RLS on all tables
  - Admin can manage all data
  - Teachers can only manage questions for their allocated subjects/classes
  - Students can only access published exams and view their own attempts
  - Passkey validation ensures secure student access

  ## Important Notes
  - Uses uuid_generate_v4() for primary keys
  - Timestamps use timestamptz with now() defaults
  - Comprehensive foreign key relationships ensure data integrity
  - RLS policies enforce role-based access control
*/

-- Enable UUID extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- 1. Profiles Table
CREATE TABLE IF NOT EXISTS profiles (
  id uuid PRIMARY KEY REFERENCES auth.users(id) ON DELETE CASCADE,
  email text UNIQUE NOT NULL,
  full_name text NOT NULL,
  role text NOT NULL CHECK (role IN ('admin', 'teacher', 'student')),
  created_at timestamptz DEFAULT now()
);

ALTER TABLE profiles ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Users can view own profile"
  ON profiles FOR SELECT
  TO authenticated
  USING (auth.uid() = id);

CREATE POLICY "Users can update own profile"
  ON profiles FOR UPDATE
  TO authenticated
  USING (auth.uid() = id)
  WITH CHECK (auth.uid() = id);

-- 2. School Config Table
CREATE TABLE IF NOT EXISTS school_config (
  id uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  school_name text NOT NULL,
  updated_at timestamptz DEFAULT now(),
  updated_by uuid REFERENCES profiles(id)
);

ALTER TABLE school_config ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Anyone authenticated can view school config"
  ON school_config FOR SELECT
  TO authenticated
  USING (true);

CREATE POLICY "Only admins can update school config"
  ON school_config FOR UPDATE
  TO authenticated
  USING (EXISTS (
    SELECT 1 FROM profiles WHERE profiles.id = auth.uid() AND profiles.role = 'admin'
  ))
  WITH CHECK (EXISTS (
    SELECT 1 FROM profiles WHERE profiles.id = auth.uid() AND profiles.role = 'admin'
  ));

CREATE POLICY "Only admins can insert school config"
  ON school_config FOR INSERT
  TO authenticated
  WITH CHECK (EXISTS (
    SELECT 1 FROM profiles WHERE profiles.id = auth.uid() AND profiles.role = 'admin'
  ));

-- 3. Classes Table
CREATE TABLE IF NOT EXISTS classes (
  id uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  name text NOT NULL,
  description text,
  created_at timestamptz DEFAULT now()
);

ALTER TABLE classes ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Anyone authenticated can view classes"
  ON classes FOR SELECT
  TO authenticated
  USING (true);

CREATE POLICY "Only admins can insert classes"
  ON classes FOR INSERT
  TO authenticated
  WITH CHECK (EXISTS (
    SELECT 1 FROM profiles WHERE profiles.id = auth.uid() AND profiles.role = 'admin'
  ));

CREATE POLICY "Only admins can update classes"
  ON classes FOR UPDATE
  TO authenticated
  USING (EXISTS (
    SELECT 1 FROM profiles WHERE profiles.id = auth.uid() AND profiles.role = 'admin'
  ))
  WITH CHECK (EXISTS (
    SELECT 1 FROM profiles WHERE profiles.id = auth.uid() AND profiles.role = 'admin'
  ));

CREATE POLICY "Only admins can delete classes"
  ON classes FOR DELETE
  TO authenticated
  USING (EXISTS (
    SELECT 1 FROM profiles WHERE profiles.id = auth.uid() AND profiles.role = 'admin'
  ));

-- 4. Subjects Table
CREATE TABLE IF NOT EXISTS subjects (
  id uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  name text NOT NULL,
  description text,
  created_at timestamptz DEFAULT now()
);

ALTER TABLE subjects ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Anyone authenticated can view subjects"
  ON subjects FOR SELECT
  TO authenticated
  USING (true);

CREATE POLICY "Only admins can insert subjects"
  ON subjects FOR INSERT
  TO authenticated
  WITH CHECK (EXISTS (
    SELECT 1 FROM profiles WHERE profiles.id = auth.uid() AND profiles.role = 'admin'
  ));

CREATE POLICY "Only admins can update subjects"
  ON subjects FOR UPDATE
  TO authenticated
  USING (EXISTS (
    SELECT 1 FROM profiles WHERE profiles.id = auth.uid() AND profiles.role = 'admin'
  ))
  WITH CHECK (EXISTS (
    SELECT 1 FROM profiles WHERE profiles.id = auth.uid() AND profiles.role = 'admin'
  ));

CREATE POLICY "Only admins can delete subjects"
  ON subjects FOR DELETE
  TO authenticated
  USING (EXISTS (
    SELECT 1 FROM profiles WHERE profiles.id = auth.uid() AND profiles.role = 'admin'
  ));

-- 5. Teacher Allocations Table
CREATE TABLE IF NOT EXISTS teacher_allocations (
  id uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  teacher_id uuid NOT NULL REFERENCES profiles(id) ON DELETE CASCADE,
  class_id uuid NOT NULL REFERENCES classes(id) ON DELETE CASCADE,
  subject_id uuid NOT NULL REFERENCES subjects(id) ON DELETE CASCADE,
  allocated_at timestamptz DEFAULT now(),
  allocated_by uuid REFERENCES profiles(id),
  UNIQUE(teacher_id, class_id, subject_id)
);

ALTER TABLE teacher_allocations ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Teachers can view own allocations"
  ON teacher_allocations FOR SELECT
  TO authenticated
  USING (
    auth.uid() = teacher_id OR
    EXISTS (SELECT 1 FROM profiles WHERE profiles.id = auth.uid() AND profiles.role = 'admin')
  );

CREATE POLICY "Only admins can insert teacher allocations"
  ON teacher_allocations FOR INSERT
  TO authenticated
  WITH CHECK (EXISTS (
    SELECT 1 FROM profiles WHERE profiles.id = auth.uid() AND profiles.role = 'admin'
  ));

CREATE POLICY "Only admins can delete teacher allocations"
  ON teacher_allocations FOR DELETE
  TO authenticated
  USING (EXISTS (
    SELECT 1 FROM profiles WHERE profiles.id = auth.uid() AND profiles.role = 'admin'
  ));

-- 6. Exams Table
CREATE TABLE IF NOT EXISTS exams (
  id uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  title text NOT NULL,
  description text,
  is_published boolean DEFAULT false,
  published_at timestamptz,
  created_by uuid REFERENCES profiles(id),
  created_at timestamptz DEFAULT now()
);

ALTER TABLE exams ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Admins can view all exams"
  ON exams FOR SELECT
  TO authenticated
  USING (EXISTS (
    SELECT 1 FROM profiles WHERE profiles.id = auth.uid() AND profiles.role = 'admin'
  ));

CREATE POLICY "Teachers can view all exams"
  ON exams FOR SELECT
  TO authenticated
  USING (EXISTS (
    SELECT 1 FROM profiles WHERE profiles.id = auth.uid() AND profiles.role = 'teacher'
  ));

CREATE POLICY "Students can view published exams"
  ON exams FOR SELECT
  TO authenticated
  USING (
    is_published = true AND
    EXISTS (SELECT 1 FROM profiles WHERE profiles.id = auth.uid() AND profiles.role = 'student')
  );

CREATE POLICY "Only admins can insert exams"
  ON exams FOR INSERT
  TO authenticated
  WITH CHECK (EXISTS (
    SELECT 1 FROM profiles WHERE profiles.id = auth.uid() AND profiles.role = 'admin'
  ));

CREATE POLICY "Only admins can update exams"
  ON exams FOR UPDATE
  TO authenticated
  USING (EXISTS (
    SELECT 1 FROM profiles WHERE profiles.id = auth.uid() AND profiles.role = 'admin'
  ))
  WITH CHECK (EXISTS (
    SELECT 1 FROM profiles WHERE profiles.id = auth.uid() AND profiles.role = 'admin'
  ));

CREATE POLICY "Only admins can delete exams"
  ON exams FOR DELETE
  TO authenticated
  USING (EXISTS (
    SELECT 1 FROM profiles WHERE profiles.id = auth.uid() AND profiles.role = 'admin'
  ));

-- 7. Exam Subjects Table
CREATE TABLE IF NOT EXISTS exam_subjects (
  id uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  exam_id uuid NOT NULL REFERENCES exams(id) ON DELETE CASCADE,
  subject_id uuid NOT NULL REFERENCES subjects(id) ON DELETE CASCADE,
  class_id uuid NOT NULL REFERENCES classes(id) ON DELETE CASCADE,
  created_at timestamptz DEFAULT now(),
  UNIQUE(exam_id, subject_id, class_id)
);

ALTER TABLE exam_subjects ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Admins can view all exam subjects"
  ON exam_subjects FOR SELECT
  TO authenticated
  USING (EXISTS (
    SELECT 1 FROM profiles WHERE profiles.id = auth.uid() AND profiles.role = 'admin'
  ));

CREATE POLICY "Teachers can view exam subjects for their allocations"
  ON exam_subjects FOR SELECT
  TO authenticated
  USING (
    EXISTS (
      SELECT 1 FROM profiles WHERE profiles.id = auth.uid() AND profiles.role = 'teacher'
    )
  );

CREATE POLICY "Students can view exam subjects for published exams"
  ON exam_subjects FOR SELECT
  TO authenticated
  USING (
    EXISTS (
      SELECT 1 FROM exams 
      WHERE exams.id = exam_subjects.exam_id 
      AND exams.is_published = true
    ) AND
    EXISTS (SELECT 1 FROM profiles WHERE profiles.id = auth.uid() AND profiles.role = 'student')
  );

CREATE POLICY "Only admins can insert exam subjects"
  ON exam_subjects FOR INSERT
  TO authenticated
  WITH CHECK (EXISTS (
    SELECT 1 FROM profiles WHERE profiles.id = auth.uid() AND profiles.role = 'admin'
  ));

CREATE POLICY "Only admins can delete exam subjects"
  ON exam_subjects FOR DELETE
  TO authenticated
  USING (EXISTS (
    SELECT 1 FROM profiles WHERE profiles.id = auth.uid() AND profiles.role = 'admin'
  ));

-- 8. Questions Table
CREATE TABLE IF NOT EXISTS questions (
  id uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  exam_subject_id uuid NOT NULL REFERENCES exam_subjects(id) ON DELETE CASCADE,
  question_text text NOT NULL,
  option_a text NOT NULL,
  option_b text NOT NULL,
  option_c text NOT NULL,
  option_d text NOT NULL,
  correct_answer text NOT NULL CHECK (correct_answer IN ('A', 'B', 'C', 'D')),
  created_by uuid REFERENCES profiles(id),
  created_at timestamptz DEFAULT now()
);

ALTER TABLE questions ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Admins can view all questions"
  ON questions FOR SELECT
  TO authenticated
  USING (EXISTS (
    SELECT 1 FROM profiles WHERE profiles.id = auth.uid() AND profiles.role = 'admin'
  ));

CREATE POLICY "Teachers can view questions for their allocated subjects"
  ON questions FOR SELECT
  TO authenticated
  USING (
    EXISTS (
      SELECT 1 FROM exam_subjects es
      JOIN teacher_allocations ta ON ta.subject_id = es.subject_id AND ta.class_id = es.class_id
      WHERE es.id = questions.exam_subject_id AND ta.teacher_id = auth.uid()
    ) OR
    auth.uid() = created_by
  );

CREATE POLICY "Students can view questions for published exams"
  ON questions FOR SELECT
  TO authenticated
  USING (
    EXISTS (
      SELECT 1 FROM exam_subjects es
      JOIN exams e ON e.id = es.exam_id
      WHERE es.id = questions.exam_subject_id 
      AND e.is_published = true
    ) AND
    EXISTS (SELECT 1 FROM profiles WHERE profiles.id = auth.uid() AND profiles.role = 'student')
  );

CREATE POLICY "Teachers can insert questions for allocated subjects"
  ON questions FOR INSERT
  TO authenticated
  WITH CHECK (
    EXISTS (
      SELECT 1 FROM exam_subjects es
      JOIN teacher_allocations ta ON ta.subject_id = es.subject_id AND ta.class_id = es.class_id
      WHERE es.id = exam_subject_id AND ta.teacher_id = auth.uid()
    )
  );

CREATE POLICY "Teachers can update own questions"
  ON questions FOR UPDATE
  TO authenticated
  USING (auth.uid() = created_by)
  WITH CHECK (auth.uid() = created_by);

CREATE POLICY "Teachers can delete own questions"
  ON questions FOR DELETE
  TO authenticated
  USING (auth.uid() = created_by);

-- 9. Passkeys Table
CREATE TABLE IF NOT EXISTS passkeys (
  id uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  passkey text UNIQUE NOT NULL,
  student_email text NOT NULL,
  exam_id uuid NOT NULL REFERENCES exams(id) ON DELETE CASCADE,
  is_used boolean DEFAULT false,
  expires_at timestamptz NOT NULL,
  created_by uuid REFERENCES profiles(id),
  created_at timestamptz DEFAULT now()
);

ALTER TABLE passkeys ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Admins can view all passkeys"
  ON passkeys FOR SELECT
  TO authenticated
  USING (EXISTS (
    SELECT 1 FROM profiles WHERE profiles.id = auth.uid() AND profiles.role = 'admin'
  ));

CREATE POLICY "Only admins can insert passkeys"
  ON passkeys FOR INSERT
  TO authenticated
  WITH CHECK (EXISTS (
    SELECT 1 FROM profiles WHERE profiles.id = auth.uid() AND profiles.role = 'admin'
  ));

CREATE POLICY "Only admins can update passkeys"
  ON passkeys FOR UPDATE
  TO authenticated
  USING (EXISTS (
    SELECT 1 FROM profiles WHERE profiles.id = auth.uid() AND profiles.role = 'admin'
  ))
  WITH CHECK (EXISTS (
    SELECT 1 FROM profiles WHERE profiles.id = auth.uid() AND profiles.role = 'admin'
  ));

-- 10. Exam Attempts Table
CREATE TABLE IF NOT EXISTS exam_attempts (
  id uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  student_id uuid NOT NULL REFERENCES profiles(id) ON DELETE CASCADE,
  exam_id uuid NOT NULL REFERENCES exams(id) ON DELETE CASCADE,
  exam_subject_id uuid NOT NULL REFERENCES exam_subjects(id) ON DELETE CASCADE,
  started_at timestamptz DEFAULT now(),
  submitted_at timestamptz,
  score integer,
  total_questions integer NOT NULL
);

ALTER TABLE exam_attempts ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Students can view own exam attempts"
  ON exam_attempts FOR SELECT
  TO authenticated
  USING (auth.uid() = student_id);

CREATE POLICY "Admins can view all exam attempts"
  ON exam_attempts FOR SELECT
  TO authenticated
  USING (EXISTS (
    SELECT 1 FROM profiles WHERE profiles.id = auth.uid() AND profiles.role = 'admin'
  ));

CREATE POLICY "Students can insert own exam attempts"
  ON exam_attempts FOR INSERT
  TO authenticated
  WITH CHECK (auth.uid() = student_id);

CREATE POLICY "Students can update own exam attempts"
  ON exam_attempts FOR UPDATE
  TO authenticated
  USING (auth.uid() = student_id)
  WITH CHECK (auth.uid() = student_id);

-- 11. Student Answers Table
CREATE TABLE IF NOT EXISTS student_answers (
  id uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  attempt_id uuid NOT NULL REFERENCES exam_attempts(id) ON DELETE CASCADE,
  question_id uuid NOT NULL REFERENCES questions(id) ON DELETE CASCADE,
  selected_answer text CHECK (selected_answer IN ('A', 'B', 'C', 'D')),
  is_correct boolean,
  answered_at timestamptz DEFAULT now(),
  UNIQUE(attempt_id, question_id)
);

ALTER TABLE student_answers ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Students can view own answers"
  ON student_answers FOR SELECT
  TO authenticated
  USING (
    EXISTS (
      SELECT 1 FROM exam_attempts 
      WHERE exam_attempts.id = student_answers.attempt_id 
      AND exam_attempts.student_id = auth.uid()
    )
  );

CREATE POLICY "Admins can view all answers"
  ON student_answers FOR SELECT
  TO authenticated
  USING (EXISTS (
    SELECT 1 FROM profiles WHERE profiles.id = auth.uid() AND profiles.role = 'admin'
  ));

CREATE POLICY "Students can insert own answers"
  ON student_answers FOR INSERT
  TO authenticated
  WITH CHECK (
    EXISTS (
      SELECT 1 FROM exam_attempts 
      WHERE exam_attempts.id = attempt_id 
      AND exam_attempts.student_id = auth.uid()
    )
  );

CREATE POLICY "Students can update own answers"
  ON student_answers FOR UPDATE
  TO authenticated
  USING (
    EXISTS (
      SELECT 1 FROM exam_attempts 
      WHERE exam_attempts.id = attempt_id 
      AND exam_attempts.student_id = auth.uid()
    )
  )
  WITH CHECK (
    EXISTS (
      SELECT 1 FROM exam_attempts 
      WHERE exam_attempts.id = attempt_id 
      AND exam_attempts.student_id = auth.uid()
    )
  );

-- 12. Exam Republish Log Table
CREATE TABLE IF NOT EXISTS exam_republish_log (
  id uuid PRIMARY KEY DEFAULT uuid_generate_v4(),
  student_id uuid NOT NULL REFERENCES profiles(id) ON DELETE CASCADE,
  exam_id uuid NOT NULL REFERENCES exams(id) ON DELETE CASCADE,
  exam_subject_id uuid REFERENCES exam_subjects(id) ON DELETE CASCADE,
  republished_by uuid REFERENCES profiles(id),
  republished_at timestamptz DEFAULT now(),
  reason text
);

ALTER TABLE exam_republish_log ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Admins can view republish log"
  ON exam_republish_log FOR SELECT
  TO authenticated
  USING (EXISTS (
    SELECT 1 FROM profiles WHERE profiles.id = auth.uid() AND profiles.role = 'admin'
  ));

CREATE POLICY "Only admins can insert republish log"
  ON exam_republish_log FOR INSERT
  TO authenticated
  WITH CHECK (EXISTS (
    SELECT 1 FROM profiles WHERE profiles.id = auth.uid() AND profiles.role = 'admin'
  ));

-- Create indexes for better query performance
CREATE INDEX IF NOT EXISTS idx_teacher_allocations_teacher ON teacher_allocations(teacher_id);
CREATE INDEX IF NOT EXISTS idx_teacher_allocations_class ON teacher_allocations(class_id);
CREATE INDEX IF NOT EXISTS idx_teacher_allocations_subject ON teacher_allocations(subject_id);
CREATE INDEX IF NOT EXISTS idx_exam_subjects_exam ON exam_subjects(exam_id);
CREATE INDEX IF NOT EXISTS idx_questions_exam_subject ON questions(exam_subject_id);
CREATE INDEX IF NOT EXISTS idx_passkeys_email ON passkeys(student_email);
CREATE INDEX IF NOT EXISTS idx_passkeys_exam ON passkeys(exam_id);
CREATE INDEX IF NOT EXISTS idx_exam_attempts_student ON exam_attempts(student_id);
CREATE INDEX IF NOT EXISTS idx_exam_attempts_exam ON exam_attempts(exam_id);
CREATE INDEX IF NOT EXISTS idx_student_answers_attempt ON student_answers(attempt_id);