import { useState, useEffect } from 'react';
import { useAuth } from '../contexts/AuthContext';
import { supabase, TeacherAllocation, Class, Subject, Exam, ExamSubject, Question } from '../lib/supabase';
import { LogOut, Plus, Trash2, Edit2 } from 'lucide-react';

export const TeacherDashboard = () => {
  const { profile, signOut } = useAuth();
  const [allocations, setAllocations] = useState<(TeacherAllocation & { class: Class; subject: Subject })[]>([]);
  const [exams, setExams] = useState<Exam[]>([]);
  const [examSubjects, setExamSubjects] = useState<(ExamSubject & { class: Class; subject: Subject; exam: Exam })[]>([]);
  const [selectedExamSubject, setSelectedExamSubject] = useState<string | null>(null);
  const [questions, setQuestions] = useState<Question[]>([]);
  const [showAddQuestion, setShowAddQuestion] = useState(false);
  const [editingQuestion, setEditingQuestion] = useState<Question | null>(null);
  const [questionText, setQuestionText] = useState('');
  const [optionA, setOptionA] = useState('');
  const [optionB, setOptionB] = useState('');
  const [optionC, setOptionC] = useState('');
  const [optionD, setOptionD] = useState('');
  const [correctAnswer, setCorrectAnswer] = useState<'A' | 'B' | 'C' | 'D'>('A');
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState('');

  useEffect(() => {
    loadAllocations();
    loadExams();
  }, [profile]);

  useEffect(() => {
    if (allocations.length > 0 && exams.length > 0) {
      loadExamSubjects();
    }
  }, [allocations, exams]);

  useEffect(() => {
    if (selectedExamSubject) {
      loadQuestions(selectedExamSubject);
    }
  }, [selectedExamSubject]);

  const loadAllocations = async () => {
    if (!profile) return;

    try {
      const { data, error } = await supabase
        .from('teacher_allocations')
        .select(`
          *,
          class:classes(*),
          subject:subjects(*)
        `)
        .eq('teacher_id', profile.id);

      if (error) throw error;
      setAllocations(data as any || []);
    } catch (error) {
      console.error('Error loading allocations:', error);
    }
  };

  const loadExams = async () => {
    try {
      const { data, error } = await supabase
        .from('exams')
        .select('*')
        .order('created_at', { ascending: false });

      if (error) throw error;
      setExams(data || []);
    } catch (error) {
      console.error('Error loading exams:', error);
    }
  };

  const loadExamSubjects = async () => {
    if (allocations.length === 0) return;

    try {
      const classIds = allocations.map(a => a.class_id);
      const subjectIds = allocations.map(a => a.subject_id);

      const { data, error } = await supabase
        .from('exam_subjects')
        .select(`
          *,
          class:classes(*),
          subject:subjects(*),
          exam:exams(*)
        `)
        .in('class_id', classIds)
        .in('subject_id', subjectIds);

      if (error) throw error;

      const filtered = (data as any || []).filter((es: any) => {
        return allocations.some(
          a => a.class_id === es.class_id && a.subject_id === es.subject_id
        );
      });

      setExamSubjects(filtered);
    } catch (error) {
      console.error('Error loading exam subjects:', error);
    }
  };

  const loadQuestions = async (examSubjectId: string) => {
    try {
      const { data, error } = await supabase
        .from('questions')
        .select('*')
        .eq('exam_subject_id', examSubjectId)
        .order('created_at');

      if (error) throw error;
      setQuestions(data || []);
    } catch (error) {
      console.error('Error loading questions:', error);
    }
  };

  const handleAddQuestion = async () => {
    if (!selectedExamSubject || !questionText || !optionA || !optionB || !optionC || !optionD) return;

    setLoading(true);
    setMessage('');

    try {
      const { error } = await supabase
        .from('questions')
        .insert({
          exam_subject_id: selectedExamSubject,
          question_text: questionText,
          option_a: optionA,
          option_b: optionB,
          option_c: optionC,
          option_d: optionD,
          correct_answer: correctAnswer,
        });

      if (error) throw error;

      resetForm();
      setMessage('Question added successfully');
      loadQuestions(selectedExamSubject);
    } catch (error: any) {
      setMessage('Error adding question: ' + error.message);
    } finally {
      setLoading(false);
    }
  };

  const handleUpdateQuestion = async () => {
    if (!editingQuestion || !questionText || !optionA || !optionB || !optionC || !optionD) return;

    setLoading(true);
    setMessage('');

    try {
      const { error } = await supabase
        .from('questions')
        .update({
          question_text: questionText,
          option_a: optionA,
          option_b: optionB,
          option_c: optionC,
          option_d: optionD,
          correct_answer: correctAnswer,
        })
        .eq('id', editingQuestion.id);

      if (error) throw error;

      resetForm();
      setMessage('Question updated successfully');
      if (selectedExamSubject) loadQuestions(selectedExamSubject);
    } catch (error: any) {
      setMessage('Error updating question: ' + error.message);
    } finally {
      setLoading(false);
    }
  };

  const handleDeleteQuestion = async (id: string) => {
    if (!confirm('Are you sure you want to delete this question?')) return;

    try {
      const { error } = await supabase
        .from('questions')
        .delete()
        .eq('id', id);

      if (error) throw error;
      if (selectedExamSubject) loadQuestions(selectedExamSubject);
    } catch (error: any) {
      alert('Error deleting question: ' + error.message);
    }
  };

  const startEditQuestion = (question: Question) => {
    setEditingQuestion(question);
    setQuestionText(question.question_text);
    setOptionA(question.option_a);
    setOptionB(question.option_b);
    setOptionC(question.option_c);
    setOptionD(question.option_d);
    setCorrectAnswer(question.correct_answer);
    setShowAddQuestion(true);
  };

  const resetForm = () => {
    setQuestionText('');
    setOptionA('');
    setOptionB('');
    setOptionC('');
    setOptionD('');
    setCorrectAnswer('A');
    setShowAddQuestion(false);
    setEditingQuestion(null);
  };

  const handleSignOut = async () => {
    try {
      await signOut();
    } catch (error) {
      console.error('Error signing out:', error);
    }
  };

  return (
    <div className="min-h-screen bg-gray-50">
      <header className="bg-white shadow-sm border-b border-gray-200">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex justify-between items-center h-16">
            <div>
              <h1 className="text-2xl font-bold text-gray-900">Teacher Dashboard</h1>
              <p className="text-sm text-gray-600">Welcome, {profile?.full_name}</p>
            </div>
            <button
              onClick={handleSignOut}
              className="flex items-center gap-2 px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
            >
              <LogOut className="w-4 h-4" />
              Sign Out
            </button>
          </div>
        </div>
      </header>

      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div className="mb-8">
          <h2 className="text-xl font-bold text-gray-900 mb-4">Your Allocations</h2>
          {allocations.length === 0 ? (
            <p className="text-gray-500">No allocations assigned yet</p>
          ) : (
            <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
              {allocations.map((allocation) => (
                <div
                  key={allocation.id}
                  className="p-4 bg-white border border-gray-200 rounded-lg"
                >
                  <p className="font-medium text-gray-900">{allocation.class.name}</p>
                  <p className="text-sm text-gray-600">{allocation.subject.name}</p>
                </div>
              ))}
            </div>
          )}
        </div>

        <div className="bg-white rounded-xl shadow-sm p-6">
          <h2 className="text-xl font-bold text-gray-900 mb-4">Question Management</h2>

          <div className="mb-6">
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Select Exam Subject
            </label>
            <select
              value={selectedExamSubject || ''}
              onChange={(e) => {
                setSelectedExamSubject(e.target.value || null);
                resetForm();
              }}
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
              <option value="">Select an exam subject</option>
              {examSubjects.map((es) => (
                <option key={es.id} value={es.id}>
                  {es.exam.title} - {es.class.name} - {es.subject.name}
                  {es.exam.is_published ? ' (Published)' : ' (Draft)'}
                </option>
              ))}
            </select>
          </div>

          {selectedExamSubject && (
            <>
              {message && (
                <div className={`mb-4 p-3 rounded-lg text-sm ${
                  message.includes('Error')
                    ? 'bg-red-50 text-red-700 border border-red-200'
                    : 'bg-green-50 text-green-700 border border-green-200'
                }`}>
                  {message}
                </div>
              )}

              <div className="mb-6">
                <button
                  onClick={() => setShowAddQuestion(!showAddQuestion)}
                  className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                >
                  <Plus className="w-4 h-4" />
                  {showAddQuestion ? 'Cancel' : 'Add Question'}
                </button>
              </div>

              {showAddQuestion && (
                <div className="mb-6 p-4 bg-gray-50 rounded-lg">
                  <h3 className="font-semibold text-gray-900 mb-4">
                    {editingQuestion ? 'Edit Question' : 'Add New Question'}
                  </h3>
                  <div className="space-y-4">
                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        Question Text
                      </label>
                      <textarea
                        value={questionText}
                        onChange={(e) => setQuestionText(e.target.value)}
                        rows={3}
                        className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Enter the question"
                      />
                    </div>

                    <div className="grid md:grid-cols-2 gap-4">
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                          Option A
                        </label>
                        <input
                          type="text"
                          value={optionA}
                          onChange={(e) => setOptionA(e.target.value)}
                          className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                          Option B
                        </label>
                        <input
                          type="text"
                          value={optionB}
                          onChange={(e) => setOptionB(e.target.value)}
                          className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                          Option C
                        </label>
                        <input
                          type="text"
                          value={optionC}
                          onChange={(e) => setOptionC(e.target.value)}
                          className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                          Option D
                        </label>
                        <input
                          type="text"
                          value={optionD}
                          onChange={(e) => setOptionD(e.target.value)}
                          className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        />
                      </div>
                    </div>

                    <div>
                      <label className="block text-sm font-medium text-gray-700 mb-1">
                        Correct Answer
                      </label>
                      <select
                        value={correctAnswer}
                        onChange={(e) => setCorrectAnswer(e.target.value as 'A' | 'B' | 'C' | 'D')}
                        className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                      >
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                        <option value="D">D</option>
                      </select>
                    </div>

                    <div className="flex gap-2">
                      <button
                        onClick={editingQuestion ? handleUpdateQuestion : handleAddQuestion}
                        disabled={loading || !questionText || !optionA || !optionB || !optionC || !optionD}
                        className="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                      >
                        {loading ? 'Saving...' : editingQuestion ? 'Update Question' : 'Add Question'}
                      </button>
                      {editingQuestion && (
                        <button
                          onClick={resetForm}
                          className="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors"
                        >
                          Cancel Edit
                        </button>
                      )}
                    </div>
                  </div>
                </div>
              )}

              <div>
                <h3 className="font-semibold text-gray-900 mb-4">
                  Questions ({questions.length})
                </h3>
                {questions.length === 0 ? (
                  <p className="text-gray-500 text-center py-8">No questions added yet</p>
                ) : (
                  <div className="space-y-4">
                    {questions.map((question, index) => (
                      <div
                        key={question.id}
                        className="p-4 border border-gray-200 rounded-lg hover:bg-gray-50"
                      >
                        <div className="flex justify-between items-start mb-3">
                          <h4 className="font-medium text-gray-900">
                            Question {index + 1}
                          </h4>
                          <div className="flex gap-2">
                            <button
                              onClick={() => startEditQuestion(question)}
                              className="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                            >
                              <Edit2 className="w-4 h-4" />
                            </button>
                            <button
                              onClick={() => handleDeleteQuestion(question.id)}
                              className="p-1.5 text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                            >
                              <Trash2 className="w-4 h-4" />
                            </button>
                          </div>
                        </div>
                        <p className="text-gray-900 mb-3">{question.question_text}</p>
                        <div className="grid md:grid-cols-2 gap-2 text-sm">
                          <div className={`p-2 rounded ${question.correct_answer === 'A' ? 'bg-green-50 border border-green-200' : 'bg-gray-50'}`}>
                            <span className="font-medium">A:</span> {question.option_a}
                          </div>
                          <div className={`p-2 rounded ${question.correct_answer === 'B' ? 'bg-green-50 border border-green-200' : 'bg-gray-50'}`}>
                            <span className="font-medium">B:</span> {question.option_b}
                          </div>
                          <div className={`p-2 rounded ${question.correct_answer === 'C' ? 'bg-green-50 border border-green-200' : 'bg-gray-50'}`}>
                            <span className="font-medium">C:</span> {question.option_c}
                          </div>
                          <div className={`p-2 rounded ${question.correct_answer === 'D' ? 'bg-green-50 border border-green-200' : 'bg-gray-50'}`}>
                            <span className="font-medium">D:</span> {question.option_d}
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </>
          )}
        </div>
      </div>
    </div>
  );
};
