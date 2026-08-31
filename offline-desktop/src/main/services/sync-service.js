const fs = require('fs');
const { now } = require('./database');

const MASTER_TABLES = [
  'classes',
  'subjects',
  'users',
  'exams',
  'exam_subjects',
  'questions',
  'comprehension_passages'
];

function createSyncService(db, store) {
  function getStatus() {
    const pending = db.prepare('SELECT COUNT(*) AS count FROM sync_queue WHERE status = ?').get('pending').count;
    const failed = db.prepare('SELECT COUNT(*) AS count FROM sync_queue WHERE status = ?').get('failed').count;
    const lastSync = db.prepare('SELECT value FROM app_meta WHERE key = ?').get('last_sync_at');

    return {
      serverUrl: store.get('serverUrl') || '',
      deviceName: store.get('deviceName') || 'CBT Offline Client',
      pending,
      failed,
      lastSyncAt: lastSync ? lastSync.value : null
    };
  }

  async function runSync() {
    const serverUrl = normalizeServerUrl(store.get('serverUrl'));
    if (!serverUrl) {
      return {
        ok: false,
        message: 'Set the online server URL before syncing.',
        status: getStatus()
      };
    }

    try {
      const pulled = await pullMasterData(serverUrl);
      const pushed = await pushQueue(serverUrl);

      db.prepare(`
        INSERT INTO app_meta (key, value)
        VALUES ('last_sync_at', ?)
        ON CONFLICT(key) DO UPDATE SET value = excluded.value
      `).run(now());

      return {
        ok: true,
        message: `Sync complete. Pulled ${pulled} server records and pushed ${pushed} local changes.`,
        status: getStatus()
      };
    } catch (error) {
      return {
        ok: false,
        message: error.message || 'Sync failed.',
        status: getStatus()
      };
    }
  }

  async function importFromOnline() {
    const serverUrl = normalizeServerUrl(store.get('serverUrl'));
    if (!serverUrl) {
      return {
        ok: false,
        message: 'Set the online server URL before importing from online.',
        status: getStatus()
      };
    }

    try {
      const pulled = await pullMasterData(serverUrl);

      db.prepare(`
        INSERT INTO app_meta (key, value)
        VALUES ('last_sync_at', ?)
        ON CONFLICT(key) DO UPDATE SET value = excluded.value
      `).run(now());

      return {
        ok: true,
        message: `Imported ${pulled} records from the online CBT server.`,
        status: getStatus()
      };
    } catch (error) {
      return {
        ok: false,
        message: explainFetchError(error, serverUrl),
        status: getStatus()
      };
    }
  }

  function importFromFile(filePath) {
    try {
      const body = JSON.parse(fs.readFileSync(filePath, 'utf8'));
      const data = body.data || body;
      let count = 0;

      const applyImport = db.transaction(() => {
        MASTER_TABLES.forEach((table) => {
          const rows = Array.isArray(data[table]) ? data[table] : [];
          rows.forEach((row) => {
            upsertMasterRow(table, normalizeImportedRow(table, row));
            count++;
          });
        });

        db.prepare(`
          INSERT INTO app_meta (key, value)
          VALUES ('last_import_at', ?)
          ON CONFLICT(key) DO UPDATE SET value = excluded.value
        `).run(now());
      });

      applyImport();

      return {
        ok: true,
        message: `Imported ${count} offline records.`,
        status: getStatus()
      };
    } catch (error) {
      return {
        ok: false,
        message: error.message || 'Import failed.',
        status: getStatus()
      };
    }
  }

  function exportAttempts(filePath) {
    try {
      const attempts = db.prepare(`
        SELECT *
        FROM exam_attempts
        WHERE sync_status IN ('pending', 'failed')
        ORDER BY started_at ASC
      `).all();
      const attemptIds = attempts.map((attempt) => attempt.id);
      const answers = attemptIds.length
        ? db.prepare(`
            SELECT *
            FROM student_answers
            WHERE attempt_id IN (${attemptIds.map(() => '?').join(',')})
            ORDER BY answered_at ASC
          `).all(...attemptIds)
        : [];

      const payload = {
        ok: true,
        exported_at: now(),
        data: {
          exam_attempts: attempts,
          student_answers: answers
        }
      };

      fs.writeFileSync(filePath, JSON.stringify(payload, null, 2));

      return {
        ok: true,
        message: `Exported ${attempts.length} attempts and ${answers.length} answers.`,
        status: getStatus()
      };
    } catch (error) {
      return {
        ok: false,
        message: error.message || 'Export failed.',
        status: getStatus()
      };
    }
  }

  async function pullMasterData(serverUrl) {
    const sinceRow = db.prepare('SELECT value FROM app_meta WHERE key = ?').get('last_sync_at');
    const since = sinceRow ? sinceRow.value : null;

    const body = await postSyncJson(serverUrl, 'pull.php', {
      device_name: store.get('deviceName'),
      since
    });

    if (!body.ok) {
      throw new Error(body.message || 'Pull failed.');
    }

    const data = body.data || {};
    let count = 0;

    const applyPull = db.transaction(() => {
      MASTER_TABLES.forEach((table) => {
        const rows = data[table] || [];
        rows.forEach((row) => {
          upsertMasterRow(table, row);
          count++;
        });
      });
    });

    applyPull();
    return count;
  }

  async function pushQueue(serverUrl) {
    const items = db.prepare(`
      SELECT *
      FROM sync_queue
      WHERE status IN ('pending', 'failed')
      ORDER BY created_at ASC
      LIMIT 250
    `).all();

    if (items.length === 0) return 0;

    const body = await postSyncJson(serverUrl, 'push.php', {
      device_name: store.get('deviceName'),
      changes: items.map((item) => ({
        id: item.id,
        entity_type: item.entity_type,
        entity_id: item.entity_id,
        operation: item.operation,
        payload: enrichPushPayload(item.entity_type, JSON.parse(item.payload)),
        created_at: item.created_at
      }))
    });

    if (!body.ok) {
      markFailed(items, body.message || 'Server rejected push.');
      throw new Error(body.message || 'Push failed.');
    }

    const acceptedIds = new Set(body.accepted_ids || []);
    const serverIds = body.server_ids || {};
    const markSynced = db.transaction(() => {
      items.forEach((item) => {
        if (acceptedIds.has(item.id)) {
          db.prepare('UPDATE sync_queue SET status = ?, updated_at = ? WHERE id = ?').run('synced', now(), item.id);
          if (serverIds[item.entity_type] && serverIds[item.entity_type][item.entity_id]) {
            setServerId(item.entity_type, item.entity_id, serverIds[item.entity_type][item.entity_id]);
          }
          markEntitySynced(item.entity_type, item.entity_id);
        }
      });
    });

    markSynced();
    return acceptedIds.size;
  }

  function upsertMasterRow(table, row) {
    const columns = getColumns(table).filter((column) => Object.prototype.hasOwnProperty.call(row, column));
    if (!columns.includes('id')) {
      throw new Error(`Pulled ${table} row without id.`);
    }

    const placeholders = columns.map((column) => `@${column}`).join(', ');
    const updates = columns
      .filter((column) => column !== 'id')
      .map((column) => `${column} = excluded.${column}`)
      .join(', ');

    db.prepare(`
      INSERT INTO ${table} (${columns.join(', ')})
      VALUES (${placeholders})
      ON CONFLICT(id) DO UPDATE SET ${updates}
    `).run(row);
  }

  function markEntitySynced(entityType, entityId) {
    if (entityType === 'exam_attempts' || entityType === 'student_answers') {
      db.prepare(`UPDATE ${entityType} SET sync_status = ?, updated_at = ? WHERE id = ?`)
        .run('synced', now(), entityId);
    }
  }

  function enrichPushPayload(entityType, payload) {
    if (entityType === 'exam_attempts') {
      return {
        ...payload,
        student_server_id: lookupServerId('users', payload.student_id),
        exam_server_id: lookupServerId('exams', payload.exam_id),
        exam_subject_server_id: lookupServerId('exam_subjects', payload.exam_subject_id)
      };
    }

    if (entityType === 'student_answers') {
      return {
        ...payload,
        attempt_server_id: lookupServerId('exam_attempts', payload.attempt_id),
        question_server_id: lookupServerId('questions', payload.question_id)
      };
    }

    return payload;
  }

  function lookupServerId(table, id) {
    const row = db.prepare(`SELECT server_id FROM ${table} WHERE id = ?`).get(id);
    return row ? row.server_id : null;
  }

  function setServerId(table, id, serverId) {
    if (table !== 'exam_attempts' && table !== 'student_answers') return;
    db.prepare(`UPDATE ${table} SET server_id = ?, updated_at = ? WHERE id = ?`).run(serverId, now(), id);
  }

  function markFailed(items, message) {
    const fail = db.transaction(() => {
      items.forEach((item) => {
        db.prepare(`
          UPDATE sync_queue
          SET status = 'failed',
              attempts = attempts + 1,
              last_error = ?,
              updated_at = ?
          WHERE id = ?
        `).run(message, now(), item.id);
      });
    });

    fail();
  }

  return {
    getStatus,
    runSync,
    importFromOnline,
    importFromFile,
    exportAttempts
  };
}

function normalizeServerUrl(value) {
  return String(value || '').trim().replace(/\/+$/, '');
}

function explainFetchError(error, serverUrl) {
  if (error && error.message === 'fetch failed') {
    return `Could not reach the online CBT server. Check the server URL and internet connection.`;
  }

  return (error && error.message) || 'Import from online failed.';
}

async function postSyncJson(serverUrl, endpointFile, payload) {
  const candidates = buildSyncEndpointCandidates(serverUrl, endpointFile);
  const errors = [];

  for (const endpoint of candidates) {
    try {
      const response = await fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      if (response.ok) {
        return response.json();
      }

      errors.push(`${endpoint} returned HTTP ${response.status}`);
    } catch (error) {
      errors.push(`${endpoint} failed: ${error.message || 'network error'}`);
    }
  }

  throw new Error(`Sync endpoint not found. Tried: ${errors.join('; ')}`);
}

function buildSyncEndpointCandidates(serverUrl, endpointFile) {
  const normalized = normalizeServerUrl(serverUrl);
  const candidates = new Set();

  if (normalized.endsWith(`/api/sync/${endpointFile}`)) {
    candidates.add(normalized);
    return [...candidates];
  }

  candidates.add(`${normalized}/api/sync/${endpointFile}`);

  try {
    const parsed = new URL(normalized);
    const origin = parsed.origin;
    const path = parsed.pathname.replace(/\/+$/, '');

    if (!path || path === '/') {
      candidates.add(`${origin}/cbt_exam/api/sync/${endpointFile}`);
    } else {
      candidates.add(`${origin}${path}/api/sync/${endpointFile}`);
      candidates.add(`${origin}/api/sync/${endpointFile}`);
      candidates.add(`${origin}/cbt_exam/api/sync/${endpointFile}`);
    }
  } catch (_error) {
    candidates.add(`${normalized}/cbt_exam/api/sync/${endpointFile}`);
  }

  return [...candidates];
}

function getColumns(table) {
  const columns = {
    classes: ['id', 'server_id', 'name', 'description', 'marks_per_question', 'updated_at', 'deleted_at'],
    subjects: ['id', 'server_id', 'name', 'subject_code', 'description', 'updated_at', 'deleted_at'],
    users: ['id', 'server_id', 'email', 'password_hash', 'full_name', 'role', 'class_id', 'subject_code', 'updated_at', 'deleted_at'],
    exams: ['id', 'server_id', 'title', 'description', 'is_published', 'published_at', 'updated_at', 'deleted_at'],
    exam_subjects: ['id', 'server_id', 'exam_id', 'subject_id', 'class_id', 'time_limit', 'is_published', 'published_at', 'show_calculator', 'updated_at', 'deleted_at'],
    questions: ['id', 'server_id', 'exam_subject_id', 'order_position', 'question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'option_e', 'option_f', 'correct_answer', 'updated_at', 'deleted_at'],
    comprehension_passages: ['id', 'server_id', 'exam_subject_id', 'passage_title', 'passage_content', 'start_question_number', 'end_question_number', 'updated_at', 'deleted_at']
  };

  if (!columns[table]) {
    throw new Error(`Unsupported sync table: ${table}`);
  }

  return columns[table];
}

function normalizeImportedRow(table, row) {
  const normalized = { ...row };
  if (!normalized.id && normalized.server_id) {
    normalized.id = String(normalized.server_id);
  }

  getColumns(table).forEach((column) => {
    if (normalized[column] !== undefined && normalized[column] !== null) {
      normalized[column] = String(normalized[column]);
    }
  });

  if (!normalized.updated_at) {
    normalized.updated_at = now();
  }

  return normalized;
}

module.exports = {
  createSyncService
};
