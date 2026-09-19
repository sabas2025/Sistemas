import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const modules = ['core', 'pedidos', 'produtos', 'estoque', 'fiscal', 'fila', 'observabilidade', 'backups'];
const versionSource = readFileSync(resolve(root, 'app/Services/SystemVersionService.php'), 'utf8');
const release = Object.fromEntries(['VERSION','RELEASE','BUILD','RELEASE_DATE'].map(k => {
  const match = versionSource.match(new RegExp(`public const ${k} = '([^']+)';`));
  if (!match) throw new Error(`Metadado de release ausente: ${k}`);
  return [k, match[1]];
}));
const header = [
  `-- Hub Tiny VSM - instalação consolidada ${release.VERSION}-${release.RELEASE}+${release.BUILD} (${release.RELEASE_DATE})`,
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
  const tables = [...output.matchAll(/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?([a-zA-Z0-9_]+)`?/gi)].map(m=>m[1].toLowerCase());
  const unique = [...new Set(tables)].sort();
  const inventory = {version:`${release.VERSION}-${release.RELEASE}+${release.BUILD}`,total_tables:tables.length,unique_tables:unique.length,duplicates:tables.filter((t,i)=>tables.indexOf(t)!==i),tables:unique};
  writeFileSync(resolve(root,'database/schema_inventory_current.json'),JSON.stringify(inventory,null,2)+'\n');
  writeFileSync(resolve(root,'VERSAO.txt'),inventory.version+'\n');
  process.stdout.write(`[OK] Schemas consolidados regenerados com ${modules.length} módulos.\n`);
}
