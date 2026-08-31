import { useState } from 'react';
import { useAuth } from '../contexts/AuthContext';
import { LogOut, Settings, BookOpen, Users, FileText, Key } from 'lucide-react';
import { SchoolSettings } from './admin/SchoolSettings';
import { ClassManagement } from './admin/ClassManagement';
import { SubjectManagement } from './admin/SubjectManagement';
import { TeacherAllocation } from './admin/TeacherAllocation';
import { ExamManagement } from './admin/ExamManagement';
import { PasskeyManagement } from './admin/PasskeyManagement';

type Tab = 'school' | 'classes' | 'subjects' | 'teachers' | 'exams' | 'passkeys';

export const AdminDashboard = () => {
  const { profile, signOut } = useAuth();
  const [activeTab, setActiveTab] = useState<Tab>('school');

  const handleSignOut = async () => {
    try {
      await signOut();
    } catch (error) {
      console.error('Error signing out:', error);
    }
  };

  const tabs = [
    { id: 'school' as Tab, label: 'School Settings', icon: Settings },
    { id: 'classes' as Tab, label: 'Classes', icon: BookOpen },
    { id: 'subjects' as Tab, label: 'Subjects', icon: FileText },
    { id: 'teachers' as Tab, label: 'Teacher Allocation', icon: Users },
    { id: 'exams' as Tab, label: 'Exam Management', icon: FileText },
    { id: 'passkeys' as Tab, label: 'Passkeys', icon: Key },
  ];

  return (
    <div className="min-h-screen bg-gray-50">
      <header className="bg-white shadow-sm border-b border-gray-200">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex justify-between items-center h-16">
            <div>
              <h1 className="text-2xl font-bold text-gray-900">Admin Dashboard</h1>
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
        <div className="flex gap-6">
          <nav className="w-64 space-y-1">
            {tabs.map((tab) => {
              const Icon = tab.icon;
              return (
                <button
                  key={tab.id}
                  onClick={() => setActiveTab(tab.id)}
                  className={`w-full flex items-center gap-3 px-4 py-3 rounded-lg font-medium transition-colors ${
                    activeTab === tab.id
                      ? 'bg-blue-600 text-white'
                      : 'text-gray-700 hover:bg-gray-100'
                  }`}
                >
                  <Icon className="w-5 h-5" />
                  {tab.label}
                </button>
              );
            })}
          </nav>

          <div className="flex-1">
            <div className="bg-white rounded-xl shadow-sm p-6">
              {activeTab === 'school' && <SchoolSettings />}
              {activeTab === 'classes' && <ClassManagement />}
              {activeTab === 'subjects' && <SubjectManagement />}
              {activeTab === 'teachers' && <TeacherAllocation />}
              {activeTab === 'exams' && <ExamManagement />}
              {activeTab === 'passkeys' && <PasskeyManagement />}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};
