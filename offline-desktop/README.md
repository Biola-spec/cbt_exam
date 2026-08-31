# CBT Offline Desktop

This is the installable Windows offline client for the CBT Exam System.

It is separate from the current PHP/MySQL web app. The desktop app stores data locally in SQLite and synchronizes with the online server when internet is available.

## What Works In This Foundation

- Electron desktop shell
- Local SQLite database path inside the user's app data folder
- Schema migration on first launch
- Offline login against synced users
- Offline exam list, attempt creation, answer saving, and submission
- Sync service foundation for pulling server data and pushing local changes

## Install Developer Dependencies

From this folder:

```powershell
npm install
```

## Run In Development

```powershell
npm start
```

## Build Windows Installer

```powershell
npm run build:win
```

The installer will be created in `offline-desktop/release`.

If the old release folder is locked by Windows, build to a fresh folder:

```powershell
npx electron-builder --win nsis --config.directories.output=release-online-look
```

## Offline USB Workflow

1. On the offline computer, login as `admin@cbtexam.com` with `Admin@2024`.
2. Set the online server URL in **Settings**. For the hosted server, use the CBT root URL, for example `https://cbt.famaiis.com`.
3. Use **Import From Online** to pull classes, subjects, students, exams, questions, and comprehension passages directly from the online CBT server.
4. If the offline computer cannot connect to the internet, download `api/sync/export.php` from the online/server computer, move the JSON file by USB, then use **Import JSON Backup**.
5. Students login with their email and subject code. Student password is not required, matching the current PHP web app.
6. After exams, login as admin and use **Export Results** to save pending offline attempts and answers to JSON, or use **Sync** when internet is available.

## Offline Teacher Workflow

- Teachers can login with a local teacher account, for example `teacher@cbtexam.com` / `Teacher@2024` on a fresh demo install.
- Teachers can use **Import From Online** to pull already-set exams and questions from the online server into the offline app.
- Teachers can open **Question Management** to add, edit, or delete questions for imported exam subjects. These offline question changes are available immediately for students on that device.
- Admins can also open **Teacher Section** from the admin dashboard when teacher login is not available on the offline device.

## Sync Direction

The intended conflict rules are:

- Server wins for master data: classes, subjects, users, exams, exam subjects, questions, passages.
- Local client wins for exam activity: attempts, answers, submitted results.
- Submitted attempts are immutable after submission.

The online PHP app will need matching sync endpoints before real production sync is enabled.
