<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks = [];

/**
 * Coluna estoque_movimentos.retorno_tiny — achado D-02 (auditoria de vídeo-demo, 2026-10-05).
 *
 * workers/worker_estoque.php (ramo destino='tiny', fluxo principal VSM -> Tiny) grava
 * `UPDATE estoque_movimentos SET status=?, retorno_tiny=? WHERE trace_id=? AND sku=?`. A coluna
 * `retorno_tiny` não existia na tabela (só `retorno_vsm`), então o UPDATE lançava "Unknown column";
 * o try/catch do worker rebaixava a "falha do item", o movimento nunca virava 'sucesso' nesse fluxo
 * e o item era re-tentado (risco de reescrever o saldo no Tiny a cada retry).
 *
 * Esta checagem ancora o SCHEMA na verdade do ESCRITOR: se o worker grava `retorno_tiny` em
 * `estoque_movimentos`, a coluna precisa existir nos DOIS caminhos (módulo + consolidado) e uma
 * migration precisa adicioná-la (lição I-18: paridade instalação nova × atualização).
 *
 * O QUE ELA NÃO PROVA: que o UPDATE roda sem erro em runtime (medido com MariaDB no job E2E) — só
 * que a coluna que o worker escreve existe no schema entregue pelos dois caminhos.
 */
$worker = hub_read('workers/worker_estoque.php');
$modulo = hub_read('database/modules/estoque.sql');
$consol = hub_read('database/install_final_current.sql');

// Lição I-20: prove que leu antes de afirmar.
hub_check($checks, 'worker_estoque.php lido (não vazio)', $worker !== '');
hub_check($checks, 'database/modules/estoque.sql lido (não vazio)', $modulo !== '');
hub_check($checks, 'database/install_final_current.sql lido (não vazio)', $consol !== '');

// (fonte da verdade) O worker grava retorno_tiny em estoque_movimentos.
hub_check($checks, 'worker_estoque grava retorno_tiny no UPDATE de estoque_movimentos',
    $worker !== '' && str_contains($worker, 'estoque_movimentos SET status=?, retorno_tiny=?'));

// Extrai o bloco CREATE TABLE estoque_movimentos (...) de um .sql e devolve só ele.
$blocoEstoqueMovimentos = static function (string $sql): string {
    $pos = stripos($sql, 'estoque_movimentos (');
    if ($pos === false) $pos = stripos($sql, "estoque_movimentos\n");
    if ($pos === false) return '';
    $fim = stripos($sql, 'ENGINE=', $pos);
    return $fim === false ? '' : substr($sql, $pos, $fim - $pos);
};

$blocoMod = $blocoEstoqueMovimentos($modulo);
$blocoCon = $blocoEstoqueMovimentos($consol);
hub_check($checks, 'bloco estoque_movimentos localizado no módulo', $blocoMod !== '');
hub_check($checks, 'bloco estoque_movimentos localizado no consolidado', $blocoCon !== '');

// A coluna existe nos DOIS caminhos de instalação nova.
hub_check($checks, 'módulo estoque.sql: estoque_movimentos declara retorno_tiny',
    $blocoMod !== '' && str_contains($blocoMod, 'retorno_tiny'));
hub_check($checks, 'consolidado: estoque_movimentos declara retorno_tiny',
    $blocoCon !== '' && str_contains($blocoCon, 'retorno_tiny'));

// E uma migration adiciona a coluna (caminho de atualização).
$temMigration = false;
foreach (glob(hub_root().'/database/migrations/*.sql') ?: [] as $m) {
    $sql = (string)@file_get_contents($m);
    if (str_contains($sql, 'estoque_movimentos') && str_contains($sql, 'retorno_tiny') && stripos($sql, 'ADD COLUMN') !== false) {
        $temMigration = true; break;
    }
}
hub_check($checks, 'existe migration que adiciona estoque_movimentos.retorno_tiny', $temMigration);

hub_finish($checks);
