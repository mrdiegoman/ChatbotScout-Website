#!/usr/bin/env node
'use strict';

const mysql = require('mysql2/promise');
const { Server } = require('@modelcontextprotocol/sdk/server/index.js');
const { StdioServerTransport } = require('@modelcontextprotocol/sdk/server/stdio.js');
const { CallToolRequestSchema, ListToolsRequestSchema } = require('@modelcontextprotocol/sdk/types.js');

const dbConfig = {
  host: 'localhost',
  user: 'root',
  password: '',
  database: 'websitedb'
};

const server = new Server(
  { name: 'websitedb-server', version: '1.0.0' },
  { capabilities: { tools: {} } }
);

server.setRequestHandler(ListToolsRequestSchema, async () => ({
  tools: [
    {
      name: 'get_tables',
      description: 'List all tables in the websitedb database',
      inputSchema: { type: 'object', properties: {} }
    },
    {
      name: 'query',
      description: 'Run a SELECT query on the websitedb database',
      inputSchema: {
        type: 'object',
        properties: {
          sql: { type: 'string', description: 'The SELECT query to run' }
        },
        required: ['sql']
      }
    }
  ]
}));

server.setRequestHandler(CallToolRequestSchema, async (request) => {
  const { name, arguments: args } = request.params;

  let db;
  try {
    db = await mysql.createConnection(dbConfig);

    if (name === 'get_tables') {
      const [rows] = await db.execute('SHOW TABLES');
      const tables = rows.map(r => Object.values(r)[0]);
      return { content: [{ type: 'text', text: JSON.stringify(tables, null, 2) }] };
    }

    if (name === 'query') {
      const sql = (args?.sql || '').trim();
      if (!/^SELECT\s/i.test(sql)) {
        return { content: [{ type: 'text', text: 'Error: Only SELECT queries are allowed.' }], isError: true };
      }
      const [rows] = await db.execute(sql);
      return { content: [{ type: 'text', text: JSON.stringify(rows, null, 2) }] };
    }

    throw new Error(`Unknown tool: ${name}`);
  } catch (e) {
    return { content: [{ type: 'text', text: `Error: ${e.message}` }], isError: true };
  } finally {
    if (db) await db.end();
  }
});

async function main() {
  const transport = new StdioServerTransport();
  await server.connect(transport);
}

main().catch(console.error);
