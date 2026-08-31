USE cbt_exam_system;

INSERT INTO users (email, password, full_name, role) VALUES
  ('admin@cbtexam.com', '$2y$10$xfxGNsab4BQBiQFYoV7dy.HEaWSCaCFOvqK.Za23fn6QWhiAv1yPK', 'System Administrator', 'admin'),
  ('teacher@cbtexam.com', '$2y$10$Ki3.oHWxfESvnxDa9IL4F.J77JCRkXbwaulzeguNnyfkTy6RCbML2', 'Sample Teacher', 'teacher');

INSERT INTO school_config (school_name, default_time_limit) VALUES
  ('CBT Exam School', 60);

INSERT INTO classes (name, description) VALUES
  ('JSS 1', 'Junior Secondary School 1'),
  ('JSS 2', 'Junior Secondary School 2'),
  ('JSS 3', 'Junior Secondary School 3');

INSERT INTO subjects (name, subject_code, description) VALUES
  ('Mathematics', 'MATH', 'Mathematics subject'),
  ('English Language', 'ENG', 'English Language subject'),
  ('Civic Education', 'CIV', 'Civic Education subject');

INSERT INTO teacher_allocations (teacher_id, class_id, subject_id, allocated_by) VALUES
  (2, 2, 2, 1);
