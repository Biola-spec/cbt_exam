import { createClient } from '@supabase/supabase-js';

const supabaseUrl = 'https://usqjcjsfwcaimoolnrjc.supabase.co';
const supabaseAnonKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InVzcWpjanNmd2NhaW1vb2xucmpjIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjEwNDcxMTksImV4cCI6MjA3NjYyMzExOX0.zwrhB1UOI98_nMDU88aoAXTfTmYVRAEVhElLnWGDQbE';

const supabase = createClient(supabaseUrl, supabaseAnonKey);

async function createAdmin() {
  try {
    const email = 'admin@cbtexam.com';
    const password = 'Admin@2024';
    const fullName = 'System Administrator';

    console.log('Creating admin account...');
    console.log('Email:', email);
    console.log('Password:', password);

    const { data, error } = await supabase.auth.signUp({
      email,
      password,
    });

    if (error) {
      if (error.message.includes('already registered') || error.message.includes('User already registered')) {
        console.log('\n✓ Admin account already exists!');
        console.log('\n=== LOGIN CREDENTIALS ===');
        console.log('Email:', email);
        console.log('Password:', password);
        console.log('========================\n');
        return;
      }
      throw error;
    }

    if (data.user) {
      const { error: profileError } = await supabase
        .from('profiles')
        .insert({
          id: data.user.id,
          email,
          full_name: fullName,
          role: 'admin',
        });

      if (profileError) {
        console.log('Profile might already exist, checking...');
      }

      console.log('\n✓ Admin account created successfully!');
      console.log('\n=== LOGIN CREDENTIALS ===');
      console.log('Email:', email);
      console.log('Password:', password);
      console.log('========================\n');
      console.log('You can now login to the system using these credentials.');
    }
  } catch (error) {
    console.error('Error:', error.message);
  }

  process.exit(0);
}

createAdmin();
