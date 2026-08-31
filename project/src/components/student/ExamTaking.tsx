import { useState, useEffect, useRef } from 'react';
import { useAuth } from '../../contexts/AuthContext';
import { supabase, ExamSubject, Class, Subject, Exam, Question, ExamAttempt, StudentAnswer } from '../../lib/supabase';
import { ChevronLeft, ChevronRight, AlertTriangle, CheckCircle } from 'lucide-react';

interface ExamTakingProps {
  examSubject: ExamSubject & { class: Class; subject: Subject; exam: Exam };
  onExit: () => void;
}

export const ExamTaking = ({ examSubject, onExit }: ExamTakingProps) => {
  const { profile } = useAuth();
  const [questions, setQuestions] = useState<Question[]>([]);
  const [currentQuestionIndex, setCurrentQuestionIndex] = useState(0);
  const [answers, setAnswers] = useState<Map<string, 'A' | 'B' | 'C' | 'D'>>(new Map());
  const [attempt, setAttempt] = useState<ExamAttempt | null>(null);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [showWarning, setShowWarning] = useState(false);
  const [timeRemaining, setTimeRemaining] = useState<number>(0);
  const timerRef = useRef<NodeJS.Timeout | null>(null);

  useEffect(() => {
    initializeExam();
    return () => {
      if (timerRef.current) {
        clearInterval(timerRef.current);
      }
    };
  }, []);

  useEffect(() => {
    if (timeRemaining > 0) {
      timerRef.current = setInterval(() => {
        setTimeRemaining(prev => {
          if (prev <= 1) {
            if (timerRef.current) clearInterval(timerRef.current);
            handleSubmit(); // Auto-submit when time is up
            return 0;
          }
          return prev - 1;
        });
      }, 1000);
    }

    return () => {
      if (timerRef.current) {
        clearInterval(timerRef.current);
      }
    };
  }, [timeRemaining]);

  const initializeExam = async () => {
    if (!profile) return;

    try {
      const { data: questionsData, error: questionsError } = await supabase
        .from('questions')
        .select('*')
        .eq('exam_subject_id', examSubject.id)
        .order('created_at');

      if (questionsError) throw questionsError;

      if (!questionsData || questionsData.length === 0) {
        alert('No questions available for this exam');
        onExit();
        return;
      }

      setQuestions(questionsData);

      const { data: existingAttempt, error: attemptError } = await supabase
        .from('exam_attempts')
        .select('*')
        .eq('student_id', profile.id)
        .eq('exam_subject_id', examSubject.id)
        .is('submitted_at', null)
        .maybeSingle();

      if (attemptError) throw attemptError;

      if (existingAttempt) {
        setAttempt(existingAttempt);

        // Calculate time remaining based on started_at
        if (existingAttempt.started_at) {
          const startedAt = new Date(existingAttempt.started_at);
          const now = new Date();
          const elapsedSeconds = Math.floor((now.getTime() - startedAt.getTime()) / 1000);
          const totalTimeSeconds = examSubject.time_limit ? examSubject.time_limit * 60 : 3600; // Default to 60 minutes
          const remainingSeconds = Math.max(0, totalTimeSeconds - elapsedSeconds);
          setTimeRemaining(remainingSeconds);
        } else {
          // If not started yet, set full time
          setTimeRemaining(examSubject.time_limit ? examSubject.time_limit * 60 : 3600);
        }

        const { data: existingAnswers } = await supabase
          .from('student_answers')
          .select('*')
          .eq('attempt_id', existingAttempt.id);

        if (existingAnswers) {
          const answerMap = new Map<string, 'A' | 'B' | 'C' | 'D'>();
          existingAnswers.forEach(answer => {
            if (answer.selected_answer) {
              answerMap.set(answer.question_id, answer.selected_answer);
            }
          });
          setAnswers(answerMap);
        }
      } else {
        const { data: newAttempt, error: createError } = await supabase
          .from('exam_attempts')
          .insert({
            student_id: profile.id,
            exam_id: examSubject.exam_id,
            exam_subject_id: examSubject.id,
            total_questions: questionsData.length,
          })
          .select()
          .single();

        if (createError) throw createError;
        setAttempt(newAttempt);
        
        // Set full time for new attempt
        setTimeRemaining(examSubject.time_limit ? examSubject.time_limit * 60 : 3600);
      }
    } catch (error) {
      console.error('Error initializing exam:', error);
      alert('Error loading exam. Please try again.');
      onExit();
    } finally {
      setLoading(false);
    }
  };

  const handleAnswerSelect = async (answer: 'A' | 'B' | 'C' | 'D') => {
    if (!attempt) return;

    const currentQuestion = questions[currentQuestionIndex];
    const newAnswers = new Map(answers);
    newAnswers.set(currentQuestion.id, answer);
    setAnswers(newAnswers);

    try {
      const { data: existingAnswer } = await supabase
        .from('student_answers')
        .select('*')
        .eq('attempt_id', attempt.id)
        .eq('question_id', currentQuestion.id)
        .maybeSingle();

      if (existingAnswer) {
        await supabase
          .from('student_answers')
          .update({
            selected_answer: answer,
            is_correct: answer === currentQuestion.correct_answer,
          })
          .eq('id', existingAnswer.id);
      } else {
        await supabase
          .from('student_answers')
          .insert({
            attempt_id: attempt.id,
            question_id: currentQuestion.id,
            selected_answer: answer,
            is_correct: answer === currentQuestion.correct_answer,
          });
      }
    } catch (error) {
      console.error('Error saving answer:', error);
    }
  };

  const handleNext = () => {
    if (currentQuestionIndex < questions.length - 1) {
      setCurrentQuestionIndex(currentQuestionIndex + 1);
    }
  };

  const handlePrevious = () => {
    if (currentQuestionIndex > 0) {
      setCurrentQuestionIndex(currentQuestionIndex - 1);
    }
  };

  const handleQuestionNavigate = (index: number) => {
    setCurrentQuestionIndex(index);
  };

  const handleSubmit = async () => {
    if (!attempt) return;

    setSubmitting(true);

    try {
      const { data: submittedAnswers } = await supabase
        .from('student_answers')
        .select('*')
        .eq('attempt_id', attempt.id);

      const correctAnswers = submittedAnswers?.filter(a => a.is_correct).length || 0;

      await supabase
        .from('exam_attempts')
        .update({
          submitted_at: new Date().toISOString(),
          score: correctAnswers,
        })
        .eq('id', attempt.id);

      // Clear timer
      if (timerRef.current) {
        clearInterval(timerRef.current);
      }

      alert(`Exam submitted! Your score: ${correctAnswers}/${questions.length}`);
      onExit();
    } catch (error) {
      console.error('Error submitting exam:', error);
      alert('Error submitting exam. Please try again.');
    } finally {
      setSubmitting(false);
      setShowWarning(false);
    }
  };

  const formatTime = (seconds: number) => {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = seconds % 60;
    
    if (hours > 0) {
      return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    }
    return `${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
          <p className="text-gray-600">Loading exam...</p>
        </div>
      </div>
    );
  }

  if (questions.length === 0) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <p className="text-gray-600 mb-4">No questions available</p>
          <button
            onClick={onExit}
            className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
          >
            Back to Dashboard
          </button>
        </div>
      </div>
    );
  }

  const currentQuestion = questions[currentQuestionIndex];
  const currentAnswer = answers.get(currentQuestion.id);
  const answeredCount = answers.size;

  return (
    <div className="min-h-screen bg-gray-50">
      <header className="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-10">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
          <div className="flex justify-between items-center">
            <div>
              <h1 className="text-xl font-bold text-gray-900">{examSubject.exam.title}</h1>
              <p className="text-sm text-gray-600">
                {examSubject.class.name} - {examSubject.subject.name}
                <span className="ml-2 text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded">
                  {examSubject.time_limit || 60} min
                </span>
              </p>
            </div>
            <div className="flex items-center gap-4">
              <div className="text-right">
                <p className="text-sm text-gray-600">Time Remaining</p>
                <p className={`font-semibold text-lg ${timeRemaining < 300 ? 'text-red-600' : 'text-gray-900'}`}>
                  {formatTime(timeRemaining)}
                </p>
              </div>
              <div className="text-right">
                <p className="text-sm text-gray-600">Progress</p>
                <p className="font-semibold text-gray-900">
                  {answeredCount} / {questions.length} answered
                </p>
              </div>
              <button
                onClick={() => setShowWarning(true)}
                className="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors"
              >
                Submit Exam
              </button>
            </div>
          </div>
        </div>
      </header>

      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div className="grid lg:grid-cols-4 gap-6">
          <div className="lg:col-span-3">
            <div className="bg-white rounded-xl shadow-sm p-8">
              <div className="mb-6">
                <div className="flex items-center justify-between mb-4">
                  <h2 className="text-lg font-semibold text-gray-900">
                    Question {currentQuestionIndex + 1} of {questions.length}
                  </h2>
                  {currentAnswer && (
                    <span className="px-3 py-1 bg-blue-100 text-blue-700 text-sm rounded-full">
                      Answered
                    </span>
                  )}
                </div>
                <p className="text-lg text-gray-900 leading-relaxed">
                  {currentQuestion.question_text}
                </p>
              </div>

              <div className="space-y-3 mb-8">
                {(['A', 'B', 'C', 'D'] as const).map((option) => {
                  const optionText = currentQuestion[`option_${option.toLowerCase()}` as keyof Question] as string;
                  const isSelected = currentAnswer === option;

                  return (
                    <button
                      key={option}
                      onClick={() => handleAnswerSelect(option)}
                      className={`w-full text-left p-4 border-2 rounded-lg transition-all ${
                        isSelected
                          ? 'border-blue-600 bg-blue-50'
                          : 'border-gray-200 hover:border-blue-300 hover:bg-gray-50'
                      }`}
                    >
                      <div className="flex items-start gap-3">
                        <div className={`flex-shrink-0 w-6 h-6 rounded-full border-2 flex items-center justify-center ${
                          isSelected
                            ? 'border-blue-600 bg-blue-600'
                            : 'border-gray-300'
                        }`}>
                          {isSelected && <CheckCircle className="w-4 h-4 text-white" />}
                        </div>
                        <div className="flex-1">
                          <span className="font-semibold text-gray-900">{option}.</span>{' '}
                          <span className="text-gray-700">{optionText}</span>
                        </div>
                      </div>
                    </button>
                  );
                })}
              </div>

              <div className="flex justify-between items-center pt-6 border-t border-gray-200">
                <button
                  onClick={handlePrevious}
                  disabled={currentQuestionIndex === 0}
                  className="flex items-center gap-2 px-6 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <ChevronLeft className="w-5 h-5" />
                  Previous
                </button>

                <button
                  onClick={handleNext}
                  disabled={currentQuestionIndex === questions.length - 1}
                  className="flex items-center gap-2 px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Next
                  <ChevronRight className="w-5 h-5" />
                </button>
              </div>
            </div>
          </div>

          <div className="lg:col-span-1">
            <div className="bg-white rounded-xl shadow-sm p-4 sticky top-24">
              <h3 className="font-semibold text-gray-900 mb-4">Question Bank</h3>
              <div className="grid grid-cols-5 gap-2">
                {questions.map((q, index) => {
                  const isAnswered = answers.has(q.id);
                  const isCurrent = index === currentQuestionIndex;

                  return (
                    <button
                      key={q.id}
                      onClick={() => handleQuestionNavigate(index)}
                      className={`aspect-square rounded-lg font-medium text-sm transition-colors ${
                        isCurrent
                          ? 'bg-blue-600 text-white'
                          : isAnswered
                          ? 'bg-green-100 text-green-700 hover:bg-green-200'
                          : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                      }`}
                    >
                      {index + 1}
                    </button>
                  );
                })}
              </div>
            </div>
          </div>
        </div>
      </div>

      {showWarning && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-xl shadow-xl max-w-md w-full p-6">
            <div className="flex items-center gap-3 mb-4">
              <div className="flex-shrink-0 w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center">
                <AlertTriangle className="w-6 h-6 text-orange-600" />
              </div>
              <h3 className="text-xl font-bold text-gray-900">Submit Exam?</h3>
            </div>

            <p className="text-gray-700 mb-4">
              You have answered {answeredCount} out of {questions.length} questions.
            </p>

            {answeredCount < questions.length && (
              <p className="text-orange-700 bg-orange-50 p-3 rounded-lg text-sm mb-4">
                Warning: You have {questions.length - answeredCount} unanswered questions.
                Unanswered questions will be marked as incorrect.
              </p>
            )}

            <p className="text-gray-700 mb-6">
              Are you sure you want to submit your exam? This action cannot be undone.
            </p>

            <div className="flex gap-3">
              <button
                onClick={() => setShowWarning(false)}
                className="flex-1 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors"
              >
                Cancel
              </button>
              <button
                onClick={handleSubmit}
                disabled={submitting}
                className="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50"
              >
                {submitting ? 'Submitting...' : 'Yes, Submit'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};