import { useState, useEffect } from 'react';
import { supabase, Profile, Class, Subject, TeacherAllocation as Allocation } from '../../lib/supabase';
import { Plus, Trash2, UserPlus, Search } from 'lucide-react';

export const TeacherAllocation = () => {
  const [teachers, setTeachers] = useState<Profile[]>([]);
  const [classes, setClasses] = useState<Class[]>([]);
  const [subjects, setSubjects] = useState<Subject[]>([]);
  const [allocations, setAllocations] = useState<(Allocation & { teacher: Profile; class: Class; subject: Subject })[]>([]);
  const [showCreateTeacher, setShowCreateTeacher] = useState(false);
  const [newTeacherEmail, setNewTeacherEmail] = useState('');
  const [newTeacherName, setNewTeacherName] = useState('');
  const [newTeacherPassword, setNewTeacherPassword] = useState('');
  const [selectedTeacher, setSelectedTeacher] = useState('');
  const [selectedClass, setSelectedClass] = useState('');
  const [selectedSubject, setSelectedSubject] = useState('');
  const [searchTerm, setSearchTerm] = useState('');
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState('');

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    try {
      const [teachersRes, classesRes, subjectsRes, allocationsRes] = await Promise.all([
        supabase.from('profiles').select('*').eq('role', 'teacher').order('full_name'),
        supabase.from('classes').select('*').order('name'),
        supabase.from('subjects').select('*').order('name'),
        supabase.from('teacher_allocations').select(`
          *,
          teacher:profiles!teacher_allocations_teacher_id_fkey(*),
          class:classes(*),
          subject:subjects(*)
        `),
      ]);

      if (teachersRes.data) setTeachers(teachersRes.data);
      if (classesRes.data) setClasses(classesRes.data);
      if (subjectsRes.data) setSubjects(subjectsRes.data);
      if (allocationsRes.data) setAllocations(allocationsRes.data as any);
    } catch (error) {
      console.error('Error loading data:', error);
    }
  };

  const handleCreateTeacher = async () => {
    if (!newTeacherEmail || !newTeacherName || !newTeacherPassword) return;

    setLoading(true);
    setMessage('');

    try {
      const { data, error } = await supabase.auth.signUp({
        email: newTeacherEmail,
        password: newTeacherPassword,
      });

      if (error) throw error;

      if (data.user) {
        const { error: profileError } = await supabase
          .from('profiles')
          .insert({
            id: data.user.id,
            email: newTeacherEmail,
            full_name: newTeacherName,
            role: 'teacher',
          });

        if (profileError) throw profileError;
      }

      setNewTeacherEmail('');
      setNewTeacherName('');
      setNewTeacherPassword('');
      setShowCreateTeacher(false);
      setMessage('Teacher created successfully');
      loadData();
    } catch (error: any) {
      setMessage('Error creating teacher: ' + error.message);
    } finally {
      setLoading(false);
    }
  };

  const handleAllocate = async () => {
    if (!selectedTeacher || !selectedClass || !selectedSubject) return;

    setLoading(true);
    setMessage('');

    try {
      // Check if allocation already exists
      const { data: existing } = await supabase
        .from('teacher_allocations')
        .select('*')
        .eq('teacher_id', selectedTeacher)
        .eq('class_id', selectedClass)
        .eq('subject_id', selectedSubject)
        .maybeSingle();

      if (existing) {
        setMessage('This allocation already exists');
        setLoading(false);
        return;
      }

      const { error } = await supabase
        .from('teacher_allocations')
        .insert({
          teacher_id: selectedTeacher,
          class_id: selectedClass,
          subject_id: selectedSubject,
        });

      if (error) throw error;

      setSelectedTeacher('');
      setSelectedClass('');
      setSelectedSubject('');
      setMessage('Allocation created successfully');
      loadData();
    } catch (error: any) {
      setMessage('Error creating allocation: ' + error.message);
    } finally {
      setLoading(false);
    }
  };

  const handleDeleteAllocation = async (id: string) => {
    if (!confirm('Are you sure you want to remove this allocation?')) return;

    try {
      const { error } = await supabase
        .from('teacher_allocations')
        .delete()
        .eq('id', id);

      if (error) throw error;
      loadData();
    } catch (error: any) {
      alert('Error deleting allocation: ' + error.message);
    }
  };

  // Filter teachers based on search term
  const filteredTeachers = teachers.filter(teacher => 
    teacher.full_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
    teacher.email.toLowerCase().includes(searchTerm.toLowerCase())
  );

  return (
    <div>
      <h2 className="text-2xl font-bold text-gray-900 mb-6">Teacher Allocation</h2>

      <div className="mb-8 p-4 bg-gray-50 rounded-lg">
        <div className="flex justify-between items-center mb-4">
          <h3 className="font-semibold text-gray-900">Create Teacher Account</h3>
          <button
            onClick={() => setShowCreateTeacher(!showCreateTeacher)}
            className="flex items-center gap-2 px-3 py-1.5 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
          >
            <UserPlus className="w-4 h-4" />
            {showCreateTeacher ? 'Cancel' : 'New Teacher'}
          </button>
        </div>

        {showCreateTeacher && (
          <div className="grid gap-4 mb-4">
            <input
              type="text"
              value={newTeacherName}
              onChange={(e) => setNewTeacherName(e.target.value)}
              placeholder="Full name"
              className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
            <input
              type="email"
              value={newTeacherEmail}
              onChange={(e) => setNewTeacherEmail(e.target.value)}
              placeholder="Email"
              className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
            <input
              type="password"
              value={newTeacherPassword}
              onChange={(e) => setNewTeacherPassword(e.target.value)}
              placeholder="Password"
              className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
            <button
              onClick={handleCreateTeacher}
              disabled={loading || !newTeacherEmail || !newTeacherName || !newTeacherPassword}
              className="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Create Teacher Account
            </button>
          </div>
        )}
      </div>

      <div className="mb-8 p-4 bg-gray-50 rounded-lg">
        <h3 className="font-semibold text-gray-900 mb-4">Allocate Teacher to Class & Subject</h3>
        
        {/* Search for teachers */}
        <div className="mb-4">
          <div className="relative">
            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <Search className="h-5 w-5 text-gray-400" />
            </div>
            <input
              type="text"
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              placeholder="Search teachers..."
              className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>
        </div>
        
        <div className="grid gap-4 mb-4">
          <div className="relative">
            <select
              value={selectedTeacher}
              onChange={(e) => setSelectedTeacher(e.target.value)}
              className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent appearance-none"
            >
              <option value="">Select Teacher</option>
              {filteredTeachers.map((teacher) => (
                <option key={teacher.id} value={teacher.id}>
                  {teacher.full_name} ({teacher.email})
                </option>
              ))}
            </select>
            <div className="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none">
              <svg className="h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fillRule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clipRule="evenodd" />
              </svg>
            </div>
          </div>

          <select
            value={selectedClass}
            onChange={(e) => setSelectedClass(e.target.value)}
            className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
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
            className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          >
            <option value="">Select Subject</option>
            {subjects.map((subject) => (
              <option key={subject.id} value={subject.id}>
                {subject.name}
              </option>
            ))}
          </select>
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

        <button
          onClick={handleAllocate}
          disabled={loading || !selectedTeacher || !selectedClass || !selectedSubject}
          className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <Plus className="w-4 h-4" />
          Allocate
        </button>
      </div>

      <div>
        <h3 className="font-semibold text-gray-900 mb-4">Current Allocations</h3>
        {allocations.length === 0 ? (
          <p className="text-gray-500 text-center py-8">No allocations yet</p>
        ) : (
          <div className="space-y-2">
            {allocations.map((allocation) => (
              <div
                key={allocation.id}
                className="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50"
              >
                <div>
                  <h4 className="font-medium text-gray-900">{allocation.teacher.full_name}</h4>
                  <p className="text-sm text-gray-600">
                    {allocation.class.name} - {allocation.subject.name}
                  </p>
                </div>
                <button
                  onClick={() => handleDeleteAllocation(allocation.id)}
                  className="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                >
                  <Trash2 className="w-4 h-4" />
                </button>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
};