import { useState, useEffect } from 'react';
import { useAuth } from '../contexts/AuthContext';
import { supabase, Exam, ExamSubject, Class, Subject } from '../lib/supabase';
import { LogOut, BookOpen } from 'lucide-react';
import { ExamTaking } from './student/ExamTaking';

export const StudentDashboard = () => {
  const { profile, signOut } = useAuth();
  const [exams, setExams] = useState<Exam[]>([]);
  const [examSubjects, setExamSubjects] = useState<(ExamSubject & { class: Class; subject: Subject; exam: Exam })[]>([]);
  const [selectedExamSubject, setSelectedExamSubject] = useState<(ExamSubject & { class: Class; subject: Subject; exam: Exam }) | null>(null);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    loadExams();
  }, []);

  const loadExams = async () => {
    try {
      const { data: examsData, error: examsError } = await supabase
        .from('exams')
        .select('*')
        .eq('is_published', true)
        .order('published_at', { ascending: false });

      if (examsError) throw examsError;
      setExams(examsData || []);

      if (examsData && examsData.length > 0) {
        const examIds = examsData.map(e => e.id);
        const { data: subjectsData, error: subjectsError } = await supabase
          .from('exam_subjects')
          .select(`
            *,
            class:classes(*),
            subject:subjects(*),
            exam:exams(*)
          `)
          .in('exam_id', examIds);

        if (subjectsError) throw subjectsError;
        setExamSubjects(subjectsData as any || []);
      }
    } catch (error) {
      console.error('Error loading exams:', error);
    }
  };

  const handleSignOut = async () => {
    try {
      await signOut();
    } catch (error) {
      console.error('Error signing out:', error);
    }
  };

  const handleStartExam = (examSubject: ExamSubject & { class: Class; subject: Subject; exam: Exam }) => {
    setSelectedExamSubject(examSubject);
  };

  const handleExitExam = () => {
    setSelectedExamSubject(null);
    loadExams();
  };

  if (selectedExamSubject) {
    return <ExamTaking examSubject={selectedExamSubject} onExit={handleExitExam} />;
  }

  return (
    <div className="min-h-screen bg-gray-50">
      <header className="bg-white shadow-sm border-b border-gray-200">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex justify-between items-center h-16">
            <div>
              <h1 className="text-2xl font-bold text-gray-900">Student Dashboard</h1>
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
        <div className="bg-white rounded-xl shadow-sm p-6">
          <h2 className="text-2xl font-bold text-gray-900 mb-6">Available Exams</h2>

          {exams.length === 0 ? (
            <div className="text-center py-12">
              <BookOpen className="w-16 h-16 text-gray-400 mx-auto mb-4" />
              <p className="text-gray-500 text-lg">No exams available at the moment</p>
            </div>
          ) : (
            <div className="space-y-6">
              {exams.map((exam) => {
                const subjects = examSubjects.filter(es => es.exam_id === exam.id);

                return (
                  <div key={exam.id} className="border border-gray-200 rounded-lg p-6">
                    <h3 className="text-xl font-bold text-gray-900 mb-2">{exam.title}</h3>
                    {exam.description && (
                      <p className="text-gray-600 mb-4">{exam.description}</p>
                    )}

                    {subjects.length === 0 ? (
                      <p className="text-gray-500 text-sm">No subjects available for this exam</p>
                    ) : (
                      <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-4">
                        {subjects.map((es) => (
                          <SubjectCard
                            key={es.id}
                            examSubject={es}
                            onStart={() => handleStartExam(es)}
                          />
                        ))}
                      </div>
                    )}
                  </div>
                );
              })}
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

interface SubjectCardProps {
  examSubject: ExamSubject & { class: Class; subject: Subject; exam: Exam };
  onStart: () => void;
}

const SubjectCard = ({ examSubject, onStart }: SubjectCardProps) => {
  const { profile } = useAuth();
  const [attempt, setAttempt] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    checkAttempt();
  }, []);

  const checkAttempt = async () => {
    if (!profile) return;

    try {
      const { data, error } = await supabase
        .from('exam_attempts')
        .select('*')
        .eq('student_id', profile.id)
        .eq('exam_subject_id', examSubject.id)
        .order('started_at', { ascending: false })
        .maybeSingle();

      if (error) throw error;
      setAttempt(data);
    } catch (error) {
      console.error('Error checking attempt:', error);
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <div className="p-4 border border-gray-200 rounded-lg">
        <div className="animate-pulse">
          <div className="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
          <div className="h-3 bg-gray-200 rounded w-1/2"></div>
        </div>
      </div>
    );
  }

  return (
    <div className="p-4 border border-gray-200 rounded-lg hover:border-blue-500 transition-colors">
      <h4 className="font-semibold text-gray-900">{examSubject.subject.name}</h4>
      <p className="text-sm text-gray-600 mb-3">{examSubject.class.name}</p>

      {attempt?.submitted_at ? (
        <div className="text-sm">
          <div className="mb-2">
            <span className="px-2 py-1 bg-green-100 text-green-700 text-xs rounded-full">
              Completed
            </span>
          </div>
          <p className="text-gray-700">
            Score: <span className="font-bold">{attempt.score}/{attempt.total_questions}</span>
          </p>
        </div>
      ) : attempt && !attempt.submitted_at ? (
        <button
          onClick={onStart}
          className="w-full px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors text-sm"
        >
          Continue Exam
        </button>
      ) : (
        <button
          onClick={onStart}
          className="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm"
        >
          Start Exam
        </button>
      )}
    </div>
  );
};
