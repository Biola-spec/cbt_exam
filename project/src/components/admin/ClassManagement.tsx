import { useState, useEffect } from 'react';
import { supabase, Class } from '../../lib/supabase';
import { Plus, Trash2, Search } from 'lucide-react';

export const ClassManagement = () => {
  const [classes, setClasses] = useState<Class[]>([]);
  const [filteredClasses, setFilteredClasses] = useState<Class[]>([]);
  const [newClassName, setNewClassName] = useState('');
  const [newClassDescription, setNewClassDescription] = useState('');
  const [searchTerm, setSearchTerm] = useState('');
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState('');

  useEffect(() => {
    loadClasses();
  }, []);

  useEffect(() => {
    // Filter classes based on search term
    if (searchTerm.trim() === '') {
      setFilteredClasses(classes);
    } else {
      const filtered = classes.filter(cls => 
        cls.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        (cls.description && cls.description.toLowerCase().includes(searchTerm.toLowerCase()))
      );
      setFilteredClasses(filtered);
    }
  }, [searchTerm, classes]);

  const loadClasses = async () => {
    try {
      const { data, error } = await supabase
        .from('classes')
        .select('*')
        .order('name');

      if (error) throw error;
      setClasses(data || []);
      setFilteredClasses(data || []);
    } catch (error) {
      console.error('Error loading classes:', error);
    }
  };

  const handleAddClass = async () => {
    if (!newClassName.trim()) return;

    setLoading(true);
    setMessage('');

    try {
      // Check if class already exists
      const { data: existing } = await supabase
        .from('classes')
        .select('*')
        .eq('name', newClassName.trim())
        .maybeSingle();

      if (existing) {
        setMessage('A class with this name already exists');
        setLoading(false);
        return;
      }

      const { error } = await supabase
        .from('classes')
        .insert({
          name: newClassName.trim(),
          description: newClassDescription.trim() || null,
        });

      if (error) throw error;

      setNewClassName('');
      setNewClassDescription('');
      setMessage('Class added successfully');
      loadClasses();
    } catch (error: any) {
      setMessage('Error adding class: ' + error.message);
    } finally {
      setLoading(false);
    }
  };

  const handleDeleteClass = async (id: string) => {
    if (!confirm('Are you sure you want to delete this class? This will also remove all associated allocations and exam subjects.')) return;

    try {
      const { error } = await supabase
        .from('classes')
        .delete()
        .eq('id', id);

      if (error) throw error;
      loadClasses();
    } catch (error: any) {
      alert('Error deleting class: ' + error.message);
    }
  };

  return (
    <div>
      <h2 className="text-2xl font-bold text-gray-900 mb-6">Class Management</h2>

      <div className="mb-8 p-4 bg-gray-50 rounded-lg">
        <h3 className="font-semibold text-gray-900 mb-4">Add New Class</h3>
        <div className="grid gap-4 mb-4">
          <input
            type="text"
            value={newClassName}
            onChange={(e) => setNewClassName(e.target.value)}
            placeholder="Class name (e.g., JSS 1, SS 2)"
            className="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          />
          <input
            type="text"
            value={newClassDescription}
            onChange={(e) => setNewClassDescription(e.target.value)}
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
          onClick={handleAddClass}
          disabled={loading || !newClassName.trim()}
          className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <Plus className="w-4 h-4" />
          Add Class
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
            placeholder="Search classes..."
            className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          />
        </div>
      </div>

      <div>
        <h3 className="font-semibold text-gray-900 mb-4">
          Existing Classes ({filteredClasses.length})
        </h3>
        {filteredClasses.length === 0 ? (
          <p className="text-gray-500 text-center py-8">
            {searchTerm ? 'No classes match your search' : 'No classes added yet'}
          </p>
        ) : (
          <div className="space-y-2">
            {filteredClasses.map((cls) => (
              <div
                key={cls.id}
                className="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50"
              >
                <div>
                  <h4 className="font-medium text-gray-900">{cls.name}</h4>
                  {cls.description && (
                    <p className="text-sm text-gray-600">{cls.description}</p>
                  )}
                </div>
                <button
                  onClick={() => handleDeleteClass(cls.id)}
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