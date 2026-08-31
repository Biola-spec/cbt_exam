import { createClient } from '@supabase/supabase-js';

const supabaseUrl = import.meta.env.VITE_SUPABASE_URL;
const supabaseAnonKey = import.meta.env.VITE_SUPABASE_ANON_KEY;

export const supabase = createClient(supabaseUrl, supabaseAnonKey);

export type UserRole = 'admin' | 'teacher' | 'student';

export interface Profile {
  id: string;
  email: string;
  full_name: string;
  role: UserRole;
  created_at: string;
}

export interface SchoolConfig {
  id: string;
  school_name: string;
  default_time_limit?: number;
  updated_at: string;
  updated_by: string;
}

export interface Class {
  id: string;
  name: string;
  description?: string;
  created_at: string;
}

export interface Subject {
  id: string;
  name: string;
  description?: string;
  created_at: string;
}

export interface TeacherAllocation {
  id: string;
  teacher_id: string;
  class_id: string;
  subject_id: string;
  allocated_at: string;
  allocated_by: string;
}

export interface Exam {
  id: string;
  title: string;
  description?: string;
  is_published: boolean;
  published_at?: string;
  created_by: string;
  created_at: string;
}

export interface ExamSubject {
  id: string;
  exam_id: string;
  subject_id: string;
  class_id: string;
  is_published?: boolean;
  published_at?: string;
  time_limit?: number;
  created_at: string;
}

export interface Question {
  id: string;
  exam_subject_id: string;
  question_text: string;
  option_a: string;
  option_b: string;
  option_c: string;
  option_d: string;
  correct_answer: 'A' | 'B' | 'C' | 'D';
  created_by: string;
  created_at: string;
}

export interface Passkey {
  id: string;
  passkey: string;
  student_email: string;
  exam_id: string;
  exam_subject_id?: string;
  usage_limit: number;
  is_used: boolean;
  expires_at: string;
  created_by: string;
  created_at: string;
}

export interface ExamAttempt {
  id: string;
  student_id: string;
  exam_id: string;
  exam_subject_id: string;
  started_at: string;
  submitted_at?: string;
  score?: number;
  total_questions: number;
}

export interface StudentAnswer {
  id: string;
  attempt_id: string;
  question_id: string;
  selected_answer?: 'A' | 'B' | 'C' | 'D';
  is_correct?: boolean;
  answered_at: string;
}