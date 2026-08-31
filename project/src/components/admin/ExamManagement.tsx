import { useState, useEffect } from 'react';
import { supabase, Exam, Class, Subject, ExamSubject } from '../../lib/supabase';
import { Plus, Trash2, CheckCircle, XCircle, Eye, RefreshCw } from 'lucide-react';

export const ExamManagement = () => {
  const [exams, setExams] = useState<Exam[]>([]);
  const [classes, setClasses] = useState<Class[]>([]);
  const [subjects, setSubjects] = useState<Subject[]>([]);
  const [showCreateExam, setShowCreateExam] = useState(false);
  const [newExamTitle, setNewExamTitle] = useState('');
  const [newExamDescription, setNewExamDescription] = useState('');
  const [selectedExamId, setSelectedExamId] = useState<string | null>(null);
  const [examSubjects, setExamSubjects] = useState<(ExamSubject & { class: Class; subject: Subject; questionCount?: number })[]>([]);
  const [selectedClass, setSelectedClass] = useState('');
  const [selectedSubject, setSelectedSubject] = useState('');
  const [timeLimit, setTimeLimit] = useState(60); // Will be updated with default from school config
  const [defaultTimeLimit, setDefaultTimeLimit] = useState(60); // Default from school config
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState('');
  const [showRepublish, setShowRepublish] = useState(false);
  const [republishEmail, setRepublishEmail] = useState('');
  const [republishReason, setRepublishReason] = useState('');
  const [republishSubjectId, setRepublishSubjectId] = useState('');

  useEffect(() => {
    loadData();
    loadSchoolConfig(); // Load school config to get default time limit
  }, []);

  useEffect(() => {
    if (selectedExamId) {
      loadExamSubjects(selectedExamId);
    }
  }, [selectedExamId]);

  const loadSchoolConfig = async () => {
    try {
      const { data } = await supabase
        .from('school_config')
        .select('default_time_limit')
        .maybeSingle();

      if (data && data.default_time_limit) {
        setDefaultTimeLimit(data.default_time_limit);
        setTimeLimit(data.default_time_limit); // Set initial time limit to default
      }
    } catch (error) {
      console.error('Error loading school config:', error);
    }
  };

  const loadData = async () => {
    try {
      const [examsRes, classesRes, subjectsRes] = await Promise.all([
        supabase.from('exams').select('*').order('created_at', { ascending: false }),
        supabase.from('classes').select('*').order('name'),
        supabase.from('subjects').select('*').order('name'),
      ]);

      if (examsRes.data) setExams(examsRes.data);
      if (classesRes.data) setClasses(classesRes.data);
      if (subjectsRes.data) setSubjects(subjectsRes.data);
    } catch (error) {
      console.error('Error loading data:', error);
    }
  };

  const loadExamSubjects = async (examId: string) => {
    try {
      const { data, error } = await supabase
        .from('exam_subjects')
        .select(`
          *,
          class:classes(*),
          subject:subjects(*)
        `)
        .eq('exam_id', examId);

      if (error) throw error;

      const subjectsWithCounts = await Promise.all(
        (data || []).map(async (es: any) => {
          const { count } = await supabase
            .from('questions')
            .select('*', { count: 'exact', head: true })
            .eq('exam_subject_id', es.id);

          return { ...es, questionCount: count || 0 };
        })
      );

      setExamSubjects(subjectsWithCounts);
    } catch (error) {
      console.error('Error loading exam subjects:', error);
    }
  };

  const handleCreateExam = async () => {
    if (!newExamTitle.trim()) return;

    setLoading(true);
    setMessage('');

    try {
      const { data, error } = await supabase
        .from('exams')
        .insert({
          title: newExamTitle,
          description: newExamDescription || null,
        })
        .select()
        .single();

      if (error) throw error;

      setNewExamTitle('');
      setNewExamDescription('');
      setShowCreateExam(false);
      setMessage('Exam created successfully');
      loadData();
    } catch (error: any) {
      setMessage('Error creating exam: ' + error.message);
    } finally {
      setLoading(false);
    }
  };

  const handleAddSubjectToExam = async () => {
    if (!selectedExamId || !selectedClass || !selectedSubject) return;

    setLoading(true);
    setMessage('');

    try {
      const { error } = await supabase
        .from('exam_subjects')
        .insert({
          exam_id: selectedExamId,
          class_id: selectedClass,
          subject_id: selectedSubject,
          time_limit: timeLimit, // Add time limit
        });

      if (error) throw error;

      setSelectedClass('');
      setSelectedSubject('');
      setTimeLimit(defaultTimeLimit); // Reset to default from school config
      setMessage('Subject added to exam successfully');
      loadExamSubjects(selectedExamId);
    } catch (error: any) {
      setMessage('Error adding subject: ' + error.message);
    } finally {
      setLoading(false);
    }
  };

  const handlePublishExam = async (examId: string, currentStatus: boolean) => {
    const action = currentStatus ? 'unpublish' : 'publish';
    if (!confirm(`Are you sure you want to ${action} this exam?`)) return;

    try {
      const { error } = await supabase
        .from('exams')
        .update({
          is_published: !currentStatus,
          published_at: !currentStatus ? new Date().toISOString() : null,
        })
        .eq('id', examId);

      if (error) throw error;
      loadData();
    } catch (error: any) {
      alert('Error updating exam: ' + error.message);
    }
  };

  const handlePublishExamSubject = async (examSubjectId: string, currentStatus: boolean) => {
    const action = currentStatus ? 'unpublish' : 'publish';
    if (!confirm(`Are you sure you want to ${action} this subject?`)) return;

    try {
      const { error } = await supabase
        .from('exam_subjects')
        .update({
          is_published: !currentStatus,
          published_at: !currentStatus ? new Date().toISOString() : null,
        })
        .eq('id', examSubjectId);

      if (error) throw error;
      
      // Reload exam subjects to reflect changes
      if (selectedExamId) loadExamSubjects(selectedExamId);
    } catch (error: any) {
      alert('Error updating exam subject: ' + error.message);
    }
  };

  const handleUpdateTimeLimit = async (examSubjectId: string, newTimeLimit: number) => {
    if (!confirm('Are you sure you want to update the time limit for this subject?')) return;

    try {
      const { error } = await supabase
        .from('exam_subjects')
        .update({
          time_limit: newTimeLimit,
        })
        .eq('id', examSubjectId);

      if (error) throw error;
      
      // Reload exam subjects to reflect changes
      if (selectedExamId) loadExamSubjects(selectedExamId);
      
      setMessage('Time limit updated successfully');
    } catch (error: any) {
      alert('Error updating time limit: ' + error.message);
    }
  };

  const handleRepublish = async () => {
    if (!selectedExamId || !republishEmail) return;

    setLoading(true);
    setMessage('');

    try {
      const { data: studentData } = await supabase
        .from('profiles')
        .select('id')
        .eq('email', republishEmail)
        .eq('role', 'student')
        .maybeSingle();

      if (!studentData) {
        setMessage('Student not found with this email');
        setLoading(false);
        return;
      }

      await supabase
        .from('exam_republish_log')
        .insert({
          student_id: studentData.id,
          exam_id: selectedExamId,
          exam_subject_id: republishSubjectId || null,
          reason: republishReason || null,
        });

      if (republishSubjectId) {
        await supabase
          .from('exam_attempts')
          .delete()
          .eq('student_id', studentData.id)
          .eq('exam_id', selectedExamId)
          .eq('exam_subject_id', republishSubjectId);
      } else {
        await supabase
          .from('exam_attempts')
          .delete()
          .eq('student_id', studentData.id)
          .eq('exam_id', selectedExamId);
      }

      setRepublishEmail('');
      setRepublishReason('');
      setRepublishSubjectId('');
      setShowRepublish(false);
      setMessage('Exam republished successfully for student');
    } catch (error: any) {
      setMessage('Error republishing exam: ' + error.message);
    } finally {
      setLoading(false);
    }
  };

  const handleDeleteExamSubject = async (id: string) => {
    if (!confirm('Are you sure you want to remove this subject from the exam?')) return;

    try {
      const { error } = await supabase
        .from('exam_subjects')
        .delete()
        .eq('id', id);

      if (error) throw error;
      if (selectedExamId) loadExamSubjects(selectedExamId);
    } catch (error: any) {
      alert('Error deleting exam subject: ' + error.message);
    }
  };

  return (
    <div>
      <h2 className="text-2xl font-bold text-gray-900 mb-6">Exam Management</h2>

      {/* Information Panel */}
      <div className="mb-6 p-4 bg-blue-50 rounded-lg">
        <h3 className="font-semibold text-gray-900 mb-2 flex items-center gap-2">
          <span className="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded-full">
            Info
          </span>
          Exam Workflow
        </h3>
        <p className="text-sm text-gray-600 mb-2">
          You can create different subject instances for different classes if needed (e.g., "Mathematics - JSS1" and "Mathematics - JSS2").
        </p>
        <ol className="list-decimal list-inside text-sm text-gray-700 space-y-1">
          <li>Create Exam - Create a new exam with a title and description</li>
          <li>Add Subjects - Link classes and subjects to the exam with time limits. You can create different subject instances for different classes if needed (e.g., "Mathematics - JSS1" and "Mathematics - JSS2").</li>
          <li>Allocate Teachers - Assign teachers to the exam's classes and subjects</li>
          <li>Add Questions - Teachers can add questions to their allocated exams (even when unpublished)</li>
          <li>Publish Exam - Make the exam available for students to take</li>
          <li>Publish Subjects - Make individual subjects available for students to take</li>
        </ol>
        <p className="mt-2 text-sm text-gray-600">
          <strong>Note:</strong> Teachers can see and work on exams even when they are not published, 
          but students can only see published exams. You can now publish individual subjects within an exam. You can create different subject instances for different classes if needed (e.g., "Mathematics - JSS1" and "Mathematics - JSS2").
        </p>
      </div>

      <div className="mb-8 p-4 bg-gray-50 rounded-lg">
        <div className="flex justify-between items-center mb-4">
          <h3 className="font-semibold text-gray-900">Create New Exam</h3>
          <button
            onClick={() => setShowCreateExam(!showCreateExam)}
            className="flex items-center gap-2 px-3 py-1.5 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
          >
            <Plus className="w-4 h-4" />
            {showCreateExam ? 'Cancel' : 'New Exam'}
          </button>
        </div>

        {showCreateExam && (
          <div className="grid gap-4 mb-4">
            <input
              type="text"
              value={newExamTitle}
              onChange={(e) => setNewExamTitle(e.target.value)}
              placeholder="Exam title"
              className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
            <textarea
              value={newExamDescription}
              onChange={(e) => setNewExamDescription(e.target.value)}
              placeholder="Description (optional)"
              rows={3}
              className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
            <button
              onClick={handleCreateExam}
              disabled={loading || !newExamTitle.trim()}
              className="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Create Exam
            </button>
          </div>
        )}
      </div>

      {message && (
        <div className={`mb-4 p-3 rounded-lg text-sm ${
          message.includes('Error')
            ? 'bg-red-50 text-red-700 border border-red-200'
            : 'bg-green-50 text-green-700 border border-green-200'
        }`}>
          {message}
        </div>
      )}

      <div className="mb-8">
        <h3 className="font-semibold text-gray-900 mb-4">Existing Exams</h3>
        {exams.length === 0 ? (
          <p className="text-gray-500 text-center py-8">No exams created yet</p>
        ) : (
          <div className="space-y-2">
            {exams.map((exam) => (
              <div
                key={exam.id}
                className="border border-gray-200 rounded-lg p-4 hover:bg-gray-50"
              >
                <div className="flex items-start justify-between">
                  <div className="flex-1">
                    <div className="flex items-center gap-3">
                      <h4 className="font-medium text-gray-900">{exam.title}</h4>
                      {exam.is_published ? (
                        <span className="flex items-center gap-1 px-2 py-1 bg-green-100 text-green-700 text-xs rounded-full">
                          <CheckCircle className="w-3 h-3" />
                          Published
                        </span>
                      ) : (
                        <span className="flex items-center gap-1 px-2 py-1 bg-gray-100 text-gray-700 text-xs rounded-full">
                          <XCircle className="w-3 h-3" />
                          Draft
                        </span>
                      )}
                    </div>
                    {exam.description && (
                      <p className="text-sm text-gray-600 mt-1">{exam.description}</p>
                    )}
                  </div>
                  <div className="flex gap-2">
                    <button
                      onClick={() => {
                        setSelectedExamId(exam.id === selectedExamId ? null : exam.id);
                        setShowRepublish(false);
                      }}
                      className="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                    >
                      <Eye className="w-4 h-4" />
                    </button>
                    <button
                      onClick={() => handlePublishExam(exam.id, exam.is_published)}
                      className={`p-2 rounded-lg transition-colors ${
                        exam.is_published
                          ? 'text-orange-600 hover:bg-orange-50'
                          : 'text-green-600 hover:bg-green-50'
                      }`}
                    >
                      {exam.is_published ? <XCircle className="w-4 h-4" /> : <CheckCircle className="w-4 h-4" />}
                    </button>
                  </div>
                </div>

                {selectedExamId === exam.id && (
                  <div className="mt-4 pt-4 border-t border-gray-200">
                    <div className="mb-4">
                      <div className="flex justify-between items-center mb-3">
                        <h5 className="font-medium text-gray-900">Subjects in This Exam</h5>
                        <button
                          onClick={() => setShowRepublish(!showRepublish)}
                          className="flex items-center gap-2 px-3 py-1.5 text-sm bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors"
                        >
                          <RefreshCw className="w-3 h-3" />
                          Republish for Student
                        </button>
                      </div>

                      {showRepublish && (
                        <div className="mb-4 p-3 bg-orange-50 border border-orange-200 rounded-lg">
                          <h6 className="font-medium text-gray-900 mb-2">Republish Exam</h6>
                          <div className="grid gap-2">
                            <input
                              type="email"
                              value={republishEmail}
                              onChange={(e) => setRepublishEmail(e.target.value)}
                              placeholder="Student email"
                              className="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                            />
                            <select
                              value={republishSubjectId}
                              onChange={(e) => setRepublishSubjectId(e.target.value)}
                              className="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                            >
                              <option value="">All subjects (entire exam)</option>
                              {examSubjects.map((es) => (
                                <option key={es.id} value={es.id}>
                                  {es.class.name} - {es.subject.name}
                                </option>
                              ))}
                            </select>
                            <input
                              type="text"
                              value={republishReason}
                              onChange={(e) => setRepublishReason(e.target.value)}
                              placeholder="Reason (optional)"
                              className="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                            />
                            <button
                              onClick={handleRepublish}
                              disabled={loading || !republishEmail}
                              className="px-3 py-2 text-sm bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors disabled:opacity-50"
                            >
                              Republish
                            </button>
                          </div>
                        </div>
                      )}

                      {examSubjects.length === 0 ? (
                        <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                          <p className="text-sm text-yellow-700">
                            <span className="font-medium">No subjects added to this exam yet.</span> Add subjects to make this exam available to teachers.
                          </p>
                        </div>
                      ) : (
                        <div className="space-y-2 mb-4">
                          {examSubjects.map((es) => (
                            <div
                              key={es.id}
                              className="flex items-center justify-between p-3 bg-white border border-gray-200 rounded-lg"
                            >
                              <div>
                                <p className="text-sm font-medium text-gray-900">
                                  {es.class.name} - {es.subject.name}
                                </p>
                                <div className="flex items-center gap-2 mt-1">
                                  <p className="text-xs text-gray-600">
                                    {es.questionCount || 0} questions
                                  </p>
                                  <span className="text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded">
                                    {(es.time_limit || 60)} min
                                  </span>
                                  {es.is_published ? (
                                    <span className="text-xs bg-green-100 text-green-800 px-2 py-0.5 rounded">
                                      Published
                                    </span>
                                  ) : (
                                    <span className="text-xs bg-yellow-100 text-yellow-800 px-2 py-0.5 rounded">
                                      Draft
                                    </span>
                                  )}
                                </div>
                              </div>
                              <div className="flex items-center gap-2">
                                {/* Publish/Unpublish Subject Button */}
                                <button
                                  onClick={() => handlePublishExamSubject(es.id, !!es.is_published)}
                                  className={`p-1.5 rounded-lg transition-colors ${
                                    es.is_published
                                      ? 'text-orange-600 hover:bg-orange-50'
                                      : 'text-green-600 hover:bg-green-50'
                                  }`}
                                >
                                  {es.is_published ? <XCircle className="w-3 h-3" /> : <CheckCircle className="w-3 h-3" />}
                                </button>
                                
                                {/* Edit Time Limit Button */}
                                <button
                                  onClick={() => {
                                    const newTimeLimit = prompt('Enter new time limit (minutes):', (es.time_limit || 60).toString());
                                    if (newTimeLimit && !isNaN(parseInt(newTimeLimit))) {
                                      handleUpdateTimeLimit(es.id, parseInt(newTimeLimit));
                                    }
                                  }}
                                  className="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                >
                                  <svg xmlns="http://www.w3.org/2000/svg" className="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                  </svg>
                                </button>
                                
                                <button
                                  onClick={() => handleDeleteExamSubject(es.id)}
                                  className="p-1.5 text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                >
                                  <Trash2 className="w-3 h-3" />
                                </button>
                              </div>
                            </div>
                          ))}
                        </div>
                      )}
                    </div>

                    <div className="p-3 bg-white border border-gray-200 rounded-lg">
                      <h5 className="font-medium text-gray-900 mb-3">Add Subject to Exam</h5>
                      <div className="grid gap-2">
                        <select
                          value={selectedClass}
                          onChange={(e) => setSelectedClass(e.target.value)}
                          className="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                          <option value="">Select Class</option>
                          {classes.map((cls) => (
                            <option key={cls.id} value={cls.id}>
                              {cls.name}
                            </option>
                          ))}
                        </select>
                        <select
                          value={selectedSubject}
                          onChange={(e) => setSelectedSubject(e.target.value)}
                          className="px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                          <option value="">Select Subject</option>
                          {subjects.map((subject) => (
                            <option key={subject.id} value={subject.id}>
                              {subject.name}
                            </option>
                          ))}
                        </select>
                        <div>
                          <label className="block text-xs font-medium text-gray-700 mb-1">
                            Time Limit (minutes)
                          </label>
                          <input
                            type="number"
                            value={timeLimit}
                            onChange={(e) => setTimeLimit(parseInt(e.target.value) || defaultTimeLimit)}
                            min="1"
                            max="300"
                            className="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                          />
                          <p className="text-xs text-gray-500 mt-1">
                            Enter time limit in minutes (1-300). 
                            {defaultTimeLimit !== 60 ? ` Default from school settings: ${defaultTimeLimit} minutes.` : ''}
                          </p>
                        </div>
                        <button
                          onClick={handleAddSubjectToExam}
                          disabled={loading || !selectedClass || !selectedSubject}
                          className="flex items-center justify-center gap-2 px-3 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50"
                        >
                          <Plus className="w-3 h-3" />
                          Add Subject
                        </button>
                      </div>
                    </div>
                  </div>
                )}
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
};