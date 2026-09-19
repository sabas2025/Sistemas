<?php
class MyOuroController extends BaseModuleController {
  public function dispatch(string $page): void {
    Auth::requireLogin();
    // Credenciais globais exigem administrador da instalação nesta release single-company.
    Auth::requirePerfil(['admin']);
    PermissionService::require('configuracoes', $page === 'myouro-configuracoes' ? 'visualizar' : 'editar');
    $error = null; $result = null; $connection = []; $empresa = null;
    try {
      if (!MyOuroConfigService::ready()) throw new RuntimeException('Schema pendente: aplique a migration 017 conforme UPGRADE-R7-20260917.md.');
      $empresa = IntegrationTenantService::singleEmpresaId();
      if ($page !== 'myouro-configuracoes') {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit('Método não permitido.'); }
        Csrf::validate();
        if ($page === 'integracao-vincular-empresa') {
          if (($_POST['confirmacao'] ?? '') !== 'CONFIRMO EMPRESA '.$empresa) throw new InvalidArgumentException('Confirme explicitamente a propriedade das credenciais antes de vincular.');
          $st = Database::forTable('configuracoes_integracao')->prepare('UPDATE configuracoes_integracao SET integracao_empresa_id=? WHERE id=1 AND (integracao_empresa_id IS NULL OR integracao_empresa_id=?)');
          $st->execute([$empresa,$empresa]);
          IntegrationTenantService::boundEmpresaId();
          Audit::event('integracao.empresa_vinculada','sucesso',['mensagem'=>'Administrador confirmou a propriedade das credenciais globais','contexto'=>['empresa_id'=>$empresa,'usuario_id'=>Auth::user()['id']]]);
          redirect('index.php?page=myouro-configuracoes');
        } elseif ($page === 'myouro-salvar') {
          MyOuroConfigService::save($empresa,$_POST);
          Audit::event('myouro.configuracao_salva','sucesso',['mensagem'=>'Conexão de consulta salva; testes anteriores invalidados','contexto'=>['empresa_id'=>$empresa]]);
          redirect('index.php?page=myouro-configuracoes');
        } elseif ($page === 'myouro-testar') {
          $produto = filter_var($_POST['codigo_produto'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>2147483647]]);
          if ($produto === false) throw new InvalidArgumentException('Informe um código numérico de produto VSM válido.');
          $result = (new MyOuroGraphqlService())->consultarEstoque($empresa,$produto);
        } else { http_response_code(404); exit('Rota não encontrada.'); }
      }
      $connection = MyOuroConfigService::get($empresa);
    } catch (PDOException $e) {
      http_response_code(503); $error='Banco indisponível ou schema pendente; consulte a Central Técnica.';
    } catch (RuntimeException|InvalidArgumentException $e) {
      http_response_code(409); $error=$e->getMessage();
    }
    $this->view('myouro_configuracoes', ['pageTitle'=>'MyOuro GraphQL — Consultas','connection'=>$connection,'empresa'=>$empresa,'error'=>$error,'result'=>$result]);
  }
}
