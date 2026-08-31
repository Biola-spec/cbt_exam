import { useState, useEffect } from 'react';
import { supabase, Exam, Passkey, ExamSubject, Class, Subject } from '../../lib/supabase';
import { Key, Plus, Trash2 } from 'lucide-react';

export const PasskeyManagement = () => {
  const [exams, setExams] = useState<Exam[]>([]);
  const [examSubjects, setExamSubjects] = useState<(ExamSubject & { class: Class; subject: Subject; exam: Exam })[]>([]);
  const [passkeys, setPasskeys] = useState<(Passkey & { exam: Exam })[]>([]);
  const [selectedExam, setSelectedExam] = useState('');
  const [selectedExamSubject, setSelectedExamSubject] = useState('');
  const [studentEmail, setStudentEmail] = useState('');
  const [usageLimit, setUsageLimit] = useState(1);
  const [expiryDays, setExpiryDays] = useState(7);
  const [bulkCount, setBulkCount] = useState(1);
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState(''import { useState, useEffect, useMemo } from 'react';
import { supabase, Exam, Passkey, ExamSubject, Class, Subject } from '../../lib/supabase';
import { Key, Plus, Trash2 } from 'lucide-react';

export const PasskeyManagement = () => {
  const [exams, setExams] = useState<Exam[]>([]);
  const [examSubjects, setExamSubjects] = useState<(ExamSubject & { class: Class; subject: Subject; exam: Exam })[]>([]);
  const [passkeys, setPasskeys] = useState<(Passkey & { exam: Exam })[]>([]);
  const [selectedExam, setSelectedExam] = useState('');
  const [selectedExamSubject, setSelectedExamSubject] = useState('');
  const [studentEmail, setStudentEmail] = useState('');
  const [usageLimit, setUsageLimit] = useState(1);
  const [expiryDays, setExpiryDays] = useState(7);
  const [bulkCount, setBulkCount] = useState(1);
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState('');

  // Memoized filtered subjects to avoid filtering in render repeatedly
  const filteredExamSubjects = useMemo(
    () => examSubjects.filter(es => selectedExam === '' || es.exam_id === selectedExam),
    [examSubjects, selectedExam]
  );

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    try {
      // Narrow selects to necessary fields
      const [examsRes, examSubjectsRes, passkeysRes] = await Promise.all([
        supabase.from('exams')
          .select('id,title,is_published,created_at')
          .order('created_at', { ascending: false }),
        supabase.from('exam_subjects')
          .select(`
            id, exam_id, subject_id, class_id, is_published, published_at, time_limit, created_at,
            class:classes(id,name),
            subject:subjects(id,name),
            exam:exams(id,title)
          `)
          .order('created_at', { ascending: false }),
        supabase.from('passkeys')
          .select(`
            id, passkey, student_email, exam_id, exam_subject_id, usage_limit, is_used, expires_at, created_by, created_at,
            exam:exams(id,title)
          `)
          .order('created_at', { ascending: false }),
      ]);

      if (examsRes.error) throw examsRes.error;
      if (examSubjectsRes.error) throw examSubjectsRes.error;
      if (passkeysRes.error) throw passkeysRes.error;

      if (examsRes.data) setExams(examsRes.data);
      if (examSubjectsRes.data) setExamSubjects(examSubjectsRes.data as any);
      if (passkeysRes.data) setPasskeys(passkeysRes.data as any);
    } catch (error) {
      console.error('Error loading data:', error);
      setMessage('Error loading data');
    }
  };

  // More robust passkey generator; uses crypto if available
  const generatePasskey = () => {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    const length = 8;
    if (typeof crypto !== 'undefined' && 'getRandomValues' in crypto) {
      const array = new Uint32Array(length);
      crypto.getRandomValues(array);
      return Array.from(array, v => chars[v % chars.length]).join('');
    }
    // Fallback
    let key = '';
    for (let i = 0; i < length; i++) {
      key += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return key;
  };

  // Normalize expiration to end-of-day to reduce surprises for users
  const computeExpiresAtISO = (daysFromNow: number) => {
    const d = new Date();
    d.setDate(d.getDate() + daysFromNow);
    d.setHours(23, 59, 59, 999);
    return d.toISOString();
  };

  const isValidEmail = (email: string) => {
    if (!email) return false;
    // Simple RFC-like check
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  };

  const handleCreateSinglePasskey = async () => {
    if (!selectedExam || !studentEmail || !isValidEmail(studentEmail) || loading) return;

    setLoading(true);
    setMessage('');

    try {
      const expiresAtIso = computeExpiresAtISO(expiryDays);

      const { error } = await supabase
        .from('passkeys')
        .insert({
          passkey: generatePasskey(),
          student_email: studentEmail,
          exam_id: selectedExam,
          exam_subject_id: selectedExamSubject || null,
          usage_limit: usageLimit,
          expires_at: expiresAtIso,
        });

      if (error) throw error;

      setStudentEmail('');
      setSelectedExamSubject('');
      setMessage('Passkey created successfully');
      await loadData();
    } catch (error: any) {
      console.debug('Create single passkey failed', { selectedExam, selectedExamSubject, usageLimit, expiryDays, studentEmail, error });
      setMessage('Error creating passkey: ' + (error?.message || 'Unknown error'));
    } finally {
      setLoading(false);
    }
  };

  const handleCreateBulkPasskeys = async () => {
    if (!selectedExam || bulkCount < 1 || loading) return;

    setLoading(true);
    setMessage('');

    try {
      const expiresAtIso = computeExpiresAtISO(expiryDays);

      const passkeysToCreate = Array.from({ length: bulkCount }, () => ({
        passkey: generatePasskey(),
        student_email: '',
        exam_id: selectedExam,
        exam_subject_id: selectedExamSubject || null,
        usage_limit: usageLimit,
        expires_at: expiresAtIso,
      }));

      const { error } = await supabase.from('passkeys').insert(passkeysToCreate);
      if (error) throw error;

      setMessage(`${bulkCount} passkey${bulkCount > 1 ? 's' : ''} created successfully`);
      await loadData();
    } catch (error: any) {
      console.debug('Create bulk passkeys failed', { selectedExam, selectedExamSubject, usageLimit, expiryDays, bulkCount, error });
      setMessage('Error creating passkeys: ' + (error?.message || 'Unknown error'));
    } finally {
      setLoading(false);
    }
  };

  const handleDeletePasskey = async (id: string) => {
    // confirm is available in DOM libs; keep for UX
    if (!confirm('Are you sure you want to delete this passkey?')) return;

    try {
      const { error } = await supabase.from('passkeys').delete().eq('id', id);
      if (error) throw error;
      // Optimistic UI: remove locally to reduce flicker
      setPasskeys(prev => prev.filter(p => p.id !== id));
    } catch (error: any) {
      alert('Error deleting passkey: ' + (error?.message || 'Unknown error'));
    }
  };

  const handleUpdateEmail = async (id: string, email: string) => {
    if (!isValidEmail(email)) {
      alert('Please enter a valid email address.');
      return;
    }
    try {
      const { error } = await supabase.from('passkeys').update({ student_email: email }).eq('id', id);
      if (error) throw error;
      // Optimistic update
      setPasskeys(prev =>
        prev.map(p => (p.id === id ? { ...p, student_email: email } : p))
      );
    } catch (error: any) {
      alert('Error updating email: ' + (error?.message || 'Unknown error'));
    }
  };

  return (
    <div>
      <h2 className="text-2xl font-bold text-gray-900 mb-6">Passkey Management</h2>

      {/* Information Panel */}
      <div className="mb-6 p-4 bg-blue-50 rounded-lg">
        <h3 className="font-semibold text-gray-900 mb-2 flex items-center gap-2">
          <span className="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded-full">
            Info
          </span>
          Passkey Types
        </h3>
        <ul className="list-disc list-inside text-sm text-gray-700 space-y-1">
          <li><strong>Exam Passkey</strong>: Grants access to all subjects in an exam</li>
          <li><strong>Subject Passkey</strong>: Grants access to a specific subject only (useful when you have different subject instances for different classes)</li>
        </ul>
      </div>

      <div className="mb-8 p-4 bg-gray-50 rounded-lg">
        <h3 className="font-semibold text-gray-900 mb-4">Create Passkeys</h3>

        <div className="grid gap-4 mb-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Select Exam
            </label>
            <select
              value={selectedExam}
              onChange={(e) => setSelectedExam(e.target.value)}
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              disabled={loading}
            >
              <option value="">Select Exam</option>
              {exams.map((exam) => (
                <option key={exam.id} value={exam.id}>
                  {exam.title} {exam.is_published ? '(Published)' : '(Draft)'}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Select Exam Subject (Optional)
            </label>
            <select
              value={selectedExamSubject}
              onChange={(e) => setSelectedExamSubject(e.target.value)}
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              disabled={loading}
            >
              <option value="">Select Exam Subject (Optional)</option>
              {filteredExamSubjects.map((examSubject) => (
                <option key={examSubject.id} value={examSubject.id}>
                  {examSubject.exam?.title} - {examSubject.class?.name} - {examSubject.subject?.name}
                </option>
              ))}
            </select>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Usage Limit
              </label>
              <select
                value={usageLimit}
                onChange={(e) => setUsageLimit(parseInt(e.target.value))}
                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                disabled={loading}
              >
                <option value="1">1 Time</option>
                <option value="2">2 Times</option>
                <option value="3">3 Times</option>
                <option value="5">5 Times</option>
                <option value="10">10 Times</option>
                <option value="0">Unlimited</option>
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Expiry Days
              </label>
              <input
                type="number"
                value={expiryDays}
                onChange={(e) => setExpiryDays(parseInt(e.target.value) || 7)}
                min="1"
                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                disabled={loading}
              />
            </div>
          </div>
        </div>

        <div className="grid md:grid-cols-2 gap-4 mb-4">
          <div className="p-4 border border-gray-200 rounded-lg">
            <h4 className="font-medium text-gray-900 mb-3">Single Passkey</h4>
            <input
              type="email"
              value={studentEmail}
              onChange={(e) => setStudentEmail(e.target.value)}
              placeholder="Student email"
              className="w-full px-4 py-2 mb-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              disabled={loading}
            />
            <button
              onClick={handleCreateSinglePasskey}
              disabled={loading || !selectedExam || !studentEmail || !isValidEmail(studentEmail)}
              className="w-full flex items-center justify-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              title={!studentEmail ? 'Enter student email' : (!isValidEmail(studentEmail) ? 'Enter a valid email' : '')}
            >
              <Key className="w-4 h-4" />
              {loading ? 'Creating...' : 'Create Single Passkey'}
            </button>
          </div>

          <div className="p-4 border border-gray-200 rounded-lg">
            <h4 className="font-medium text-gray-900 mb-3">Bulk Passkeys</h4>
            <input
              type="number"
              value={bulkCount}
              onChange={(e) => setBulkCount(parseInt(e.target.value) || 1)}
              min="1"
              max="100"
              placeholder="Number of passkeys"
              className="w-full px-4 py-2 mb-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              disabled={loading}
            />
            <button
              onClick={handleCreateBulkPasskeys}
              disabled={loading || !selectedExam || bulkCount < 1}
              className="w-full flex items-center justify-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <Plus className="w-4 h-4" />
              {loading ? 'Creating...' : `Create ${bulkCount} Passkeys`}
            </button>
          </div>
        </div>

        {message && (
          <div
            className={`p-3 rounded-lg text-sm ${
              message.includes('Error')
                ? 'bg-red-50 text-red-700 border border-red-200'
                : 'bg-green-50 text-green-700 border border-green-200'
            }`}
            role="status"
            aria-live="polite"
          >
            {message}
          </div>
        )}
      </div>

      <div>
        <h3 className="font-semibold text-gray-900 mb-4">Existing Passkeys</h3>
        {passkeys.length === 0 ? (
          <p className="text-gray-500 text-center py-8">No passkeys created yet</p>
        ) : (
          <div className="space-y-2">
            {passkeys.map((passkey) => {
              const isExpired = new Date(passkey.expires_at) < new Date();
              const usageText = passkey.usage_limit === 0 ? 'Unlimited' : `${passkey.usage_limit} times`;
              return (
                <div
                  key={passkey.id}
                  className="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50"
                >
                  <div className="flex-1 grid grid-cols-6 gap-4 items-center">
                    <div>
                      <p className="text-xs text-gray-500">Passkey</p>
                      <p className="font-mono font-bold text-lg text-blue-600">{passkey.passkey}</p>
                    </div>
                    <div>
                      <p className="text-xs text-gray-500">Exam</p>
                      <p className="text-sm font-medium text-gray-900">{passkey.exam.title}</p>
                    </div>
                    <div>
                      <p className="text-xs text-gray-500">Usage Limit</p>
                      <p className="text-sm text-gray-900">{usageText}</p>
                    </div>
                    <div>
                      <p className="text-xs text-gray-500">Student Email</p>
                      {passkey.student_email ? (
                        <p className="text-sm text-gray-900">{passkey.student_email}</p>
                      ) : (
                        <input
                          type="email"
                          placeholder="Enter email"
                          onBlur={(e) => {
                            const value = e.target.value.trim();
                            if (value) {
                              handleUpdateEmail(passkey.id, value);
                            }
                          }}
                          className="text-sm px-2 py-1 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        />
                      )}
                    </div>
                    <div>
                      <p className="text-xs text-gray-500">Status</p>
                      {passkey.is_used ? (
                        <span className="inline-block px-2 py-1 bg-gray-100 text-gray-700 text-xs rounded-full">
                          Used
                        </span>
                      ) : isExpired ? (
                        <span className="inline-block px-2 py-1 bg-red-100 text-red-700 text-xs rounded-full">
                          Expired
                        </span>
                      ) : (
                        <span className="inline-block px-2 py-1 bg-green-100 text-green-700 text-xs rounded-full">
                          Active
                        </span>
                      )}
                    </div>
                    <div>
                      <p className="text-xs text-gray-500">Expires</p>
                      <p className="text-sm text-gray-900">
                        {new Date(passkey.expires_at).toLocaleDateString()}
                      </p>
                    </div>
                  </div>
                  <button
                    onClick={() => handleDeletePasskey(passkey.id)}
                    className="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                    aria-label={`Delete passkey ${passkey.passkey}`}
                    title="Delete"
                  >
                    <Trash2 className="w-4 h-4" />
                  </button>
                </div>
              );
            })}
          </div>
        )}
      </div>
    </div>
  );
};import { useState, useEffect, useMemo } from 'react';
import { supabase, Exam, Passkey, ExamSubject, Class, Subject } from '../../lib/supabase';
import { Key, Plus, Trash2 } from 'lucide-react';

export const PasskeyManagement = () => {
  const [exams, setExams] = useState<Exam[]>([]);
  const [examSubjects, setExamSubjects] = useState<(ExamSubject & { class: Class; subject: Subject; exam: Exam })[]>([]);
  const [passkeys, setPasskeys] = useState<(Passkey & { exam: Exam })[]>([]);
  const [selectedExam, setSelectedExam] = useState('');
  const [selectedExamSubject, setSelectedExamSubject] = useState('');
  const [studentEmail, setStudentEmail] = useState('');
  const [usageLimit, setUsageLimit] = useState(1);
  const [expiryDays, setExpiryDays] = useState(7);
  const [bulkCount, setBulkCount] = useState(1);
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState('');

  // Memoized filtered subjects to avoid filtering in render repeatedly
  const filteredExamSubjects = useMemo(
    () => examSubjects.filter(es => selectedExam === '' || es.exam_id === selectedExam),
    [examSubjects, selectedExam]
  );

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    try {
      // Narrow selects to necessary fields
      const [examsRes, examSubjectsRes, passkeysRes] = await Promise.all([
        supabase.from('exams')
          .select('id,title,is_published,created_at')
          .order('created_at', { ascending: false }),
        supabase.from('exam_subjects')
          .select(`
            id, exam_id, subject_id, class_id, is_published, published_at, time_limit, created_at,
            class:classes(id,name),
            subject:subjects(id,name),
            exam:exams(id,title)
          `)
          .order('created_at', { ascending: false }),
        supabase.from('passkeys')
          .select(`
            id, passkey, student_email, exam_id, exam_subject_id, usage_limit, is_used, expires_at, created_by, created_at,
            exam:exams(id,title)
          `)
          .order('created_at', { ascending: false }),
      ]);

      if (examsRes.error) throw examsRes.error;
      if (examSubjectsRes.error) throw examSubjectsRes.error;
      if (passkeysRes.error) throw passkeysRes.error;

      if (examsRes.data) setExams(examsRes.data);
      if (examSubjectsRes.data) setExamSubjects(examSubjectsRes.data as any);
      if (passkeysRes.data) setPasskeys(passkeysRes.data as any);
    } catch (error) {
      console.error('Error loading data:', error);
      setMessage('Error loading data');
    }
  };

  // More robust passkey generator; uses crypto if available
  const generatePasskey = () => {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    const length = 8;
    if (typeof crypto !== 'undefined' && 'getRandomValues' in crypto) {
      const array = new Uint32Array(length);
      crypto.getRandomValues(array);
      return Array.from(array, v => chars[v % chars.length]).join('');
    }
    // Fallback
    let key = '';
    for (let i = 0; i < length; i++) {
      key += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return key;
  };

  // Normalize expiration to end-of-day to reduce surprises for users
  const computeExpiresAtISO = (daysFromNow: number) => {
    const d = new Date();
    d.setDate(d.getDate() + daysFromNow);
    d.setHours(23, 59, 59, 999);
    return d.toISOString();
  };

  const isValidEmail = (email: string) => {
    if (!email) return false;
    // Simple RFC-like check
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  };

  const handleCreateSinglePasskey = async () => {
    if (!selectedExam || !studentEmail || !isValidEmail(studentEmail) || loading) return;

    setLoading(true);
    setMessage('');

    try {
      const expiresAtIso = computeExpiresAtISO(expiryDays);

      const { error } = await supabase
        .from('passkeys')
        .insert({
          passkey: generatePasskey(),
          student_email: studentEmail,
          exam_id: selectedExam,
          exam_subject_id: selectedExamSubject || null,
          usage_limit: usageLimit,
          expires_at: expiresAtIso,
        });

      if (error) throw error;

      setStudentEmail('');
      setSelectedExamSubject('');
      setMessage('Passkey created successfully');
      await loadData();
    } catch (error: any) {
      console.debug('Create single passkey failed', { selectedExam, selectedExamSubject, usageLimit, expiryDays, studentEmail, error });
      setMessage('Error creating passkey: ' + (error?.message || 'Unknown error'));
    } finally {
      setLoading(false);
    }
  };

  const handleCreateBulkPasskeys = async () => {
    if (!selectedExam || bulkCount < 1 || loading) return;

    setLoading(true);
    setMessage('');

    try {
      const expiresAtIso = computeExpiresAtISO(expiryDays);

      const passkeysToCreate = Array.from({ length: bulkCount }, () => ({
        passkey: generatePasskey(),
        student_email: '',
        exam_id: selectedExam,
        exam_subject_id: selectedExamSubject || null,
        usage_limit: usageLimit,
        expires_at: expiresAtIso,
      }));

      const { error } = await supabase.from('passkeys').insert(passkeysToCreate);
      if (error) throw error;

      setMessage(`${bulkCount} passkey${bulkCount > 1 ? 's' : ''} created successfully`);
      await loadData();
    } catch (error: any) {
      console.debug('Create bulk passkeys failed', { selectedExam, selectedExamSubject, usageLimit, expiryDays, bulkCount, error });
      setMessage('Error creating passkeys: ' + (error?.message || 'Unknown error'));
    } finally {
      setLoading(false);
    }
  };

  const handleDeletePasskey = async (id: string) => {
    // confirm is available in DOM libs; keep for UX
    if (!confirm('Are you sure you want to delete this passkey?')) return;

    try {
      const { error } = await supabase.from('passkeys').delete().eq('id', id);
      if (error) throw error;
      // Optimistic UI: remove locally to reduce flicker
      setPasskeys(prev => prev.filter(p => p.id !== id));
    } catch (error: any) {
      alert('Error deleting passkey: ' + (error?.message || 'Unknown error'));
    }
  };

  const handleUpdateEmail = async (id: string, email: string) => {
    if (!isValidEmail(email)) {
      alert('Please enter a valid email address.');
      return;
    }
    try {
      const { error } = await supabase.from('passkeys').update({ student_email: email }).eq('id', id);
      if (error) throw error;
      // Optimistic update
      setPasskeys(prev =>
        prev.map(p => (p.id === id ? { ...p, student_email: email } : p))
      );
    } catch (error: any) {
      alert('Error updating email: ' + (error?.message || 'Unknown error'));
    }
  };

  return (
    <div>
      <h2 className="text-2xl font-bold text-gray-900 mb-6">Passkey Management</h2>

      {/* Information Panel */}
      <div className="mb-6 p-4 bg-blue-50 rounded-lg">
        <h3 className="font-semibold text-gray-900 mb-2 flex items-center gap-2">
          <span className="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded-full">
            Info
          </span>
          Passkey Types
        </h3>
        <ul className="list-disc list-inside text-sm text-gray-700 space-y-1">
          <li><strong>Exam Passkey</strong>: Grants access to all subjects in an exam</li>
          <li><strong>Subject Passkey</strong>: Grants access to a specific subject only (useful when you have different subject instances for different classes)</li>
        </ul>
      </div>

      <div className="mb-8 p-4 bg-gray-50 rounded-lg">
        <h3 className="font-semibold text-gray-900 mb-4">Create Passkeys</h3>

        <div className="grid gap-4 mb-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Select Exam
            </label>
            <select
              value={selectedExam}
              onChange={(e) => setSelectedExam(e.target.value)}
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              disabled={loading}
            >
              <option value="">Select Exam</option>
              {exams.map((exam) => (
                <option key={exam.id} value={exam.id}>
                  {exam.title} {exam.is_published ? '(Published)' : '(Draft)'}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Select Exam Subject (Optional)
            </label>
            <select
              value={selectedExamSubject}
              onChange={(e) => setSelectedExamSubject(e.target.value)}
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              disabled={loading}
            >
              <option value="">Select Exam Subject (Optional)</option>
              {filteredExamSubjects.map((examSubject) => (
                <option key={examSubject.id} value={examSubject.id}>
                  {examSubject.exam?.title} - {examSubject.class?.name} - {examSubject.subject?.name}
                </option>
              ))}
            </select>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Usage Limit
              </label>
              <select
                value={usageLimit}
                onChange={(e) => setUsageLimit(parseInt(e.target.value))}
                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                disabled={loading}
              >
                <option value="1">1 Time</option>
                <option value="2">2 Times</option>
                <option value="3">3 Times</option>
                <option value="5">5 Times</option>
                <option value="10">10 Times</option>
                <option value="0">Unlimited</option>
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Expiry Days
              </label>
              <input
                type="number"
                value={expiryDays}
                onChange={(e) => setExpiryDays(parseInt(e.target.value) || 7)}
                min="1"
                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                disabled={loading}
              />
            </div>
          </div>
        </div>

        <div className="grid md:grid-cols-2 gap-4 mb-4">
          <div className="p-4 border border-gray-200 rounded-lg">
            <h4 className="font-medium text-gray-900 mb-3">Single Passkey</h4>
            <input
              type="email"
              value={studentEmail}
              onChange={(e) => setStudentEmail(e.target.value)}
              placeholder="Student email"
              className="w-full px-4 py-2 mb-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              disabled={loading}
            />
            <button
              onClick={handleCreateSinglePasskey}
              disabled={loading || !selectedExam || !studentEmail || !isValidEmail(studentEmail)}
              className="w-full flex items-center justify-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              title={!studentEmail ? 'Enter student email' : (!isValidEmail(studentEmail) ? 'Enter a valid email' : '')}
            >
              <Key className="w-4 h-4" />
              {loading ? 'Creating...' : 'Create Single Passkey'}
            </button>
          </div>

          <div className="p-4 border border-gray-200 rounded-lg">
            <h4 className="font-medium text-gray-900 mb-3">Bulk Passkeys</h4>
            <input
              type="number"
              value={bulkCount}
              onChange={(e) => setBulkCount(parseInt(e.target.value) || 1)}
              min="1"
              max="100"
              placeholder="Number of passkeys"
              className="w-full px-4 py-2 mb-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              disabled={loading}
            />
            <button
              onClick={handleCreateBulkPasskeys}
              disabled={loading || !selectedExam || bulkCount < 1}
              className="w-full flex items-center justify-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <Plus className="w-4 h-4" />
              {loading ? 'Creating...' : `Create ${bulkCount} Passkeys`}
            </button>
          </div>
        </div>

        {message && (
          <div
            className={`p-3 rounded-lg text-sm ${
              message.includes('Error')
                ? 'bg-red-50 text-red-700 border border-red-200'
                : 'bg-green-50 text-green-700 border border-green-200'
            }`}
            role="status"
            aria-live="polite"
          >
            {message}
          </div>
        )}
      </div>

      <div>
        <h3 className="font-semibold text-gray-900 mb-4">Existing Passkeys</h3>
        {passkeys.length === 0 ? (
          <p className="text-gray-500 text-center py-8">No passkeys created yet</p>
        ) : (
          <div className="space-y-2">
            {passkeys.map((passkey) => {
              const isExpired = new Date(passkey.expires_at) < new Date();
              const usageText = passkey.usage_limit === 0 ? 'Unlimited' : `${passkey.usage_limit} times`;
              return (
                <div
                  key={passkey.id}
                  className="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50"
                >
                  <div className="flex-1 grid grid-cols-6 gap-4 items-center">
                    <div>
                      <p className="text-xs text-gray-500">Passkey</p>
                      <p className="font-mono font-bold text-lg text-blue-600">{passkey.passkey}</p>
                    </div>
                    <div>
                      <p className="text-xs text-gray-500">Exam</p>
                      <p className="text-sm font-medium text-gray-900">{passkey.exam.title}</p>
                    </div>
                    <div>
                      <p className="text-xs text-gray-500">Usage Limit</p>
                      <p className="text-sm text-gray-900">{usageText}</p>
                    </div>
                    <div>
                      <p className="text-xs text-gray-500">Student Email</p>
                      {passkey.student_email ? (
                        <p className="text-sm text-gray-900">{passkey.student_email}</p>
                      ) : (
                        <input
                          type="email"
                          placeholder="Enter email"
                          onBlur={(e) => {
                            const value = e.target.value.trim();
                            if (value) {
                              handleUpdateEmail(passkey.id, value);
                            }
                          }}
                          className="text-sm px-2 py-1 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        />
                      )}
                    </div>
                    <div>
                      <p className="text-xs text-gray-500">Status</p>
                      {passkey.is_used ? (
                        <span className="inline-block px-2 py-1 bg-gray-100 text-gray-700 text-xs rounded-full">
                          Used
                        </span>
                      ) : isExpired ? (
                        <span className="inline-block px-2 py-1 bg-red-100 text-red-700 text-xs rounded-full">
                          Expired
                        </span>
                      ) : (
                        <span className="inline-block px-2 py-1 bg-green-100 text-green-700 text-xs rounded-full">
                          Active
                        </span>
                      )}
                    </div>
                    <div>
                      <p className="text-xs text-gray-500">Expires</p>
                      <p className="text-sm text-gray-900">
                        {new Date(passkey.expires_at).toLocaleDateString()}
                      </p>
                    </div>
                  </div>
                  <button
                    onClick={() => handleDeletePasskey(passkey.id)}
                    className="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                    aria-label={`Delete passkey ${passkey.passkey}`}
                    title="Delete"
                  >
                    <Trash2 className="w-4 h-4" />
                  </button>
                </div>
              );
            })}
          </div>
        )}
      </div>
    </div>
  );
};import { useState, useEffect, useMemo } from 'react';
import { supabase, Exam, Passkey, ExamSubject, Class, Subject } from '../../lib/supabase';
import { Key, Plus, Trash2 } from 'lucide-react';

export const PasskeyManagement = () => {
  const [exams, setExams] = useState<Exam[]>([]);
  const [examSubjects, setExamSubjects] = useState<(ExamSubject & { class: Class; subject: Subject; exam: Exam })[]>([]);
  const [passkeys, setPasskeys] = useState<(Passkey & { exam: Exam })[]>([]);
  const [selectedExam, setSelectedExam] = useState('');
  const [selectedExamSubject, setSelectedExamSubject] = useState('');
  const [studentEmail, setStudentEmail] = useState('');
  const [usageLimit, setUsageLimit] = useState(1);
  const [expiryDays, setExpiryDays] = useState(7);
  const [bulkCount, setBulkCount] = useState(1);
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState('');

  // Memoized filtered subjects to avoid filtering in render repeatedly
  const filteredExamSubjects = useMemo(
    () => examSubjects.filter(es => selectedExam === '' || es.exam_id === selectedExam),
    [examSubjects, selectedExam]
  );

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    try {
      // Narrow selects to necessary fields
      const [examsRes, examSubjectsRes, passkeysRes] = await Promise.all([
        supabase.from('exams')
          .select('id,title,is_published,created_at')
          .order('created_at', { ascending: false }),
        supabase.from('exam_subjects')
          .select(`
            id, exam_id, subject_id, class_id, is_published, published_at, time_limit, created_at,
            class:classes(id,name),
            subject:subjects(id,name),
            exam:exams(id,title)
          `)
          .order('created_at', { ascending: false }),
        supabase.from('passkeys')
          .select(`
            id, passkey, student_email, exam_id, exam_subject_id, usage_limit, is_used, expires_at, created_by, created_at,
            exam:exams(id,title)
          `)
          .order('created_at', { ascending: false }),
      ]);

      if (examsRes.error) throw examsRes.error;
      if (examSubjectsRes.error) throw examSubjectsRes.error;
      if (passkeysRes.error) throw passkeysRes.error;

      if (examsRes.data) setExams(examsRes.data);
      if (examSubjectsRes.data) setExamSubjects(examSubjectsRes.data as any);
      if (passkeysRes.data) setPasskeys(passkeysRes.data as any);
    } catch (error) {
      console.error('Error loading data:', error);
      setMessage('Error loading data');
    }
  };

  // More robust passkey generator; uses crypto if available
  const generatePasskey = () => {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    const length = 8;
    if (typeof crypto !== 'undefined' && 'getRandomValues' in crypto) {
      const array = new Uint32Array(length);
      crypto.getRandomValues(array);
      return Array.from(array, v => chars[v % chars.length]).join('');
    }
    // Fallback
    let key = '';
    for (let i = 0; i < length; i++) {
      key += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return key;
  };

  // Normalize expiration to end-of-day to reduce surprises for users
  const computeExpiresAtISO = (daysFromNow: number) => {
    const d = new Date();
    d.setDate(d.getDate() + daysFromNow);
    d.setHours(23, 59, 59, 999);
    return d.toISOString();
  };

  const isValidEmail = (email: string) => {
    if (!email) return false;
    // Simple RFC-like check
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  };

  const handleCreateSinglePasskey = async () => {
    if (!selectedExam || !studentEmail || !isValidEmail(studentEmail) || loading) return;

    setLoading(true);
    setMessage('');

    try {
      const expiresAtIso = computeExpiresAtISO(expiryDays);

      const { error } = await supabase
        .from('passkeys')
        .insert({
          passkey: generatePasskey(),
          student_email: studentEmail,
          exam_id: selectedExam,
          exam_subject_id: selectedExamSubject || null,
          usage_limit: usageLimit,
          expires_at: expiresAtIso,
        });

      if (error) throw error;

      setStudentEmail('');
      setSelectedExamSubject('');
      setMessage('Passkey created successfully');
      await loadData();
    } catch (error: any) {
      console.debug('Create single passkey failed', { selectedExam, selectedExamSubject, usageLimit, expiryDays, studentEmail, error });
      setMessage('Error creating passkey: ' + (error?.message || 'Unknown error'));
    } finally {
      setLoading(false);
    }
  };

  const handleCreateBulkPasskeys = async () => {
    if (!selectedExam || bulkCount < 1 || loading) return;

    setLoading(true);
    setMessage('');

    try {
      const expiresAtIso = computeExpiresAtISO(expiryDays);

      const passkeysToCreate = Array.from({ length: bulkCount }, () => ({
        passkey: generatePasskey(),
        student_email: '',
        exam_id: selectedExam,
        exam_subject_id: selectedExamSubject || null,
        usage_limit: usageLimit,
        expires_at: expiresAtIso,
      }));

      const { error } = await supabase.from('passkeys').insert(passkeysToCreate);
      if (error) throw error;

      setMessage(`${bulkCount} passkey${bulkCount > 1 ? 's' : ''} created successfully`);
      await loadData();
    } catch (error: any) {
      console.debug('Create bulk passkeys failed', { selectedExam, selectedExamSubject, usageLimit, expiryDays, bulkCount, error });
      setMessage('Error creating passkeys: ' + (error?.message || 'Unknown error'));
    } finally {
      setLoading(false);
    }
  };

  const handleDeletePasskey = async (id: string) => {
    // confirm is available in DOM libs; keep for UX
    if (!confirm('Are you sure you want to delete this passkey?')) return;

    try {
      const { error } = await supabase.from('passkeys').delete().eq('id', id);
      if (error) throw error;
      // Optimistic UI: remove locally to reduce flicker
      setPasskeys(prev => prev.filter(p => p.id !== id));
    } catch (error: any) {
      alert('Error deleting passkey: ' + (error?.message || 'Unknown error'));
    }
  };

  const handleUpdateEmail = async (id: string, email: string) => {
    if (!isValidEmail(email)) {
      alert('Please enter a valid email address.');
      return;
    }
    try {
      const { error } = await supabase.from('passkeys').update({ student_email: email }).eq('id', id);
      if (error) throw error;
      // Optimistic update
      setPasskeys(prev =>
        prev.map(p => (p.id === id ? { ...p, student_email: email } : p))
      );
    } catch (error: any) {
      alert('Error updating email: ' + (error?.message || 'Unknown error'));
    }
  };

  return (
    <div>
      <h2 className="text-2xl font-bold text-gray-900 mb-6">Passkey Management</h2>

      {/* Information Panel */}
      <div className="mb-6 p-4 bg-blue-50 rounded-lg">
        <h3 className="font-semibold text-gray-900 mb-2 flex items-center gap-2">
          <span className="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded-full">
            Info
          </span>
          Passkey Types
        </h3>
        <ul className="list-disc list-inside text-sm text-gray-700 space-y-1">
          <li><strong>Exam Passkey</strong>: Grants access to all subjects in an exam</li>
          <li><strong>Subject Passkey</strong>: Grants access to a specific subject only (useful when you have different subject instances for different classes)</li>
        </ul>
      </div>

      <div className="mb-8 p-4 bg-gray-50 rounded-lg">
        <h3 className="font-semibold text-gray-900 mb-4">Create Passkeys</h3>

        <div className="grid gap-4 mb-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Select Exam
            </label>
            <select
              value={selectedExam}
              onChange={(e) => setSelectedExam(e.target.value)}
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              disabled={loading}
            >
              <option value="">Select Exam</option>
              {exams.map((exam) => (
                <option key={exam.id} value={exam.id}>
                  {exam.title} {exam.is_published ? '(Published)' : '(Draft)'}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Select Exam Subject (Optional)
            </label>
            <select
              value={selectedExamSubject}
              onChange={(e) => setSelectedExamSubject(e.target.value)}
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              disabled={loading}
            >
              <option value="">Select Exam Subject (Optional)</option>
              {filteredExamSubjects.map((examSubject) => (
                <option key={examSubject.id} value={examSubject.id}>
                  {examSubject.exam?.title} - {examSubject.class?.name} - {examSubject.subject?.name}
                </option>
              ))}
            </select>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Usage Limit
              </label>
              <select
                value={usageLimit}
                onChange={(e) => setUsageLimit(parseInt(e.target.value))}
                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                disabled={loading}
              >
                <option value="1">1 Time</option>
                <option value="2">2 Times</option>
                <option value="3">3 Times</option>
                <option value="5">5 Times</option>
                <option value="10">10 Times</option>
                <option value="0">Unlimited</option>
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Expiry Days
              </label>
              <input
                type="number"
                value={expiryDays}
                onChange={(e) => setExpiryDays(parseInt(e.target.value) || 7)}
                min="1"
                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                disabled={loading}
              />
            </div>
          </div>
        </div>

        <div className="grid md:grid-cols-2 gap-4 mb-4">
          <div className="p-4 border border-gray-200 rounded-lg">
            <h4 className="font-medium text-gray-900 mb-3">Single Passkey</h4>
            <input
              type="email"
              value={studentEmail}
              onChange={(e) => setStudentEmail(e.target.value)}
              placeholder="Student email"
              className="w-full px-4 py-2 mb-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              disabled={loading}
            />
            <button
              onClick={handleCreateSinglePasskey}
              disabled={loading || !selectedExam || !studentEmail || !isValidEmail(studentEmail)}
              className="w-full flex items-center justify-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              title={!studentEmail ? 'Enter student email' : (!isValidEmail(studentEmail) ? 'Enter a valid email' : '')}
            >
              <Key className="w-4 h-4" />
              {loading ? 'Creating...' : 'Create Single Passkey'}
            </button>
          </div>

          <div className="p-4 border border-gray-200 rounded-lg">
            <h4 className="font-medium text-gray-900 mb-3">Bulk Passkeys</h4>
            <input
              type="number"
              value={bulkCount}
              onChange={(e) => setBulkCount(parseInt(e.target.value) || 1)}
              min="1"
              max="100"
              placeholder="Number of passkeys"
              className="w-full px-4 py-2 mb-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              disabled={loading}
            />
            <button
              onClick={handleCreateBulkPasskeys}
              disabled={loading || !selectedExam || bulkCount < 1}
              className="w-full flex items-center justify-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <Plus className="w-4 h-4" />
              {loading ? 'Creating...' : `Create ${bulkCount} Passkeys`}
            </button>
          </div>
        </div>

        {message && (
          <div
            className={`p-3 rounded-lg text-sm ${
              message.includes('Error')
                ? 'bg-red-50 text-red-700 border border-red-200'
                : 'bg-green-50 text-green-700 border border-green-200'
            }`}
            role="status"
            aria-live="polite"
          >
            {message}
          </div>
        )}
      </div>

      <div>
        <h3 className="font-semibold text-gray-900 mb-4">Existing Passkeys</h3>
        {passkeys.length === 0 ? (
          <p className="text-gray-500 text-center py-8">No passkeys created yet</p>
        ) : (
          <div className="space-y-2">
            {passkeys.map((passkey) => {
              const isExpired = new Date(passkey.expires_at) < new Date();
              const usageText = passkey.usage_limit === 0 ? 'Unlimited' : `${passkey.usage_limit} times`;
              return (
                <div
                  key={passkey.id}
                  className="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50"
                >
                  <div className="flex-1 grid grid-cols-6 gap-4 items-center">
                    <div>
                      <p className="text-xs text-gray-500">Passkey</p>
                      <p className="font-mono font-bold text-lg text-blue-600">{passkey.passkey}</p>
                    </div>
                    <div>
                      <p className="text-xs text-gray-500">Exam</p>
                      <p className="text-sm font-medium text-gray-900">{passkey.exam.title}</p>
                    </div>
                    <div>
                      <p className="text-xs text-gray-500">Usage Limit</p>
                      <p className="text-sm text-gray-900">{usageText}</p>
                    </div>
                    <div>
                      <p className="text-xs text-gray-500">Student Email</p>
                      {passkey.student_email ? (
                        <p className="text-sm text-gray-900">{passkey.student_email}</p>
                      ) : (
                        <input
                          type="email"
                          placeholder="Enter email"
                          onBlur={(e) => {
                            const value = e.target.value.trim();
                            if (value) {
                              handleUpdateEmail(passkey.id, value);
                            }
                          }}
                          className="text-sm px-2 py-1 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        />
                      )}
                    </div>
                    <div>
                      <p className="text-xs text-gray-500">Status</p>
                      {passkey.is_used ? (
                        <span className="inline-block px-2 py-1 bg-gray-100 text-gray-700 text-xs rounded-full">
                          Used
                        </span>
                      ) : isExpired ? (
                        <span className="inline-block px-2 py-1 bg-red-100 text-red-700 text-xs rounded-full">
                          Expired
                        </span>
                      ) : (
                        <span className="inline-block px-2 py-1 bg-green-100 text-green-700 text-xs rounded-full">
                          Active
                        </span>
                      )}
                    </div>
                    <div>
                      <p className="text-xs text-gray-500">Expires</p>
                      <p className="text-sm text-gray-900">
                        {new Date(passkey.expires_at).toLocaleDateString()}
                      </p>
                    </div>
                  </div>
                  <button
                    onClick={() => handleDeletePasskey(passkey.id)}
                    className="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                    aria-label={`Delete passkey ${passkey.passkey}`}
                    title="Delete"
                  >
                    <Trash2 className="w-4 h-4" />
                  </button>
                </div>
              );
            })}
          </div>
        )}
      </div>
    </div>
  );
};);

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    try {
      const [examsRes, examSubjectsRes, passkeysRes] = await Promise.all([
        supabase.from('exams').select('*').order('created_at', { ascending: false }),
        supabase.from('exam_subjects').select(`
          *,
          class:classes(*),
          subject:subjects(*),
          exam:exams(*)
        `).order('created_at', { ascending: false }),
        supabase.from('passkeys').select(`
          *,
          exam:exams(*)
        `).order('created_at', { ascending: false }),
      ]);

      if (examsRes.data) setExams(examsRes.data);
      if (examSubjectsRes.data) setExamSubjects(examSubjectsRes.data as any);
      if (passkeysRes.data) setPasskeys(passkeysRes.data as any);
    } catch (error) {
      console.error('Error loading data:', error);
    }
  };

  const generatePasskey = () => {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let key = '';
    for (let i = 0; i < 8; i++) {
      key += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return key;
  };

  const handleCreateSinglePasskey = async () => {
    if (!selectedExam || !studentEmail) return;

    setLoading(true);
    setMessage('');

    try {
      const expiresAt = new Date();
      expiresAt.setDate(expiresAt.getDate() + expiryDays);

      const { error } = await supabase
        .from('passkeys')
        .insert({
          passkey: generatePasskey(),
          student_email: studentEmail,
          exam_id: selectedExam,
          exam_subject_id: selectedExamSubject || null,
          usage_limit: usageLimit,
          expires_at: expiresAt.toISOString(),
        });

      if (error) throw error;

      setStudentEmail('');
      setSelectedExamSubject('');
      setMessage('Passkey created successfully');
      loadData();
    } catch (error: any) {
      setMessage('Error creating passkey: ' + error.message);
    } finally {
      setLoading(false);
    }
  };

  const handleCreateBulkPasskeys = async () => {
    if (!selectedExam || bulkCount < 1) return;

    setLoading(true);
    setMessage('');

    try {
      const expiresAt = new Date();
      expiresAt.setDate(expiresAt.getDate() + expiryDays);

      const passkeysToCreate = Array.from({ length: bulkCount }, () => ({
        passkey: generatePasskey(),
        student_email: '',
        exam_id: selectedExam,
        exam_subject_id: selectedExamSubject || null,
        usage_limit: usageLimit,
        expires_at: expiresAt.toISOString(),
      }));

      const { error } = await supabase
        .from('passkeys')
        .insert(passkeysToCreate);

      if (error) throw error;

      setMessage(`${bulkCount} passkeys created successfully`);
      loadData();
    } catch (error: any) {
      setMessage('Error creating passkeys: ' + error.message);
    } finally {
      setLoading(false);
    }
  };

  const handleDeletePasskey = async (id: string) => {
    if (!confirm('Are you sure you want to delete this passkey?')) return;

    try {
      const { error } = await supabase
        .from('passkeys')
        .delete()
        .eq('id', id);

      if (error) throw error;
      loadData();
    } catch (error: any) {
      alert('Error deleting passkey: ' + error.message);
    }
  };

  const handleUpdateEmail = async (id: string, email: string) => {
    try {
      const { error } = await supabase
        .from('passkeys')
        .update({ student_email: email })
        .eq('id', id);

      if (error) throw error;
      loadData();
    } catch (error: any) {
      alert('Error updating email: ' + error.message);
    }
  };

  return (
    <div>
      <h2 className="text-2xl font-bold text-gray-900 mb-6">Passkey Management</h2>

      {/* Information Panel */}
      <div className="mb-6 p-4 bg-blue-50 rounded-lg">
        <h3 className="font-semibold text-gray-900 mb-2 flex items-center gap-2">
          <span className="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded-full">
            Info
          </span>
          Passkey Types
        </h3>
        <ul className="list-disc list-inside text-sm text-gray-700 space-y-1">
          <li><strong>Exam Passkey</strong>: Grants access to all subjects in an exam</li>
          <li><strong>Subject Passkey</strong>: Grants access to a specific subject only (useful when you have different subject instances for different classes)</li>
        </ul>
      </div>

      <div className="mb-8 p-4 bg-gray-50 rounded-lg">
        <h3 className="font-semibold text-gray-900 mb-4">Create Passkeys</h3>

        <div className="grid gap-4 mb-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Select Exam
            </label>
            <select
              value={selectedExam}
              onChange={(e) => setSelectedExam(e.target.value)}
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
              <option value="">Select Exam</option>
              {exams.map((exam) => (
                <option key={exam.id} value={exam.id}>
                  {exam.title} {exam.is_published ? '(Published)' : '(Draft)'}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Select Exam Subject (Optional)
            </label>
            <select
              value={selectedExamSubject}
              onChange={(e) => setSelectedExamSubject(e.target.value)}
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
              <option value="">Select Exam Subject (Optional)</option>
              {examSubjects
                .filter(es => selectedExam === '' || es.exam_id === selectedExam)
                .map((examSubject) => (
                  <option key={examSubject.id} value={examSubject.id}>
                    {examSubject.exam?.title} - {examSubject.class?.name} - {examSubject.subject?.name}
                  </option>
                ))}
            </select>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Usage Limit
              </label>
              <select
                value={usageLimit}
                onChange={(e) => setUsageLimit(parseInt(e.target.value))}
                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              >
                <option value="1">1 Time</option>
                <option value="2">2 Times</option>
                <option value="3">3 Times</option>
                <option value="5">5 Times</option>
                <option value="10">10 Times</option>
                <option value="0">Unlimited</option>
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Expiry Days
              </label>
              <input
                type="number"
                value={expiryDays}
                onChange={(e) => setExpiryDays(parseInt(e.target.value) || 7)}
                min="1"
                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
            </div>
          </div>
        </div>

        <div className="grid md:grid-cols-2 gap-4 mb-4">
          <div className="p-4 border border-gray-200 rounded-lg">
            <h4 className="font-medium text-gray-900 mb-3">Single Passkey</h4>
            <input
              type="email"
              value={studentEmail}
              onChange={(e) => setStudentEmail(e.target.value)}
              placeholder="Student email"
              className="w-full px-4 py-2 mb-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
            <button
              onClick={handleCreateSinglePasskey}
              disabled={loading || !selectedExam || !studentEmail}
              className="w-full flex items-center justify-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <Key className="w-4 h-4" />
              Create Single Passkey
            </button>
          </div>

          <div className="p-4 border border-gray-200 rounded-lg">
            <h4 className="font-medium text-gray-900 mb-3">Bulk Passkeys</h4>
            <input
              type="number"
              value={bulkCount}
              onChange={(e) => setBulkCount(parseInt(e.target.value) || 1)}
              min="1"
              max="100"
              placeholder="Number of passkeys"
              className="w-full px-4 py-2 mb-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
            <button
              onClick={handleCreateBulkPasskeys}
              disabled={loading || !selectedExam || bulkCount < 1}
              className="w-full flex items-center justify-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <Plus className="w-4 h-4" />
              Create {bulkCount} Passkeys
            </button>
          </div>
        </div>

        {message && (
          <div className={`p-3 rounded-lg text-sm ${
            message.includes('Error')
              ? 'bg-red-50 text-red-700 border border-red-200'
              : 'bg-green-50 text-green-700 border border-green-200'
          }`}>
            {message}
          </div>
        )}
      </div>

      <div>
        <h3 className="font-semibold text-gray-900 mb-4">Existing Passkeys</h3>
        {passkeys.length === 0 ? (
          <p className="text-gray-500 text-center py-8">No passkeys created yet</p>
        ) : (
          <div className="space-y-2">
            {passkeys.map((passkey) => {
              const isExpired = new Date(passkey.expires_at) < new Date();
              const usageText = passkey.usage_limit === 0 ? 'Unlimited' : `${passkey.usage_limit} times`;
              return (
                <div
                  key={passkey.id}
                  className="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50"
                >
                  <div className="flex-1 grid grid-cols-6 gap-4 items-center">
                    <div>
                      <p className="text-xs text-gray-500">Passkey</p>
                      <p className="font-mono font-bold text-lg text-blue-600">{passkey.passkey}</p>
                    </div>
                    <div>
                      <p className="text-xs text-gray-500">Exam</p>
                      <p className="text-sm font-medium text-gray-900">{passkey.exam.title}</p>
                    </div>
                    <div>
                      <p className="text-xs text-gray-500">Usage Limit</p>
                      <p className="text-sm text-gray-900">{usageText}</p>
                    </div>
                    <div>
                      <p className="text-xs text-gray-500">Student Email</p>
                      {passkey.student_email ? (
                        <p className="text-sm text-gray-900">{passkey.student_email}</p>
                      ) : (
                        <input
                          type="email"
                          placeholder="Enter email"
                          onBlur={(e) => {
                            if (e.target.value) {
                              handleUpdateEmail(passkey.id, e.target.value);
                            }
                          }}
                          className="text-sm px-2 py-1 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        />
                      )}
                    </div>
                    <div>
                      <p className="text-xs text-gray-500">Status</p>
                      {passkey.is_used ? (
                        <span className="inline-block px-2 py-1 bg-gray-100 text-gray-700 text-xs rounded-full">
                          Used
                        </span>
                      ) : isExpired ? (
                        <span className="inline-block px-2 py-1 bg-red-100 text-red-700 text-xs rounded-full">
                          Expired
                        </span>
                      ) : (
                        <span className="inline-block px-2 py-1 bg-green-100 text-green-700 text-xs rounded-full">
                          Active
                        </span>
                      )}
                    </div>
                    <div>
                      <p className="text-xs text-gray-500">Expires</p>
                      <p className="text-sm text-gray-900">
                        {new Date(passkey.expires_at).toLocaleDateString()}
                      </p>
                    </div>
                  </div>
                  <button
                    onClick={() => handleDeletePasskey(passkey.id)}
                    className="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                  >
                    <Trash2 className="w-4 h-4" />
                  </button>
                </div>
              );
            })}
          </div>
        )}
      </div>
    </div>
  );
};