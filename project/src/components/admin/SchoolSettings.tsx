import { useState, useEffect } from 'react';
import { supabase } from '../../lib/supabase';
import { Save } from 'lucide-react';

export const SchoolSettings = () => {
  const [schoolName, setSchoolName] = useState('');
  const [defaultTimeLimit, setDefaultTimeLimit] = useState(60); // Default to 60 minutes
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState('');

  useEffect(() => {
    loadSchoolConfig();
  }, []);

  const loadSchoolConfig = async () => {
    try {
      const { data } = await supabase
        .from('school_config')
        .select('*')
        .maybeSingle();

      if (data) {
        setSchoolName(data.school_name);
        // Load default time limit if stored in school config
        if (data.default_time_limit) {
          setDefaultTimeLimit(data.default_time_limit);
        }
      }
    } catch (error) {
      console.error('Error loading school config:', error);
    }
  };

  const handleSave = async () => {
    setLoading(true);
    setMessage('');

    try {
      const { data: existing } = await supabase
        .from('school_config')
        .select('*')
        .maybeSingle();

      if (existing) {
        await supabase
          .from('school_config')
          .update({ 
            school_name: schoolName, 
            updated_at: new Date().toISOString(),
            default_time_limit: defaultTimeLimit
          })
          .eq('id', existing.id);
      } else {
        await supabase
          .from('school_config')
          .insert({ 
            school_name: schoolName,
            default_time_limit: defaultTimeLimit
          });
      }

      setMessage('School settings saved successfully');
    } catch (error: any) {
      setMessage('Error saving school settings: ' + error.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div>
      <h2 className="text-2xl font-bold text-gray-900 mb-6">School Settings</h2>

      <div className="max-w-xl">
        <div className="mb-6">
          <label className="block text-sm font-medium text-gray-700 mb-2">
            School Name
          </label>
          <input
            type="text"
            value={schoolName}
            onChange={(e) => setSchoolName(e.target.value)}
            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            placeholder="Enter school name"
          />
        </div>

        <div className="mb-6">
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Default Exam Time Limit (minutes)
          </label>
          <input
            type="number"
            value={defaultTimeLimit}
            onChange={(e) => setDefaultTimeLimit(parseInt(e.target.value) || 60)}
            min="1"
            max="300"
            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          />
          <p className="text-xs text-gray-500 mt-1">This will be the default time limit for new exam subjects (1-300 minutes)</p>
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
          onClick={handleSave}
          disabled={loading || !schoolName}
          className="flex items-center gap-2 px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <Save className="w-4 h-4" />
          {loading ? 'Saving...' : 'Save Settings'}
        </button>
      </div>
    </div>
  );
};