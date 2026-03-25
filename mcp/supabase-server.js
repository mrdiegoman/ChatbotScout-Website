const { createClient } = require('@supabase/supabase-js');

// Cursor MCP runs this file via `node` under your repo's `"type": "commonjs"`.
// So this file must be CommonJS (not ESM `import`/`export`).
const supabase = createClient(process.env.SUPABASE_URL, process.env.SUPABASE_KEY);

const tools = {
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

module.exports = { tools };