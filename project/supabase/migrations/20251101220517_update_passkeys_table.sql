/*
  # Update Passkeys Table

  ## Overview
  This migration updates the passkeys table to add two new columns:
  - `usage_limit` (integer): Limits how many times a passkey can be used (0 for unlimited)
  - `exam_subject_id` (uuid): Optional reference to a specific exam subject

  ## Changes

  ### 1. Add `usage_limit` column
  - Type: integer
  - Default: 1 (single use)
  - Not null: false (to maintain backward compatibility)

  ### 2. Add `exam_subject_id` column
  - Type: uuid
  - References: exam_subjects(id) with ON DELETE CASCADE
  - Not null: false (optional)

  ### 3. Add foreign key constraint for `exam_subject_id`
  - References exam_subjects table
  - ON DELETE CASCADE

  ### 4. Add index for better performance
  - Index on exam_subject_id column
*/

-- Add usage_limit column
ALTER TABLE passkeys ADD COLUMN IF NOT EXISTS usage_limit INTEGER DEFAULT 1;

-- Add exam_subject_id column
ALTER TABLE passkeys ADD COLUMN IF NOT EXISTS exam_subject_id UUID REFERENCES exam_subjects(id) ON DELETE CASCADE;

-- Add index for better performance on exam_subject_id
CREATE INDEX IF NOT EXISTS idx_passkeys_exam_subject ON passkeys(exam_subject_id);

-- Update the Passkey type in supabase.ts to reflect these changes
/*
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
*/