<?php
class LegacyRegistryService {
  private const CLASSIFICATION = [
    'V50Controller' => [
      'status' => 'LEGADO_CONTROLADO',
      'motivo' => 'Rotas históricas de validação Tiny/VSM, produção segura e limpeza. Mantidas por compatibilidade, mas auditadas.',
      'paginas_operacionais' => ['tiny-validacao','tiny-validacao-executar','vsm-simulador','vsm-simulador-executar','producao-segura','producao-segura-executar','limpeza-retencao','limpeza-retencao-executar'],
      'paginas_migracao' => ['atualizar-v50-producao-segura'],
    ],
    'V51Controller' => [
      'status' => 'LEGADO_CONTROLADO_COM_FUNCOES_ATIVAS',
      'motivo' => 'Controller histórico que ainda contém funções operacionais de produto novo, validação de pedido Tiny → VSM e ciclo de pedido/XML.',
      'paginas_operacionais' => ['produto-novo-politica','produto-novo-politica-salvar','pedidos-validacao-vsm','pedido-validacao-detalhe','pedido-validacao-enfileirar','pedido-ciclo-vida','pedido-ciclo-detalhe','pedido-ciclo-enviar-tiny'],
      'paginas_migracao' => ['atualizar-v51-produto-pedido-validacao','atualizar-v52-ciclo-pedido-xml'],
    ],
  ];

  public static function all(): array { return self::CLASSIFICATION; }

  public static function guard(string $controller, string $page): void {
    $info = self::CLASSIFICATION[$controller] ?? ['status'=>'DESCONHECIDO','motivo'=>'Controller não classificado.'];
    $isMigration = str_starts_with($page, 'atualizar-v') || in_array($page, $info['paginas_migracao'] ?? [], true);
    if ($isMigration) {
      PermissionService::require('database','validar');
      if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        Audit::event('legacy.migracao.bloqueada','alerta',[ 'mensagem'=>'Rota de migração legada bloqueada fora do fluxo seguro.', 'contexto'=>['controller'=>$controller,'page'=>$page,'status'=>$info['status']] ]);
        redirect('index.php?page=migracoes-seguras&legado_bloqueado=1');
      }
    }
    if (!in_array($page, array_merge($info['paginas_operacionais'] ?? [], $info['paginas_migracao'] ?? []), true)) {
      Audit::event('legacy.rota_nao_classificada','alerta',[ 'mensagem'=>'Rota legada sem classificação explícita acessada.', 'contexto'=>['controller'=>$controller,'page'=>$page,'status'=>$info['status']] ]);
    }
  }
}
