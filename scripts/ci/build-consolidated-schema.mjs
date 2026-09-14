import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const modules = ['core', 'pedidos', 'produtos', 'estoque', 'fiscal', 'fila', 'observabilidade', 'backups'];
const header = [
  '-- Hub Tiny VSM - instalação consolidada V104.49.3-R5 (2026-08-22)',
  '-- Gerado exclusivamente de database/modules/*.sql; não edite manualmente.',
  '-- Reaplicação não sobrescreve usuários, credenciais nem configurações operacionais.',
  '',
  'CREATE DATABASE IF NOT EXISTS `{DB_NAME}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;',
  'USE `{DB_NAME}`;',
  '',
].join('\n');

let output = header;
for (const moduleName of modules) {
  const source = readFileSync(resolve(root, `database/modules/${moduleName}.sql`), 'utf8').trim();
  output += `\n-- ===== MÓDULO ${moduleName.toUpperCase()} =====\n${source}\n`;
}

const targets = ['database/install_final_current.sql', 'database/install.sql'];
if (process.argv.includes('--check')) {
  const divergent = targets.filter((target) => readFileSync(resolve(root, target), 'utf8') !== output);
  if (divergent.length) {
    process.stderr.write(`[FALHA] Schemas consolidados não foram regenerados: ${divergent.join(', ')}\n`);
    process.exit(1);
  }
  process.stdout.write('[OK] install.sql e install_final_current.sql correspondem exatamente aos oito módulos.\n');
} else {
  for (const target of targets) writeFileSync(resolve(root, target), output, 'utf8');
  process.stdout.write(`[OK] Schemas consolidados regenerados com ${modules.length} módulos.\n`);
}
