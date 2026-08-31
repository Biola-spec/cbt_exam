-- Update script to add missing columns to support new features
-- Adding usage_limit and exam_subject_id columns to passkeys table
-- Adding time_limit and is_published columns to exam_subjects table
-- Adding default_time_limit column to school_config table

-- Add columns to passkeys table
ALTER TABLE passkeys 
ADD COLUMN IF NOT EXISTS usage_limit INTEGER DEFAULT 1,
ADD COLUMN IF NOT EXISTS exam_subject_id UUID REFERENCES exam_subjects(id) ON DELETE CASCADE;

-- Add columns to exam_subjects table
ALTER TABLE exam_subjects 
ADD COLUMN IF NOT EXISTS time_limit INTEGER DEFAULT 60,
ADD COLUMN IF NOT EXISTS is_published BOOLEAN DEFAULT false,
ADD COLUMN IF NOT EXISTS published_at TIMESTAMP WITH TIME ZONE;

-- Add column to school_config table
ALTER TABLE school_config 
ADD COLUMN IF NOT EXISTS default_time_limit INTEGER DEFAULT 60;

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_passkeys_exam_subject ON passkeys(exam_subject_id);
CREATE INDEX IF NOT EXISTS idx_exam_subjects_published ON exam_subjects(is_published);