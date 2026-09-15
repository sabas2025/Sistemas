<?php
/**
 * Melhoria 5 da seção 8 (relatório V104.49.3-R6): geração do classmap.
 *
 * app/Core/Autoload.php lê storage/cache/classmap.php, mas NADA no pacote gerava esse arquivo -
 * ele era mantido à mão. Na auditoria da R6 ele estava com 209 das 236 classes: as 27 restantes
 * (EnterpriseCoreController, IdempotencyService, IntegrationEventService, ...) caíam no varredor
 * de diretórios do autoloader a cada requisição. Não quebrava - o fallback funciona -, mas o mapa
 * volta a ficar defasado no próximo arquivo novo e ninguém percebe.
 *
 * Este serviço monta o mapa a partir da árvore real, exatamente sobre os diretórios que o
 * autoloader varre, e é chamado pelo instalador, pelo atualizador e pela CI.
 */
class ClassmapBuilderService {
  /** Mesmos diretórios (e mesma ordem) do fallback de app/Core/Autoload.php. */
  private const SCAN_DIRS = [
    'app/Core',
    'app/Controllers',
    'app/Services',
    'app/Connectors',
    'app/Models',
    'app/Legacy/Controllers',
    'app/Legacy/Services',
  ];

  public static function root(): string { return dirname(__DIR__, 2); }
  public static function mapFile(): string { return self::root().'/storage/cache/classmap.php'; }

  /**
   * Varre a árvore e devolve o mapa classe => caminho relativo, ordenado.
   *
   * O autoloader resolve por NOME DE ARQUIVO ($class.'.php'), então o mapa segue o mesmo
   * contrato: o nome do arquivo é a chave. Arquivos que não declarem uma classe/interface/trait/
   * enum com o próprio nome ficam de fora - incluí-los criaria uma entrada que o autoloader
   * carregaria sem definir a classe esperada.
   *
   * @return array<string,string>
   */
  public static function build(): array {
    $root = self::root();
    $map = [];
    foreach (self::SCAN_DIRS as $relDir) {
      $dir = $root.'/'.$relDir;
      if (!is_dir($dir)) continue;
      foreach (glob($dir.'/*.php') ?: [] as $file) {
        $class = basename($file, '.php');
        if ($class === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $class)) continue;
        if (!self::declaresSymbol($file, $class)) continue;
        // Primeiro diretório vence, como no fallback do autoloader.
        if (isset($map[$class])) continue;
        $map[$class] = $relDir.'/'.basename($file);
      }
    }
    ksort($map, SORT_STRING);
    return $map;
  }

  /** O arquivo declara mesmo um símbolo com este nome? Usa tokens, não regex sobre comentários. */
  private static function declaresSymbol(string $file, string $class): bool {
    $code = @file_get_contents($file);
    if ($code === false) return false;
    $tokens = @token_get_all($code);
    if (!is_array($tokens)) return false;
    $wanted = [T_CLASS, T_INTERFACE, T_TRAIT];
    if (defined('T_ENUM')) $wanted[] = T_ENUM;
    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
      $token = $tokens[$i];
      if (!is_array($token) || !in_array($token[0], $wanted, true)) continue;
      // Ignora "::class" (T_CLASS precedido de T_DOUBLE_COLON).
      for ($b = $i - 1; $b >= 0; $b--) {
        if (is_array($tokens[$b]) && $tokens[$b][0] === T_WHITESPACE) continue;
        if (is_array($tokens[$b]) && $tokens[$b][0] === T_DOUBLE_COLON) continue 2;
        break;
      }
      for ($j = $i + 1; $j < $count; $j++) {
        $next = $tokens[$j];
        if (is_array($next) && $next[0] === T_WHITESPACE) continue;
        if (is_array($next) && $next[0] === T_STRING) { if ($next[1] === $class) return true; }
        break;
      }
    }
    return false;
  }

  /** Conteúdo PHP do arquivo de mapa, no mesmo formato que já era versionado. */
  public static function render(array $map): string {
    $out = "<?php\nreturn [\n";
    foreach ($map as $class => $path) {
      $out .= "  '".str_replace("'", "\\'", (string)$class)."' => '".str_replace("'", "\\'", (string)$path)."',\n";
    }
    return $out."];\n";
  }

  /**
   * Regrava o mapa. Devolve o número de classes, ou null se não foi possível escrever
   * (o autoloader tem fallback por diretório, então isto nunca deve derrubar uma instalação).
   */
  public static function write(): ?int {
    $map = self::build();
    $file = self::mapFile();
    $dir = dirname($file);
    if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) return null;
    $tmp = $file.'.tmp'.bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, self::render($map)) === false) { @unlink($tmp); return null; }
    if (!@rename($tmp, $file)) { @unlink($tmp); return null; }
    @chmod($file, 0640);
    if (function_exists('opcache_invalidate')) @opcache_invalidate($file, true);
    return count($map);
  }

  /**
   * Compara o mapa em disco com a árvore real.
   * @return array{ok:bool,faltando:list<string>,sobrando:list<string>,divergentes:list<string>,total:int}
   */
  public static function verify(): array {
    $esperado = self::build();
    $file = self::mapFile();
    $atual = is_file($file) ? (include $file) : [];
    if (!is_array($atual)) $atual = [];
    $faltando = array_values(array_diff(array_keys($esperado), array_keys($atual)));
    $sobrando = array_values(array_diff(array_keys($atual), array_keys($esperado)));
    $divergentes = [];
    foreach ($esperado as $class => $path) {
      if (isset($atual[$class]) && $atual[$class] !== $path) $divergentes[] = $class;
    }
    return [
      'ok' => $faltando === [] && $sobrando === [] && $divergentes === [],
      'faltando' => $faltando, 'sobrando' => $sobrando, 'divergentes' => $divergentes,
      'total' => count($esperado),
    ];
  }
}
