-- Allows one staff account to have both Teacher and Admin permissions.
ALTER TABLE `users`
  MODIFY `role` ENUM('admin','teacher','student','admin_teacher') NOT NULL;
