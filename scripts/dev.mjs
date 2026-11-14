/**
 * Development runner — starts the API and the Vite dev server together.
 * Vite proxies /api to the API, so the browser only ever talks to one origin.
 */
import { spawn } from 'node:child_process';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = dirname(fileURLToPath(new URL('.', import.meta.url)));
const apiPort = process.env.PORT || '3000';

const children = [];

function start(name, command, args, cwd) {
  const child = spawn(command, args, {
    cwd,
    env: { ...process.env, PORT: apiPort, FORCE_COLOR: '1' },
    stdio: ['ignore', 'pipe', 'pipe'],
    shell: process.platform === 'win32',
  });

  const prefix = `[${name}]`;
  const pipe = (stream, target) => {
    stream.setEncoding('utf8');
    let buffer = '';
    stream.on('data', (chunk) => {
      buffer += chunk;
      const lines = buffer.split('\n');
      buffer = lines.pop() ?? '';
      for (const line of lines) target.write(`${prefix} ${line}\n`);
    });
  };

  pipe(child.stdout, process.stdout);
  pipe(child.stderr, process.stderr);

  child.on('exit', (code) => {
    if (code !== 0 && code !== null) console.log(`${prefix} exited with code ${code}`);
  });

  children.push(child);
  return child;
}

start('api', process.execPath, [join(root, 'server', 'src', 'index.js')], root);
start('web', 'npm', ['run', 'dev', '--prefix', 'client'], root);

const shutdown = () => {
  for (const child of children) child.kill('SIGTERM');
  process.exit(0);
};

process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);
