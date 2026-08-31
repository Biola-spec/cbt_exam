import { createContext, useContext, useEffect, useState, ReactNode } from 'react';
import { User } from '@supabase/supabase-js';
import { supabase, Profile } from '../lib/supabase';

interface AuthContextType {
  user: User | null;
  profile: Profile | null;
  loading: boolean;
  signIn: (email: string, password: string) => Promise<void>;
  signUp: (email: string, password: string, fullName: string, role: 'admin' | 'teacher' | 'student') => Promise<void>;
  signOut: () => Promise<void>;
  studentSignIn: (email: string, passkey: string) => Promise<void>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within AuthProvider');
  }
  return context;
};

export const AuthProvider = ({ children }: { children: ReactNode }) => {
  const [user, setUser] = useState<User | null>(null);
  const [profile, setProfile] = useState<Profile | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    supabase.auth.getSession().then(({ data: { session } }) => {
      setUser(session?.user ?? null);
      if (session?.user) {
        loadProfile(session.user.id);
      } else {
        setLoading(false);
      }
    });

    const { data: { subscription } } = supabase.auth.onAuthStateChange((_event, session) => {
      (async () => {
        setUser(session?.user ?? null);
        if (session?.user) {
          await loadProfile(session.user.id);
        } else {
          setProfile(null);
          setLoading(false);
        }
      })();
    });

    return () => subscription.unsubscribe();
  }, []);

  const loadProfile = async (userId: string) => {
    try {
      const { data, error } = await supabase
        .from('profiles')
        .select('*')
        .eq('id', userId)
        .maybeSingle();

      if (error) throw error;
      setProfile(data);
    } catch (error) {
      console.error('Error loading profile:', error);
    } finally {
      setLoading(false);
    }
  };

  const signIn = async (email: string, password: string) => {
    const { error } = await supabase.auth.signInWithPassword({
      email,
      password,
    });
    if (error) throw error;
  };

  const signUp = async (email: string, password: string, fullName: string, role: 'admin' | 'teacher' | 'student') => {
    const { data, error } = await supabase.auth.signUp({
      email,
      password,
    });
    if (error) throw error;

    if (data.user) {
      const { error: profileError } = await supabase
        .from('profiles')
        .insert({
          id: data.user.id,
          email,
          full_name: fullName,
          role,
        });
      if (profileError) throw profileError;
    }
  };

  const studentSignIn = async (email: string, passkey: string) => {
    const { data: passkeyData, error: passkeyError } = await supabase
      .from('passkeys')
      .select('*, exams(*)')
      .eq('passkey', passkey)
      .eq('student_email', email)
      .eq('is_used', false)
      .maybeSingle();

    if (passkeyError || !passkeyData) {
      throw new Error('Invalid passkey or email');
    }

    const now = new Date();
    const expiresAt = new Date(passkeyData.expires_at);

    if (expiresAt < now) {
      throw new Error('Passkey has expired');
    }

    const tempPassword = `${passkey}-${email}`;

    let signInError = null;
    const { error: signInAttemptError } = await supabase.auth.signInWithPassword({
      email,
      password: tempPassword,
    });
    signInError = signInAttemptError;

    if (signInError) {
      const { data: signUpData, error: signUpError } = await supabase.auth.signUp({
        email,
        password: tempPassword,
      });

      if (signUpError) throw signUpError;

      if (signUpData.user) {
        const { error: profileError } = await supabase
          .from('profiles')
          .insert({
            id: signUpData.user.id,
            email,
            full_name: email.split('@')[0],
            role: 'student',
          });
        if (profileError) throw profileError;
      }
    }

    await supabase
      .from('passkeys')
      .update({ is_used: true })
      .eq('id', passkeyData.id);
  };

  const signOut = async () => {
    const { error } = await supabase.auth.signOut();
    if (error) throw error;
  };

  return (
    <AuthContext.Provider value={{ user, profile, loading, signIn, signUp, signOut, studentSignIn }}>
      {children}
    </AuthContext.Provider>
  );
};
