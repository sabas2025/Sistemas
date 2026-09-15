<?php
/**
 * Catálogo de empresas — fonte única para listar e validar `empresas` fora da camada de dados.
 *
 * Nasceu em 2026-09-15, junto do campo *Empresa* na tela de usuários. Vive aqui, e não no
 * DashboardController, por uma razão medida: `tests/enterprise/v104_48_1_architecture_test.php`
 * trava aquele controller abaixo de 160 KB, e ele estava a **378 bytes** do teto. A guarda existe
 * para impedir que o controller volte a inchar — então lógica nova de domínio entra por serviço.
 *
 * Complementa, não substitui: quem decide o que cada empresa enxerga é o `TenantScopeService`;
 * quem diz qual empresa está ativa na sessão é o `TenantContextService`. Este aqui só responde
 * "quais empresas existem" e "este id é de uma empresa de verdade".
 */
class EmpresaCatalogService {
  /**
   * Empresas cadastradas, ordenadas por nome. Devolve lista vazia quando a tabela não existe —
   * mesmo desenho do LicenseEnforcementService, para que instalação antiga não quebre a tela.
   *
   * @return list<array{id:int,nome:string,ativo:int}>
   */
  public static function listar(): array {
    if (!Database::tableExists('empresas')) return [];
    $linhas = Database::forTable('empresas')->query('SELECT id,nome,ativo FROM empresas ORDER BY nome')->fetchAll();
    return array_map(static fn(array $l): array => [
      'id' => (int)$l['id'],
      'nome' => (string)$l['nome'],
      'ativo' => (int)($l['ativo'] ?? 0),
    ], $linhas ?: []);
  }

  /**
   * Lê e valida o `empresa_id` de um formulário, numa chamada só — para que o controller que a usa
   * fique em duas linhas (ver a nota de tamanho no topo desta classe).
   *
   * Devolve `null` para campo vazio ("sem empresa": o usuário enxerga os dados de todas, que é o
   * comportamento anterior à migration 20260915_014), o inteiro quando a empresa existe, e `false`
   * quando veio um id que não corresponde a nenhuma empresa — cabe a quem chama recusar o envio.
   *
   * @param mixed $bruto valor cru vindo de $_POST
   */
  public static function idDoFormulario($bruto): int|false|null {
    $texto = trim((string)(is_scalar($bruto) ? $bruto : ''));
    if ($texto === '') return null;
    $id = (int)$texto;
    return self::existe($id) ? $id : false;
  }

  /**
   * Confere que o id é de uma empresa que existe.
   *
   * Nunca grave um `empresa_id` vindo de formulário sem passar por aqui: apontar um usuário para
   * uma empresa inexistente faria o isolamento esconder tudo dele, sem mensagem que explicasse.
   */
  public static function existe(int $id): bool {
    if ($id < 1 || !Database::tableExists('empresas')) return false;
    $st = Database::forTable('empresas')->prepare('SELECT id FROM empresas WHERE id=? LIMIT 1');
    $st->execute([$id]);
    return (bool)$st->fetch();
  }
}
