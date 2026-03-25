import { createClient } from '@supabase/supabase-js';

const supabase = createClient(
  process.env.SUPABASE_URL,
  process.env.SUPABASE_KEY
);

export const tools = {
  get_tables: async () => {
    const { data, error } = await supabase
      .from('information_schema.tables')
      .select('*');

    if (error) throw new Error(error.message);
    return data;
  },

  get_users: async () => {
    const { data, error } = await supabase
      .from('users')
      .select('*');

    if (error) throw new Error(error.message);
    return data;
  }
};