import fs from 'node:fs/promises';
import path from 'node:path';
import CleanCSS from 'clean-css';
import { minify } from 'terser';

const root = path.resolve(new URL('../..', import.meta.url).pathname);
const assetDir = path.join(root, 'public', 'assets');

// integration-center.css entrou em 2026-09-15: views/layout_top.php:50 carrega o .min.css dela,
// mas a folha estava FORA desta lista. O minificado servido era de 13/07 e podia divergir da
// fonte sem nada acusar — editar o .css não chegava na tela. Conferido que minificar a fonte
// reproduz o .min.css commitado (só normalizações do clean-css: `0%`->`0`, aspas em
// [data-hub-theme=dark], ordem de seletores), então incluí-la não muda o CSS servido.
//
// Ao ACRESCENTAR um arquivo aqui, confira antes que minificar a fonte reproduz o .min commitado;
// se não reproduzir, o .min servido hoje não veio desta ferramenta e mudá-lo é alteração visual.
const cssFiles = ['app.css','responsive-enterprise.css','scroll-enterprise.css','minimalist-enterprise.css','integration-center.css','login.css','bootstrap-login-lite.css','offline.css'];
const jsFiles = ['pwa.js','notifications.js','futuristic-ui.js','minimalist-ui.js','offline.js'];

// --check não escreve nada: minifica em memória e compara com o que está no disco.
//
// Por que este modo existe: a CI rodava `npm run build:pwa`, que REESCREVE os .min no runner.
// Os testes passavam a rodar contra assets recém-construídos enquanto os COMMITADOS podiam estar
// velhos — e foi exatamente assim que integration-center.min.css ficou dois meses defasado sem
// nada acusar. Um passo que conserta em silêncio o que deveria denunciar é indicador que mente.
const apenasConferir = process.argv.includes('--check');

async function minificarCss(file) {
  const input = await fs.readFile(path.join(assetDir, file), 'utf8');
  const result = new CleanCSS({ level: 2 }).minify(input);
  if (result.errors.length) throw new Error(`${file}: ${result.errors.join('; ')}`);
  return { destino: file.replace(/\.css$/, '.min.css'), conteudo: result.styles };
}

async function minificarJs(file) {
  const input = await fs.readFile(path.join(assetDir, file), 'utf8');
  const result = await minify(input, { compress: true, mangle: true, format: { comments: false } });
  if (!result.code) throw new Error(`Falha ao minificar ${file}`);
  return { destino: file.replace(/\.js$/, '.min.js'), conteudo: result.code };
}

const gerados = [
  ...await Promise.all(cssFiles.map(minificarCss)),
  ...await Promise.all(jsFiles.map(minificarJs)),
];

if (!apenasConferir) {
  for (const { destino, conteudo } of gerados) {
    await fs.writeFile(path.join(assetDir, destino), conteudo);
  }
  console.log(`Assets PWA minificados com validação (${gerados.length} arquivos).`);
  process.exit(0);
}

const defasados = [];
for (const { destino, conteudo } of gerados) {
  let atual = null;
  try {
    atual = await fs.readFile(path.join(assetDir, destino), 'utf8');
  } catch {
    defasados.push({ destino, motivo: 'não existe no repositório' });
    continue;
  }
  // Comparação byte a byte, ignorando só quebra de linha final: é o que o navegador recebe.
  if (atual.replace(/\n+$/, '') !== conteudo.replace(/\n+$/, '')) {
    defasados.push({ destino, motivo: `difere da fonte (${atual.length} bytes no repo, ${conteudo.length} gerados)` });
  }
}

if (defasados.length) {
  console.error(`[FALHA] ${defasados.length} asset(s) minificado(s) fora de sincronia com a fonte:`);
  for (const d of defasados) console.error(`  - public/assets/${d.destino}: ${d.motivo}`);
  console.error('\n  A tela carrega o .min, não a fonte — então essas mudanças NÃO estão no ar.');
  console.error('  Corrija com:  npm run build:pwa   (e commite os .min junto com a fonte)');
  process.exit(1);
}

console.log(`Assets minificados em dia com a fonte: ${gerados.length} arquivo(s) conferido(s).`);
