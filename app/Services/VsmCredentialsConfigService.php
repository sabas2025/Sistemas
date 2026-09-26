<?php
/**
 * F6-07 (auditoria Fase 6, 2026-09-26) — persistência das credenciais reais da VSM Conecta Venda.
 *
 * A lógica mora aqui, e não no DashboardController, porque o controller está a poucas dezenas de
 * bytes do teto que a CI impõe (tests/enterprise/v104_48_1_architecture_test): lógica de domínio
 * nova entra por serviço, conforme a lição registrada no CLAUDE.md.
 *
 * Modelo de credenciais (contrato contracts/vsm/pedidos-integradora.openapi.json):
 *   - Integradora: clientToken + clientSecret -> autenticam em POST /v1/auth/token (JWT).
 *   - Loja: clientTokenLoja -> vai na query do pedido, junto do clientTokenIntegradora
 *     (que É o clientToken da integradora — não há campo separado).
 *
 * Compatível com bancos antigos: só grava colunas que já existem (a migration 20260926_018 as
 * cria). Ao salvar credenciais, o JWT em cache (vsm_access_token) é descartado para forçar nova
 * troca com as credenciais atuais.
 */
class VsmCredentialsConfigService {
  /** Colunas de credencial cifradas gravadas a partir do formulário de Configurações. */
  private const CAMPOS = ['vsm_client_token','vsm_client_secret','vsm_client_token_loja'];

  /**
   * @param array<string,mixed> $post  Dados crus do formulário ($_POST).
   * @param array<string,mixed> $atual Configuração atual já descriptografada (IntegrationConfig::get()).
   */
  public static function salvarDoFormulario(array $post, array $atual): void {
    try {
      $sets = []; $values = [];
      foreach (self::CAMPOS as $campo) {
        if (!Database::columnExists('configuracoes_integracao', $campo)) continue;
        // keepIfMasked: se o operador não digitou (veio mascarado), mantém o valor atual.
        $valor = Secrets::keepIfMasked((string)($post[$campo] ?? ''), (string)($atual[$campo] ?? ''));
        $sets[] = $campo.'=?';
        $values[] = CryptoService::encrypt($valor);
      }
      if ($sets === []) return;
      // Descarta o JWT em cache: as credenciais podem ter mudado, então o token antigo não vale mais.
      if (Database::columnExists('configuracoes_integracao','vsm_access_token')) $sets[] = 'vsm_access_token=NULL';
      if (Database::columnExists('configuracoes_integracao','vsm_access_token_expira_em')) $sets[] = 'vsm_access_token_expira_em=NULL';
      Database::forTable('configuracoes_integracao')
        ->prepare('UPDATE configuracoes_integracao SET '.implode(',', $sets).' WHERE id=1')
        ->execute($values);
    } catch (Throwable $e) {
      Audit::exception($e, 'configuracoes.vsm_credenciais.erro', ['codigo_erro' => 'VSM_CREDENTIALS_SAVE_ERROR']);
    }
  }
}
