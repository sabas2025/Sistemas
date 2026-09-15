<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
$checks=[];

/**
 * Melhoria 1 da seção 8 (relatório V104.49.3-R6): isolamento multiempresa.
 *
 * A reescrita de consulta é o ponto mais perigoso desta entrega: ela altera o SQL de ~160 pontos
 * de chamada. Um erro de POSIÇÃO de parâmetro não quebra a sintaxe — produz consulta que executa
 * e devolve o resultado errado, que é a pior falha possível numa camada de isolamento. Por isso
 * cada transformação é exercitada aqui, incluindo os casos que costumam quebrar implementações
 * ingênuas: LIMIT com placeholder, ON DUPLICATE KEY UPDATE, palavra reservada dentro de string,
 * consulta sem WHERE e INSERT ... SELECT.
 */

// Contexto de empresa controlado pelo teste, sem sessão nem banco.
class TenantContextService {
    public static ?int $empresa = null;
    public static function currentEmpresaId(): ?int { return self::$empresa; }
}
require_once hub_root().'/app/Services/TenantScopeService.php';

$comEmpresa = static function (int $id, callable $fn) {
    TenantContextService::$empresa = $id;
    try { return $fn(); } finally { TenantContextService::$empresa = null; }
};

// ---------------------------------------------------------------- catálogo
// 32 desde o achado C-05 da auditoria de capacidade, que trouxe logs_integracao para o escopo:
// com 100 clientes, os logs de integração de todos ficavam no mesmo balde sem filtro.
hub_check($checks,'Catálogo cobre as 32 tabelas operacionais', count(TenantScopeService::scopedTables()) === 32);
hub_check($checks,'logs_integracao está no escopo (C-05)', TenantScopeService::isScoped('logs_integracao'));
hub_check($checks,'Trilha forense da instalação segue GLOBAL de propósito',
    !TenantScopeService::isScoped('auditoria_eventos') && !TenantScopeService::isScoped('security_events'));
hub_check($checks,'Tabela operacional está no catálogo', TenantScopeService::isScoped('fila_integracao') && TenantScopeService::isScoped('notas_fiscais'));
hub_check($checks,'Tabela global NÃO está no catálogo',
    !TenantScopeService::isScoped('usuarios') && !TenantScopeService::isScoped('security_events')
    && !TenantScopeService::isScoped('configuracoes_integracao') && !TenantScopeService::isScoped('empresas'));
hub_check($checks,'Nome com crase/espaço é normalizado', TenantScopeService::isScoped('`fila_integracao`'));

// ------------------------------------------- sem contexto: nada muda (instalação de empresa única)
[$sql,$p] = TenantScopeService::applyToSelect('fila_integracao','SELECT * FROM fila_integracao WHERE status=?',['pendente']);
hub_check($checks,'Sem empresa no contexto o SELECT fica intacto', $sql === 'SELECT * FROM fila_integracao WHERE status=?' && $p === ['pendente']);
[$sql,$p] = TenantScopeService::applyToInsert('fila_integracao','INSERT INTO fila_integracao(tipo,referencia) VALUES(?,?)',['a','b']);
hub_check($checks,'Sem empresa no contexto o INSERT fica intacto', $sql === 'INSERT INTO fila_integracao(tipo,referencia) VALUES(?,?)' && $p === ['a','b']);

// ---------------------------------------------------------------- SELECT
$comEmpresa(7, static function () use (&$checks) {
    [$sql,$p] = TenantScopeService::applyToSelect('fila_integracao','SELECT * FROM fila_integracao WHERE status=?',['pendente']);
    hub_check($checks,'SELECT com WHERE recebe o predicado e o parâmetro no fim',
        $sql === 'SELECT * FROM fila_integracao WHERE status=? AND (empresa_id = ? OR empresa_id IS NULL)' && $p === ['pendente',7]);

    [$sql,$p] = TenantScopeService::applyToSelect('fila_integracao','SELECT * FROM fila_integracao',[]);
    hub_check($checks,'SELECT sem WHERE ganha um WHERE',
        $sql === 'SELECT * FROM fila_integracao WHERE (empresa_id = ? OR empresa_id IS NULL)' && $p === [7]);

    // O caso que quebra implementação ingênua: placeholder DEPOIS do ponto de inserção.
    [$sql,$p] = TenantScopeService::applyToSelect('fila_integracao','SELECT * FROM fila_integracao WHERE status=? ORDER BY id DESC LIMIT ?',['erro',50]);
    hub_check($checks,'Predicado entra ANTES de ORDER BY/LIMIT',
        $sql === 'SELECT * FROM fila_integracao WHERE status=? AND (empresa_id = ? OR empresa_id IS NULL) ORDER BY id DESC LIMIT ?');
    hub_check($checks,'Parâmetro é inserido na POSIÇÃO certa, não no fim (LIMIT ? continua por último)',
        $p === ['erro',7,50]);

    [$sql,$p] = TenantScopeService::applyToSelect('fila_integracao','SELECT status, COUNT(*) total FROM fila_integracao GROUP BY status',[]);
    hub_check($checks,'Predicado entra antes de GROUP BY',
        $sql === 'SELECT status, COUNT(*) total FROM fila_integracao WHERE (empresa_id = ? OR empresa_id IS NULL) GROUP BY status' && $p === [7]);

    // Palavra reservada dentro de literal não pode ser confundida com cláusula.
    [$sql,$p] = TenantScopeService::applyToSelect('fila_integracao',"SELECT * FROM fila_integracao WHERE tipo='order by teste' AND status=?",['ok']);
    hub_check($checks,'"order by" dentro de string não é tratado como cláusula',
        str_ends_with($sql, "AND status=? AND (empresa_id = ? OR empresa_id IS NULL)") && $p === ['ok',7]);

    [$sql,$p] = TenantScopeService::applyToSelect('fila_integracao','UPDATE fila_integracao SET status=? WHERE id=?',['ok',9]);
    hub_check($checks,'UPDATE também recebe o predicado',
        $sql === 'UPDATE fila_integracao SET status=? WHERE id=? AND (empresa_id = ? OR empresa_id IS NULL)' && $p === ['ok',9,7]);

    [$sql,$p] = TenantScopeService::applyToSelect('pedidos_integracao','SELECT p.* FROM pedidos_integracao p WHERE p.status=?',['novo'],'p');
    hub_check($checks,'Alias é respeitado no predicado',
        $sql === 'SELECT p.* FROM pedidos_integracao p WHERE p.status=? AND (p.empresa_id = ? OR p.empresa_id IS NULL)' && $p === ['novo',7]);

    hub_check($checks,'Tabela fora do catálogo não é reescrita',
        TenantScopeService::applyToSelect('usuarios','SELECT * FROM usuarios WHERE id=?',[1])[0] === 'SELECT * FROM usuarios WHERE id=?');

    // ------------------------------------------------------------ INSERT
    [$sql,$p] = TenantScopeService::applyToInsert('fila_integracao','INSERT INTO fila_integracao(tipo,referencia,payload) VALUES(?,?,?)',['t','r','p']);
    hub_check($checks,'INSERT ganha coluna e placeholder',
        $sql === 'INSERT INTO fila_integracao(tipo,referencia,payload,empresa_id) VALUES(?,?,?,?)' && $p === ['t','r','p',7]);

    // O outro caso que quebra implementação ingênua: placeholders depois do VALUES.
    [$sql,$p] = TenantScopeService::applyToInsert('estoque_saldos_cache',
        'INSERT INTO estoque_saldos_cache(sku,saldo) VALUES(?,?) ON DUPLICATE KEY UPDATE saldo=?',['SKU1',10,10]);
    hub_check($checks,'INSERT ... ON DUPLICATE KEY: coluna entra em VALUES, não no UPDATE',
        $sql === 'INSERT INTO estoque_saldos_cache(sku,saldo,empresa_id) VALUES(?,?,?) ON DUPLICATE KEY UPDATE saldo=?');
    hub_check($checks,'INSERT ... ON DUPLICATE KEY: parâmetro entra ANTES do parâmetro do UPDATE',
        $p === ['SKU1',10,7,10]);

    [$sql,$p] = TenantScopeService::applyToInsert('fila_integracao','INSERT IGNORE INTO fila_integracao(tipo) VALUES(?)',['x']);
    hub_check($checks,'INSERT IGNORE é reescrito',
        $sql === 'INSERT IGNORE INTO fila_integracao(tipo,empresa_id) VALUES(?,?)' && $p === ['x',7]);

    $jaTem = 'INSERT INTO fila_integracao(tipo,empresa_id) VALUES(?,?)';
    hub_check($checks,'INSERT que já cita empresa_id não é reescrito duas vezes',
        TenantScopeService::applyToInsert('fila_integracao',$jaTem,['x',3])[0] === $jaTem);

    $insertSelect = 'INSERT INTO fila_morta(tipo,referencia) SELECT tipo,referencia FROM fila_integracao WHERE id=?';
    hub_check($checks,'INSERT ... SELECT não é reescrito às cegas',
        TenantScopeService::applyToInsert('fila_morta',$insertSelect,[1])[0] === $insertSelect);

    // ------------------------------------------------------------ whereStrict / stamp
    $strict = TenantScopeService::whereStrict('fila_integracao');
    hub_check($checks,'whereStrict exclui linhas sem empresa', $strict['sql'] === ' AND empresa_id = ?' && $strict['params'] === [7]);

    hub_check($checks,'stamp carimba a empresa no array de INSERT',
        (TenantScopeService::stamp('fila_integracao',['tipo'=>'x'])['empresa_id'] ?? null) === 7);
    hub_check($checks,'stamp não toca tabela fora do catálogo',
        !array_key_exists('empresa_id', TenantScopeService::stamp('usuarios',['nome'=>'x'])));

    // ------------------------------------------------------------ assertRow
    hub_check($checks,'assertRow aceita linha da própria empresa', TenantScopeService::assertRow('fila_integracao',['id'=>1,'empresa_id'=>7]));
    hub_check($checks,'assertRow aceita linha legada (empresa_id NULL)', TenantScopeService::assertRow('fila_integracao',['id'=>1,'empresa_id'=>null]));
    hub_check($checks,'assertRow RECUSA linha de outra empresa', !TenantScopeService::assertRow('fila_integracao',['id'=>1,'empresa_id'=>99]));
    hub_check($checks,'assertRow ignora linha sem a coluna (consulta de colunas parciais)', TenantScopeService::assertRow('fila_integracao',['id'=>1]));
    hub_check($checks,'assertRow ignora resultado vazio', TenantScopeService::assertRow('fila_integracao', false));
});

// ------------------------------------------------- schema e verificação estática acompanham
$schema = hub_read('database/install_final_current.sql');
$semColuna = [];
foreach (TenantScopeService::scopedTables() as $t) {
    if (!preg_match('/CREATE TABLE(?: IF NOT EXISTS)?\s+`?'.preg_quote($t,'/').'`?\s*\((.*?)\n\)\s*ENGINE/s', $schema, $m) || !preg_match('/\bempresa_id\b/i', $m[1])) {
        $semColuna[] = $t;
    }
}
hub_check($checks,'Toda tabela do catálogo tem empresa_id no schema: '.(implode(', ',$semColuna) ?: 'nenhuma pendente'), $semColuna === []);

$migration = hub_read('database/migrations/20260914_010_tenant_isolation.sql');
hub_check($checks,'Migration de isolamento existe e é idempotente',
    $migration !== '' && str_contains($migration,'information_schema.COLUMNS') && str_contains($migration,'schema_migrations'));
hub_check($checks,'Backfill só roda com UMA empresa cadastrada (não chuta com várias)',
    str_contains($migration,'@empresa_unica') && str_contains($migration,'WHEN @empresas = 1'));
hub_check($checks,'Verificador estático de escopo está presente na CI',
    is_file(hub_root().'/scripts/ci/tenant-scope-check.php')
    && str_contains(hub_read('.github/workflows/hub-ci.yml'),'tenant-scope-check.php'));

hub_finish($checks);
