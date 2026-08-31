-- Update script to remove passkey columns from database
-- This script should be run after removing the passkeys table

-- Remove any references to passkey columns in other tables (if any were added)
-- In this case, there are no additional columns in other tables that reference passkeys