const crypto = require('crypto');
const { now } = require('./database');

function createExamService(db) {
  let session = null;

  function login({ email, subjectCode }) {
    const user = db.prepare(`
      SELECT id, email, full_name, role, class_id, subject_code
      FROM users
      WHERE lower(email) = lower(?) AND deleted_at IS NULL
    `).get(email || '');

    if (!user) {
      return { ok: false, message: 'Invalid login details for this offline device.' };
    }

    if (user.role !== 'student') {
      return { ok: false, message: 'Only student login is allowed on this offline app.' };
    }

    const cleanSubjectCode = String(subjectCode || '').trim();
    if (!cleanSubjectCode) {
      return { ok: false, message: 'Enter the subject code for this exam.' };
    }

    const subject = db.prepare(`
      SELECT subject_code
      FROM subjects
      WHERE subject_code = ? AND deleted_at IS NULL
      LIMIT 1
    `).get(cleanSubjectCode);

    if (!subject) {
      return { ok: false, message: 'Invalid email or subject code.' };
    }

    session = {
      userId: user.id,
      email: user.email,
      fullName: user.full_name,
      role: user.role,
      classId: user.class_id,
      subjectCode: cleanSubjectCode
    };

    return { ok: true, session };
  }

  function logout() {
    session = null;
    return { ok: true };
  }

  function getSession() {
    return session;
  }

  function requireSession() {
    if (!session) {
      throw new Error('You must login first.');
    }
    return session;
  }

  function getDashboard() {
    const activeSession = requireSession();

    if (activeSession.role !== 'student') {
      return {
        session: activeSession,
        exams: [],
        attempts: getRecentAttempts(activeSession.userId),
        pendingSync: getPendingSyncCount()
      };
    }

    const exams = db.prepare(`
      SELECT
        es.id AS exam_subject_id,
        es.time_limit,
        e.id AS exam_id,
        e.title,
        e.description,
        c.name AS class_name,
        s.name AS subject_name,
        s.subject_code
      FROM exam_subjects es
      JOIN exams e ON e.id = es.exam_id
      JOIN classes c ON c.id = es.class_id
      JOIN subjects s ON s.id = es.subject_id
      WHERE e.is_published = 1
        AND es.is_published = 1
        AND es.deleted_at IS NULL
        AND e.deleted_at IS NULL
        AND es.class_id = ?
        AND s.subject_code = ?
      ORDER BY e.published_at DESC, e.title ASC
    `).all(activeSession.classId, activeSession.subjectCode);

    const decoratedExams = exams.map((exam) => {
      const submitted = db.prepare(`
        SELECT id, submitted_at, score, total_questions
        FROM exam_attempts
        WHERE student_id = ? AND exam_subject_id = ? AND submitted_at IS NOT NULL
        ORDER BY submitted_at DESC
        LIMIT 1
      `).get(activeSession.userId, exam.exam_subject_id);

      const active = db.prepare(`
        SELECT id, started_at, total_questions
        FROM exam_attempts
        WHERE student_id = ? AND exam_subject_id = ? AND submitted_at IS NULL
        ORDER BY started_at DESC
        LIMIT 1
      `).get(activeSession.userId, exam.exam_subject_id);

      if (submitted) {
        return { ...exam, status: 'completed', attempt: submitted };
      }

      if (active) {
        return { ...exam, status: 'in_progress', attempt: active };
      }

      return { ...exam, status: 'available', attempt: null };
    });

    return {
      session: activeSession,
      exams: decoratedExams,
      attempts: getRecentAttempts(activeSession.userId),
      pendingSync: getPendingSyncCount()
    };
  }

  function requireTeacherSession() {
    const activeSession = requireSession();
    if (activeSession.role !== 'teacher' && activeSession.role !== 'admin') {
      throw new Error('Teacher access is required.');
    }
    return activeSession;
  }

  function getTeacherExamSubjects() {
    requireTeacherSession();

    return db.prepare(`
      SELECT
        es.id AS exam_subject_id,
        es.time_limit,
        es.is_published,
        e.id AS exam_id,
        e.title,
        e.description,
        c.name AS class_name,
        s.name AS subject_name,
        s.subject_code,
        COUNT(q.id) AS question_count
      FROM exam_subjects es
      JOIN exams e ON e.id = es.exam_id
      JOIN classes c ON c.id = es.class_id
      JOIN subjects s ON s.id = es.subject_id
      LEFT JOIN questions q ON q.exam_subject_id = es.id AND q.deleted_at IS NULL
      WHERE es.deleted_at IS NULL AND e.deleted_at IS NULL
      GROUP BY es.id
      ORDER BY e.title ASC, c.name ASC, s.name ASC
    `).all();
  }

  function getTeacherQuestions(examSubjectId) {
    requireTeacherSession();

    const examSubject = db.prepare(`
      SELECT
        es.id AS exam_subject_id,
        es.time_limit,
        e.title,
        c.name AS class_name,
        s.name AS subject_name
      FROM exam_subjects es
      JOIN exams e ON e.id = es.exam_id
      JOIN classes c ON c.id = es.class_id
      JOIN subjects s ON s.id = es.subject_id
      WHERE es.id = ? AND es.deleted_at IS NULL
    `).get(examSubjectId);

    if (!examSubject) {
      throw new Error('Exam subject not found on this device.');
    }

    const questions = db.prepare(`
      SELECT *
      FROM questions
      WHERE exam_subject_id = ? AND deleted_at IS NULL
      ORDER BY order_position ASC
    `).all(examSubjectId);

    return { examSubject, questions };
  }

  function saveTeacherQuestion(payload) {
    requireTeacherSession();

    const question = normalizeQuestionPayload(payload);
    const exists = question.id
      ? db.prepare('SELECT id FROM questions WHERE id = ?').get(question.id)
      : null;

    if (exists) {
      db.prepare(`
        UPDATE questions
        SET question_text = @question_text,
            option_a = @option_a,
            option_b = @option_b,
            option_c = @option_c,
            option_d = @option_d,
            option_e = @option_e,
            option_f = @option_f,
            correct_answer = @correct_answer,
            updated_at = @updated_at,
            deleted_at = NULL
        WHERE id = @id
      `).run(question);
    } else {
      question.id = crypto.randomUUID();
      question.server_id = null;
      question.order_position = nextQuestionPosition(db, question.exam_subject_id);
      db.prepare(`
        INSERT INTO questions (
          id, server_id, exam_subject_id, order_position, question_text,
          option_a, option_b, option_c, option_d, option_e, option_f,
          correct_answer, updated_at, deleted_at
        ) VALUES (
          @id, @server_id, @exam_subject_id, @order_position, @question_text,
          @option_a, @option_b, @option_c, @option_d, @option_e, @option_f,
          @correct_answer, @updated_at, NULL
        )
      `).run(question);
    }

    return {
      ok: true,
      message: exists ? 'Question updated.' : 'Question added.',
      data: getTeacherQuestions(question.exam_subject_id)
    };
  }

  function deleteTeacherQuestion(questionId) {
    requireTeacherSession();

    const question = db.prepare('SELECT id, exam_subject_id FROM questions WHERE id = ?').get(questionId);
    if (!question) {
      throw new Error('Question not found.');
    }

    db.prepare(`
      UPDATE questions
      SET deleted_at = ?, updated_at = ?
      WHERE id = ?
    `).run(now(), now(), question.id);

    return {
      ok: true,
      message: 'Question deleted.',
      data: getTeacherQuestions(question.exam_subject_id)
    };
  }

  function startAttempt(examSubjectId) {
    const activeSession = requireSession();
    const existing = db.prepare(`
      SELECT id FROM exam_attempts
      WHERE student_id = ? AND exam_subject_id = ? AND submitted_at IS NULL
      ORDER BY started_at DESC
      LIMIT 1
    `).get(activeSession.userId, examSubjectId);

    if (existing) {
      return getAttempt(existing.id);
    }

    const examSubject = db.prepare(`
      SELECT es.id, es.exam_id, es.time_limit
      FROM exam_subjects es
      WHERE es.id = ? AND es.deleted_at IS NULL
    `).get(examSubjectId);

    if (!examSubject) {
      throw new Error('Exam is not available on this device.');
    }

    const totalQuestions = db.prepare(`
      SELECT COUNT(*) AS count
      FROM questions
      WHERE exam_subject_id = ? AND deleted_at IS NULL
    `).get(examSubjectId).count;

    const attempt = {
      id: crypto.randomUUID(),
      student_id: activeSession.userId,
      exam_id: examSubject.exam_id,
      exam_subject_id: examSubject.id,
      started_at: now(),
      submitted_at: null,
      score: null,
      total_questions: totalQuestions,
      sync_status: 'pending',
      updated_at: now()
    };

    db.prepare(`
      INSERT INTO exam_attempts (
        id, student_id, exam_id, exam_subject_id, started_at, submitted_at,
        score, total_questions, sync_status, updated_at
      ) VALUES (
        @id, @student_id, @exam_id, @exam_subject_id, @started_at, @submitted_at,
        @score, @total_questions, @sync_status, @updated_at
      )
    `).run(attempt);

    enqueue(db, 'exam_attempts', attempt.id, 'upsert', attempt);

    return getAttempt(attempt.id);
  }

  function getAttempt(attemptId) {
    const activeSession = requireSession();
    const attempt = db.prepare(`
      SELECT
        ea.*,
        es.time_limit,
        es.show_calculator,
        e.title,
        s.name AS subject_name
      FROM exam_attempts ea
      JOIN exam_subjects es ON es.id = ea.exam_subject_id
      JOIN exams e ON e.id = ea.exam_id
      JOIN subjects s ON s.id = es.subject_id
      WHERE ea.id = ? AND ea.student_id = ?
    `).get(attemptId, activeSession.userId);

    if (!attempt) {
      throw new Error('Attempt not found.');
    }

    const allQuestions = db.prepare(`
      SELECT *
      FROM questions
      WHERE exam_subject_id = ? AND deleted_at IS NULL
      ORDER BY order_position ASC
    `).all(attempt.exam_subject_id);

    allQuestions.forEach((q) => {
      q.original_number = Number(q.order_position) + 1;
    });

    const questions = seededShuffle(allQuestions, attempt.id);

    const answers = db.prepare(`
      SELECT question_id, selected_answer, is_correct
      FROM student_answers
      WHERE attempt_id = ?
    `).all(attempt.id);

    const answerMap = {};
    answers.forEach((answer) => {
      answerMap[answer.question_id] = answer;
    });

    const passages = db.prepare(`
      SELECT *
      FROM comprehension_passages
      WHERE exam_subject_id = ? AND deleted_at IS NULL
      ORDER BY start_question_number ASC
    `).all(attempt.exam_subject_id);

    return {
      attempt,
      questions,
      answers: answerMap,
      passages,
      timeRemainingSeconds: getTimeRemaining(attempt)
    };
  }

  function saveAnswer({ attemptId, questionId, selectedAnswer }) {
    const activeSession = requireSession();
    const attempt = db.prepare(`
      SELECT id, student_id, submitted_at
      FROM exam_attempts
      WHERE id = ?
    `).get(attemptId);

    if (!attempt || attempt.student_id !== activeSession.userId) {
      throw new Error('Attempt not found.');
    }

    if (attempt.submitted_at) {
      throw new Error('This exam has already been submitted.');
    }

    const question = db.prepare(`
      SELECT id, correct_answer
      FROM questions
      WHERE id = ?
    `).get(questionId);

    if (!question) {
      throw new Error('Question not found.');
    }

    const answer = {
      id: stableAnswerId(attemptId, questionId),
      attempt_id: attemptId,
      question_id: questionId,
      selected_answer: selectedAnswer,
      is_correct: selectedAnswer === question.correct_answer ? 1 : 0,
      answered_at: now(),
      sync_status: 'pending',
      updated_at: now()
    };

    db.prepare(`
      INSERT INTO student_answers (
        id, attempt_id, question_id, selected_answer, is_correct, answered_at, sync_status, updated_at
      ) VALUES (
        @id, @attempt_id, @question_id, @selected_answer, @is_correct, @answered_at, @sync_status, @updated_at
      )
      ON CONFLICT(attempt_id, question_id) DO UPDATE SET
        selected_answer = excluded.selected_answer,
        is_correct = excluded.is_correct,
        answered_at = excluded.answered_at,
        sync_status = 'pending',
        updated_at = excluded.updated_at
    `).run(answer);

    enqueue(db, 'student_answers', answer.id, 'upsert', answer);

    return { ok: true };
  }

  function submitAttempt(attemptId) {
    const activeSession = requireSession();
    const attemptData = getAttempt(attemptId);
    const attempt = attemptData.attempt;

    if (attempt.student_id !== activeSession.userId) {
      throw new Error('Attempt not found.');
    }

    if (attempt.submitted_at) {
      return attemptData;
    }

    const classData = db.prepare(`
      SELECT c.marks_per_question
      FROM exam_subjects es
      JOIN classes c ON c.id = es.class_id
      WHERE es.id = ?
    `).get(attempt.exam_subject_id);

    const marksPerQuestion = classData ? Number(classData.marks_per_question || 1) : 1;
    const correct = db.prepare(`
      SELECT COUNT(*) AS count
      FROM student_answers
      WHERE attempt_id = ? AND is_correct = 1
    `).get(attempt.id).count;

    const submitted = {
      id: attempt.id,
      submitted_at: now(),
      score: correct * marksPerQuestion,
      sync_status: 'pending',
      updated_at: now()
    };

    db.prepare(`
      UPDATE exam_attempts
      SET submitted_at = @submitted_at,
          score = @score,
          sync_status = @sync_status,
          updated_at = @updated_at
      WHERE id = @id
    `).run(submitted);

    const updatedAttempt = db.prepare('SELECT * FROM exam_attempts WHERE id = ?').get(attempt.id);
    enqueue(db, 'exam_attempts', attempt.id, 'upsert', updatedAttempt);

    return getAttempt(attempt.id);
  }

  function getRecentAttempts(studentId) {
    return db.prepare(`
      SELECT
        ea.id,
        ea.score,
        ea.total_questions,
        ea.submitted_at,
        ea.sync_status,
        e.title,
        s.name AS subject_name
      FROM exam_attempts ea
      JOIN exams e ON e.id = ea.exam_id
      JOIN exam_subjects es ON es.id = ea.exam_subject_id
      JOIN subjects s ON s.id = es.subject_id
      WHERE ea.student_id = ? AND ea.submitted_at IS NOT NULL
      ORDER BY ea.submitted_at DESC
      LIMIT 20
    `).all(studentId);
  }

  function getPendingSyncCount() {
    return db.prepare('SELECT COUNT(*) AS count FROM sync_queue WHERE status = ?').get('pending').count;
  }

  return {
    login,
    logout,
    getSession,
    getDashboard,
    getTeacherExamSubjects,
    getTeacherQuestions,
    saveTeacherQuestion,
    deleteTeacherQuestion,
    startAttempt,
    getAttempt,
    saveAnswer,
    submitAttempt
  };
}

function normalizeQuestionPayload(payload) {
  const question = {
    id: payload.id || null,
    server_id: payload.server_id || null,
    exam_subject_id: payload.examSubjectId || payload.exam_subject_id,
    order_position: Number(payload.order_position || 0),
    question_text: String(payload.question_text || '').trim(),
    option_a: String(payload.option_a || '').trim(),
    option_b: String(payload.option_b || '').trim(),
    option_c: String(payload.option_c || '').trim(),
    option_d: String(payload.option_d || '').trim(),
    option_e: payload.option_e ? String(payload.option_e).trim() : null,
    option_f: payload.option_f ? String(payload.option_f).trim() : null,
    correct_answer: String(payload.correct_answer || '').trim().toUpperCase(),
    updated_at: now(),
    deleted_at: null
  };

  if (!question.exam_subject_id) throw new Error('Choose an exam subject first.');
  if (!question.question_text || !question.option_a || !question.option_b || !question.option_c || !question.option_d) {
    throw new Error('Question text and options A to D are required.');
  }
  if (!['A', 'B', 'C', 'D', 'E', 'F'].includes(question.correct_answer)) {
    throw new Error('Choose a valid correct answer.');
  }

  return question;
}

function nextQuestionPosition(db, examSubjectId) {
  return db.prepare(`
    SELECT COALESCE(MAX(order_position), -1) + 1 AS next_position
    FROM questions
    WHERE exam_subject_id = ? AND deleted_at IS NULL
  `).get(examSubjectId).next_position;
}

function getTimeRemaining(attempt) {
  if (attempt.submitted_at) return 0;
  const started = new Date(attempt.started_at).getTime();
  const elapsedSeconds = Math.floor((Date.now() - started) / 1000);
  return Math.max(0, (attempt.time_limit * 60) - elapsedSeconds);
}

function seededShuffle(items, seed) {
  const list = [...items];
  let state = hashSeed(seed);

  for (let i = list.length - 1; i > 0; i--) {
    state = (state * 1664525 + 1013904223) >>> 0;
    const j = state % (i + 1);
    [list[i], list[j]] = [list[j], list[i]];
  }

  return list;
}

function hashSeed(seed) {
  const hash = crypto.createHash('sha256').update(String(seed)).digest();
  return hash.readUInt32LE(0);
}

function stableAnswerId(attemptId, questionId) {
  return crypto.createHash('sha256').update(`${attemptId}:${questionId}`).digest('hex');
}

function enqueue(db, entityType, entityId, operation, payload) {
  const item = {
    id: crypto.randomUUID(),
    entity_type: entityType,
    entity_id: entityId,
    operation,
    payload: JSON.stringify(payload),
    status: 'pending',
    attempts: 0,
    last_error: null,
    created_at: now(),
    updated_at: now()
  };

  db.prepare(`
    INSERT INTO sync_queue (
      id, entity_type, entity_id, operation, payload, status,
      attempts, last_error, created_at, updated_at
    ) VALUES (
      @id, @entity_type, @entity_id, @operation, @payload, @status,
      @attempts, @last_error, @created_at, @updated_at
    )
  `).run(item);
}

module.exports = {
  createExamService
};
