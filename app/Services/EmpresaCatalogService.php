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
  /** Memória por requisição da empresa única — `empresaUnicaId()` é chamada em todo INSERT. */
  private static bool $unicaResolvida = false;
  private static ?int $unicaId = null;
  private static bool $resolvendo = false;

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
   * Id da ÚNICA empresa cadastrada, ou `null` quando há zero ou duas ou mais.
   *
   * Existe para responder a uma pergunta que a sessão não consegue: quem grava vindo de webhook,
   * fila, worker ou cron não tem sessão, então `TenantContextService::currentEmpresaId()` devolve
   * `null` e a linha nascia com `empresa_id` NULL — visível a todas as empresas (a metade aberta
   * do achado H-01). Numa instalação de empresa única não há dúvida sobre a quem a linha pertence.
   *
   * A regra é a MESMA das migrations `20260914_010` e `20260915_014`, de propósito:
   * `COUNT(*) = 1` então `MIN(id)`. Com duas ou mais empresas a resposta é `null` e o
   * comportamento anterior é preservado inteiro — atribuir a linha a uma delas seria adivinhar.
   *
   * Nunca lança e nunca registra em banco: é chamada de dentro do caminho de gravação, e uma
   * escrita de log aqui reentraria em `applyToInsert()`. Daí a trava `$resolvendo`.
   */
  public static function empresaUnicaId(): ?int {
    if (self::$unicaResolvida) return self::$unicaId;
    if (self::$resolvendo) return null; // reentrância: responde "não sei" em vez de recursar
    self::$resolvendo = true;
    try {
      if (!class_exists('Database') || !Database::tableExists('empresas')) return self::$unicaId = null;
      $st = Database::forTable('empresas')->prepare('SELECT COUNT(*) AS total, MIN(id) AS menor FROM empresas');
      $st->execute();
      $linha = $st->fetch(PDO::FETCH_ASSOC);
      $st->closeCursor(); // MariaDB exige drenar o result set antes da próxima consulta
      $total = (int)($linha['total'] ?? 0);
      $menor = isset($linha['menor']) ? (int)$linha['menor'] : 0;
      return self::$unicaId = ($total === 1 && $menor > 0) ? $menor : null;
    } catch (Throwable $e) {
      // Banco indisponível (instalador, CLI sem config) é estado normal, não defeito: sem empresa
      // resolvida o Hub segue exatamente como seguia antes desta classe existir.
      return self::$unicaId = null;
    } finally {
      self::$resolvendo = false;
      self::$unicaResolvida = true;
    }
  }

  /** Esquece a empresa única memorizada. Para teste e para depois de mexer no cadastro. */
  public static function limparCache(): void {
    self::$unicaResolvida = false;
    self::$unicaId = null;
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
