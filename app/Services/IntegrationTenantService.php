<?php
/** Limite explícito desta release: uma empresa por instalação e credenciais globais vinculadas. */
class IntegrationTenantService {
  public static function singleEmpresaId(): int {
    $rows = Database::forTable('empresas')->query('SELECT id,ativo FROM empresas ORDER BY id LIMIT 2')->fetchAll();
    if (count($rows) !== 1 || (int)$rows[0]['ativo'] !== 1) {
      throw new RuntimeException('TENANT_SINGLE_COMPANY_REQUIRED: esta release exige uma única empresa ativa cadastrada. Multicliente ainda não homologado.');
    }
    return (int)$rows[0]['id'];
  }

  public static function boundEmpresaId(): int {
    $id = self::singleEmpresaId();
    if (!Database::columnExists('configuracoes_integracao', 'integracao_empresa_id')) {
      throw new RuntimeException('TENANT_BINDING_REQUIRED: aplique a migration 017 e vincule a integração à empresa nas configurações MyOuro.');
    }
    $st = Database::forTable('configuracoes_integracao')->query('SELECT integracao_empresa_id FROM configuracoes_integracao WHERE id=1');
    $bound = (int)$st->fetchColumn();
    $st->closeCursor();
    if ($bound !== $id) throw new RuntimeException('TENANT_BINDING_REQUIRED: confirme a empresa das credenciais Tiny/VSM nas configurações MyOuro.');
    return $id;
  }

  public static function sessionEmpresaId(): ?int {
    $user = $_SESSION['user'] ?? [];
    if (empty($user['id'])) return null;
    $st = Database::forTable('usuarios')->prepare('SELECT empresa_id,ativo,perfil FROM usuarios WHERE id=?');
    $st->execute([(int)$user['id']]);
    $row = $st->fetch(); $st->closeCursor();
    if (!$row || (int)$row['ativo'] !== 1) throw new RuntimeException('TENANT_SESSION_INVALID: usuário inativo ou removido.');
    if ((string)$row['perfil'] !== (string)($user['perfil'] ?? '')) throw new RuntimeException('TENANT_SESSION_INVALID: perfil alterado; entre novamente.');
    $id = (int)($row['empresa_id'] ?? 0);
    if ($id !== (int)($user['empresa_id'] ?? 0)) throw new RuntimeException('TENANT_SESSION_INVALID: vínculo alterado; entre novamente.');
    return $id > 0 ? $id : null;
  }

  public static function enforceRequest(string $page): void {
    // Administração permanece acessível para corrigir schema e vínculos, com o RBAC de cada rota.
    $maintenance = ['configuracoes','salvar-configuracoes','myouro-configuracoes','myouro-salvar',
      'integracao-vincular-empresa','usuarios','usuario-salvar','usuario-excluir','salvar-usuario',
      'central-tecnica','validar-banco','mapa-banco','health-modulos','migracoes-seguras',
      'migracao-aplicar','enterprise-core','enterprise-core-aplicar','trocar-senha'];
    if (in_array($page, $maintenance, true)) return;
    try {
      self::boundEmpresaId();
      if (!str_starts_with($page, 'api/') && PHP_SAPI !== 'cli' && Auth::check()) {
        if (self::sessionEmpresaId() !== self::singleEmpresaId()) throw new RuntimeException('TENANT_CONTEXT_REQUIRED: atribua uma empresa ao usuário e entre novamente.');
      }
    } catch (RuntimeException $e) {
      http_response_code(409);
      header('Content-Type: text/plain; charset=UTF-8');
      echo $e->getMessage();
      exit;
    }
  }
}
