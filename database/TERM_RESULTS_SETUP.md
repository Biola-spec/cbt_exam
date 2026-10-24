# Term results setup

Apply `term_results.sql` to the existing `cbt_exam_system` database. It adds the student class link on older installations that do not already have `users.class_id`. The new admin page is `admin/term_results.php`; teachers use `teacher/term_scores.php`, and students use `student/term_results.php`.

Apply `class_teacher_attendance.sql` after `term_results.sql` to enable multiple class-teacher assignments, daily attendance, and per-student term remarks. Admins assign teachers in Admin Dashboard → Classes. Assigned staff use `teacher/class_duties.php` for daily attendance and remarks. In Report Card Settings, admins set days school opened for each session and term and define custom psychomotor/locomotor rating fields. Class teachers enter each student's days present and 1–5 skill ratings. Report cards show the attendance totals, skill ratings, and class-teacher remark.

Apply `report_card_signatory.sql` to enable the shared school stamp, logo watermarks, one per-class report signatory, and report-authority remarks. In Admin Dashboard → Classes, appoint one teacher or admin per class, enter a title (such as Principal or a custom designation), and upload that appointee's signature. The appointed staff member uses Class Duties → Report authority remarks to comment for each student and term. The matching remark, title, staff name, signature and stamp are displayed on the report card.

Admins create a school session, set the current session, and choose the number, display name, and maximum mark for each CA and exam component in each class. Sessions contain First, Second, and Third Term result sheets.

Teachers can edit results only for their allocated class and subject. Submitting scores marks each complete student-subject result as awaiting admin release. The admin reviews a term sheet and can release or recall each student-subject result independently. Students see only their individually released results; their dashboard shows a notification when one or more results are out.

Teachers see released, awaiting-release, and not-submitted/recalled counts on the dashboard. Each score sheet also shows the release state for every student. A released student row is locked until the admin recalls that result; other student rows remain editable.

Reapply term_results.sql on existing installations to add the per-result release fields and preserve any previously approved term sheets as released results. The migration is safe to rerun.

Apply `exam_result_component_link.sql` to let admins link each CBT exam class to a CA or exam component in a session and term. Add the exam subjects first, then set a separate link for each class under the exam. The class link applies to every subject in that class and uses its configured component name and maximum mark. Submitted CBT scores are imported into the matching component on the teacher score sheet, scaled to that class's configured maximum, and remain editable before saving.
