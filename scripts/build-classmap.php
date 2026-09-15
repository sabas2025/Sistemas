<?php
declare(strict_types=1);
/**
 * Melhoria 5 da seção 8 (relatório V104.49.3-R6): gera storage/cache/classmap.php a partir da
 * árvore real. Antes o mapa era mantido à mão e nada no pacote o produzia, então ele ficava
 * defasado em silêncio a cada arquivo novo.
 *
 * Uso:
 *   php scripts/build-classmap.php           grava o mapa
 *   php scripts/build-classmap.php --check   não grava; sai 1 se o mapa estiver defasado (CI)
 */
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "[FALHA] Somente CLI.\n"); exit(1); }
require_once dirname(__DIR__).'/app/Services/ClassmapBuilderService.php';

$check = in_array('--check', $argv, true);
if ($check) {
    $r = ClassmapBuilderService::verify();
    if ($r['ok']) { echo "[OK] classmap.php em dia: {$r['total']} classes.\n"; exit(0); }
    fwrite(STDERR, "[FALHA] storage/cache/classmap.php está defasado.\n");
    if ($r['faltando'])    fwrite(STDERR, "        Ausentes no mapa: ".implode(', ', $r['faltando'])."\n");
    if ($r['sobrando'])    fwrite(STDERR, "        No mapa mas sem arquivo: ".implode(', ', $r['sobrando'])."\n");
    if ($r['divergentes']) fwrite(STDERR, "        Caminho divergente: ".implode(', ', $r['divergentes'])."\n");
    fwrite(STDERR, "        Rode: php scripts/build-classmap.php\n");
    exit(1);
}

$total = ClassmapBuilderService::write();
if ($total === null) { fwrite(STDERR, "[FALHA] Não foi possível gravar storage/cache/classmap.php.\n"); exit(1); }
echo "[OK] classmap.php gerado com {$total} classes.\n";
