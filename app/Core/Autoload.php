<?php
/**
 * Autoload otimizado V104.16 com suporte a conectores plugáveis.
 * Primeiro tenta classmap gerado em storage/cache/classmap.php.
 * Se não existir, mantém fallback seguro por pastas.
 */
spl_autoload_register(function($class){
  $class = ltrim((string)$class, '\\');
  if ($class === '') return;

  static $classMap = null;
  $root = dirname(__DIR__, 2);
  if ($classMap === null) {
    $mapFile = $root . '/storage/cache/classmap.php';
    $classMap = is_file($mapFile) ? (include $mapFile) : [];
    if (!is_array($classMap)) $classMap = [];
  }

  if (isset($classMap[$class])) {
    $file = $root . '/' . ltrim((string)$classMap[$class], '/');
    if (is_file($file)) { require_once $file; return; }
  }

  static $negative = [];
  if (isset($negative[$class])) return;

  $paths = [
    __DIR__.'/../Core/',
    __DIR__.'/../Controllers/',
    __DIR__.'/../Services/',
    __DIR__.'/../Connectors/',
    __DIR__.'/../Models/',
    __DIR__.'/../Legacy/Controllers/',
    __DIR__.'/../Legacy/Services/',
  ];
  foreach ($paths as $p) {
    $f = $p.$class.'.php';
    if (is_file($f)) { require_once $f; return; }
  }
  $negative[$class] = true;
});
