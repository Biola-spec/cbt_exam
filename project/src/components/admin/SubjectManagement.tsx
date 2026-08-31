import { useState, useEffect } from 'react';
import { supabase, Subject } from '../../lib/supabase';
import { Plus, Trash2, Search } from 'lucide-react';

export const SubjectManagement = () => {
  const [subjects, setSubjects] = useState<Subject[]>([]);
  const [filteredSubjects, setFilteredSubjects] = useState<Subject[]>([]);
  const [newSubjectName, setNewSubjectName] = useState('');
  const [newSubjectDescription, setNewSubjectDescription] = useState('');
  const [searchTerm, setSearchTerm] = useState('');
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState('');

  useEffect(() => {
    loadSubjects();
  }, []);

  useEffect(() => {
    // Filter subjects based on search term
    if (searchTerm.trim() === '') {
      setFilteredSubjects(subjects);
    } else {
      const filtered = subjects.filter(subject => 
        subject.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        (subject.description && subject.description.toLowerCase().includes(searchTerm.toLowerCase()))
      );
      setFilteredSubjects(filtered);
    }
  }, [searchTerm, subjects]);

  const loadSubjects = async () => {
    try {
      const { data, error } = await supabase
        .from('subjects')
        .select('*')
        .order('name');

      if (error) throw error;
      setSubjects(data || []);
      setFilteredSubjects(data || []);
    } catch (error) {
      console.error('Error loading subjects:', error);
    }
  };

  const handleAddSubject = async () => {
    if (!newSubjectName.trim()) return;

    setLoading(true);
    setMessage('');

    try {
      // Check if subject already exists
      const { data: existing } = await supabase
        .from('subjects')
        .select('*')
        .eq('name', newSubjectName.trim())
        .maybeSingle();

      if (existing) {
        setMessage('A subject with this name already exists');
        setLoading(false);
        return;
      }

      const { error } = await supabase
        .from('subjects')
        .insert({
          name: newSubjectName.trim(),
          description: newSubjectDescription.trim() || null,
        });

      if (error) throw error;

      setNewSubjectName('');
      setNewSubjectDescription('');
      setMessage('Subject added successfully');
      loadSubjects();
    } catch (error: any) {
      setMessage('Error adding subject: ' + error.message);
    } finally {
      setLoading(false);
    }
  };

  const handleDeleteSubject = async (id: string) => {
    if (!confirm('Are you sure you want to delete this subject? This will also remove all associated allocations and exam subjects.')) return;

    try {
      const { error } = await supabase
        .from('subjects')
        .delete()
        .eq('id', id);

      if (error) throw error;
      loadSubjects();
    } catch (error: any) {
      alert('Error deleting subject: ' + error.message);
    }
  };

  return (
    <div>
      <h2 className="text-2xl font-bold text-gray-900 mb-6">Subject Management</h2>

      <div className="mb-8 p-4 bg-gray-50 rounded-lg">
        <h3 className="font-semibold text-gray-900 mb-4">Add New Subject</h3>
        <div className="grid gap-4 mb-4">
          <input
            type="text"
            value={newSubjectName}
            onChange={(e) => setNewSubjectName(e.target.value)}
            placeholder="Subject name (e.g., Mathematics, English)"
            className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          />
          <input
            type="text"
            value={newSubjectDescription}
            onChange={(e) => setNewSubjectDescription(e.target.value)}
            placeholder="Description (optional)"
            className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          />
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
          onClick={handleAddSubject}
          disabled={loading || !newSubjectName.trim()}
          className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <Plus className="w-4 h-4" />
          Add Subject
        </button>
      </div>

      {/* Search bar */}
      <div className="mb-6">
        <div className="relative">
          <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <Search className="h-5 w-5 text-gray-400" />
          </div>
          <input
            type="text"
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            placeholder="Search subjects..."
            className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          />
        </div>
      </div>

      <div>
        <h3 className="font-semibold text-gray-900 mb-4">
          Existing Subjects ({filteredSubjects.length})
        </h3>
        {filteredSubjects.length === 0 ? (
          <p className="text-gray-500 text-center py-8">
            {searchTerm ? 'No subjects match your search' : 'No subjects added yet'}
          </p>
        ) : (
          <div className="space-y-2">
            {filteredSubjects.map((subject) => (
              <div
                key={subject.id}
                className="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50"
              >
                <div>
                  <h4 className="font-medium text-gray-900">{subject.name}</h4>
                  {subject.description && (
                    <p className="text-sm text-gray-600">{subject.description}</p>
                  )}
                </div>
                <button
                  onClick={() => handleDeleteSubject(subject.id)}
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