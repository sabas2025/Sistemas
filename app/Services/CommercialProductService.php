<?php
/**
 * V104.16 - Camada comercial do produto com licenciamento e conectores plugáveis reais.
 * Não expõe tokens, senhas ou dados reais. Todas as telas comerciais trabalham
 * com catálogo, licenças e faturas de controle comercial do HUB.
 */
class CommercialProductService {
  public static function ensureSchema(): void {
    SchemaRuntimePolicyService::requireTables([
      'comercial_clientes_licencas','comercial_conectores_catalogo','comercial_cobranca_faturas',
      'comercial_demo_ambientes','comercial_suporte_chamados','comercial_sla_eventos'
    ], 'módulo comercial');
    SchemaRuntimePolicyService::requireColumns('comercial_clientes_licencas', ['licenca_origem','ultimo_check_em','bloquear_ao_vencer','assinatura_hmac'], 'licenciamento comercial');
    self::seedConnectors();
  }

  private static function seedConnectors(): void {
    $pdo = Database::connection('core');
    $items = [
      ['tiny_v2','Tiny V2','erp','ativo','Integração legada Tiny para pedidos, estoque e produtos em operações existentes.','Token/API V2, SKU de homologação e endpoints configurados.'],
      ['tiny_v3','Tiny V3','erp','ativo','Integração Tiny OAuth com token vault, auditoria, retry e circuit breaker.','Client ID, Client Secret, Redirect URI e OAuth validado.'],
      ['vsm_integradora','VSM pedidos-integradora','erp','ativo','Conector principal para o fluxo Tiny → HUB → VSM e retorno fiscal/estoque.','Credencial VSM de homologação/produção, empresa/filial/CNPJ e endpoints oficiais.'],
      ['vsm_loja','VSM pedidos-loja','loja','opcional','Perfil opcional para endpoints de loja quando a VSM solicitar uso específico.','Credencial separada ou autorização VSM. Não usar como padrão sem confirmação.'],
    ];
    if (class_exists('ConnectorRegistryService')) {
      foreach (ConnectorRegistryService::catalog() as $c) {
        $items[] = [$c['codigo'], $c['nome'], $c['categoria'], $c['status'], 'Conector plugável registrado no catálogo operacional.', implode('; ', $c['requirements'] ?? [])];
      }
    } else {
      $items = array_merge($items, [
        ['bling','Bling','erp','planejado','Conector futuro para pedidos, produtos, estoque e fiscal no Bling.','OAuth/API Bling e mapeamento de campos.'],
        ['omie','Omie','erp','planejado','Conector futuro para empresas que usam Omie como ERP.','App Key/App Secret e escopo de integração.'],
      ]);
    }
    $st = $pdo->prepare('INSERT INTO comercial_conectores_catalogo(codigo,nome,categoria,status,descricao,requisitos) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE nome=VALUES(nome), categoria=VALUES(categoria), status=VALUES(status), descricao=VALUES(descricao), requisitos=VALUES(requisitos), atualizado_em=NOW()');
    foreach ($items as $i) $st->execute($i);
  }

  public static function phrases(): array {
    return [
      'Automatize pedidos, estoque e NF-e entre Tiny, VSM e outros ERPs com segurança, auditoria e rastreabilidade total.',
      'Um HUB profissional para integrar lojas, ERPs, marketplaces e operações fiscais sem depender de processos manuais.',
      'Da homologação à produção: fluxo controlado, fila inteligente, retry, circuit breaker, backup e Trace ID em cada operação.',
      'Integração não é só conectar API. É validar, auditar, proteger e garantir que pedido, estoque e nota fiscal sigam o fluxo correto.',
      'Reduza retrabalho, divergência de estoque e falhas fiscais com um middleware feito para operação real.',
      'Conectores plugáveis para crescer: Tiny, VSM, Bling, Omie, marketplaces e novos parceiros conforme a operação evolui.',
    ];
  }

  public static function plans(): array {
    return [
      ['nome'=>'Starter','preco'=>'R$ 490 a R$ 990/mês','setup'=>'R$ 5.000 a R$ 15.000','perfil'=>'Pequena operação em homologação ou primeira integração.','inclui'=>['1 empresa','até 2 conectores','backup e auditoria básica','suporte em horário comercial','ambiente único']],
      ['nome'=>'Professional','preco'=>'R$ 1.500 a R$ 5.000/mês','setup'=>'R$ 15.000 a R$ 50.000','perfil'=>'Operação com Tiny ↔ VSM e fluxo real de pedidos, estoque e NF-e.','inclui'=>['multiempresa/multifilial controlado','Tiny V2/V3 + VSM','fila, retry e circuit breaker','homologação assistida','relatórios e segurança avançada']],
      ['nome'=>'Enterprise','preco'=>'R$ 6.000 a R$ 15.000+/mês','setup'=>'R$ 50.000 a R$ 150.000+','perfil'=>'Operação crítica com SLA, múltiplos conectores e suporte prioritário.','inclui'=>['SLA avançado','monitoramento contínuo','ambiente staging + produção','conectores sob demanda','suporte prioritário e governança']],
      ['nome'=>'White-label / Código-fonte','preco'=>'sob proposta','setup'=>'R$ 80.000 a R$ 400.000+','perfil'=>'Parceiros, integradoras e empresas que querem marca própria ou código-fonte.','inclui'=>['licenciamento especial','customização visual','documentação técnica','treinamento técnico','contrato específico']],
    ];
  }

  public static function valuePropositions(): array {
    return [
      ['titulo'=>'Rastreabilidade total','texto'=>'Cada pedido, estoque, NF-e, webhook e erro recebe Trace ID, auditoria e histórico técnico.'],
      ['titulo'=>'Seguro para produção','texto'=>'2FA, CSP, CSRF, WAF no painel, backup assinado, restore validado e teste de segurança assistido.'],
      ['titulo'=>'Compatível com integrações reais','texto'=>'Tiny V2/V3, VSM pedidos-integradora e estrutura plugável para novos ERPs e marketplaces.'],
      ['titulo'=>'Operação resiliente','texto'=>'Fila, retry com backoff, circuit breaker, DLQ e workers CLI reduzem falhas em integrações instáveis.'],
      ['titulo'=>'Homologação profissional','texto'=>'Fluxos ativos, health checks, SchemaGuard, mapa do banco e evidências para entrada em produção.'],
      ['titulo'=>'Pronto para virar produto','texto'=>'Planos, licenças por cliente, cobrança, demo segura e documentação comercial/técnica.'],
    ];
  }

  public static function connectors(): array {
    self::ensureSchema();
    return Database::connection('core')->query('SELECT id,codigo,nome,categoria,status,descricao,requisitos,criado_em,atualizado_em FROM comercial_conectores_catalogo ORDER BY FIELD(status,"ativo","opcional","planejado"), nome')->fetchAll();
  }

  public static function licenses(): array {
    self::ensureSchema();
    return Database::connection('core')->query('SELECT id,cliente_nome,documento,email_responsavel,plano,status,ambiente,limite_empresas,limite_filiais,limite_conectores,data_inicio,data_expiracao,licenca_origem,ultimo_check_em,bloquear_ao_vencer,observacoes,criado_em,atualizado_em FROM comercial_clientes_licencas ORDER BY id DESC LIMIT 100')->fetchAll();
  }

  public static function invoices(): array {
    self::ensureSchema();
    $sql = 'SELECT f.id,f.cliente_licenca_id,f.descricao,f.valor_centavos,f.status,f.vencimento,f.forma_pagamento,f.referencia_externa,f.observacoes,f.criado_em,f.atualizado_em,l.cliente_nome FROM comercial_cobranca_faturas f LEFT JOIN comercial_clientes_licencas l ON l.id=f.cliente_licenca_id ORDER BY f.id DESC LIMIT 100';
    return Database::connection('core')->query($sql)->fetchAll();
  }

  public static function demos(): array {
    self::ensureSchema();
    return Database::connection('core')->query('SELECT id,nome,url,status,usa_dados_reais,observacoes,criado_em,atualizado_em FROM comercial_demo_ambientes ORDER BY id DESC LIMIT 50')->fetchAll();
  }

  public static function createDemoLicense(): string {
    self::ensureSchema();
    $key = 'HUB-DEMO-' . strtoupper(bin2hex(random_bytes(4))) . '-' . date('Y');
    $cfg = App::config();
    $secret = LicenseEnforcementService::secret();
    $hash = hash_hmac('sha256', $key, $secret);
    $pdo = Database::connection('core');
    $st = $pdo->prepare('INSERT INTO comercial_clientes_licencas(cliente_nome,documento,email_responsavel,plano,status,ambiente,limite_empresas,limite_filiais,limite_conectores,data_inicio,data_expiracao,license_key_hash,licenca_origem,bloquear_ao_vencer,assinatura_hmac,observacoes) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $rowForSignature = ['license_key_hash'=>$hash,'cliente_nome'=>'Cliente Demonstração','documento'=>'DEMO-'.date('YmdHis'),'plano'=>'professional','status'=>'trial','ambiente'=>'demo','limite_empresas'=>1,'limite_filiais'=>3,'limite_conectores'=>3,'data_expiracao'=>date('Y-m-d', strtotime('+15 days'))];
    $signature = LicenseEnforcementService::sign($rowForSignature);
    $st->execute([$rowForSignature['cliente_nome'], $rowForSignature['documento'], 'demo@cliente.local', $rowForSignature['plano'], $rowForSignature['status'], $rowForSignature['ambiente'], $rowForSignature['limite_empresas'], $rowForSignature['limite_filiais'], $rowForSignature['limite_conectores'], date('Y-m-d'), $rowForSignature['data_expiracao'], $hash, 'demo', 0, $signature, 'Licença demo criada sem dados reais. Chave exibida somente uma vez.']);
    Audit::event('comercial.licenca_demo_criada','info',['mensagem'=>'Licença demo criada','contexto'=>['plano'=>'professional','ambiente'=>'demo']]);
    return $key;
  }

  public static function createSampleInvoice(): void {
    self::ensureSchema();
    $pdo = Database::connection('core');
    $licenseId = (int)($pdo->query('SELECT id FROM comercial_clientes_licencas ORDER BY id DESC LIMIT 1')->fetchColumn() ?: 0);
    $st = $pdo->prepare('INSERT INTO comercial_cobranca_faturas(cliente_licenca_id,descricao,valor_centavos,status,vencimento,forma_pagamento,observacoes) VALUES(?,?,?,?,?,?,?)');
    $st->execute([$licenseId ?: null, 'Mensalidade HUB de Integração Professional', 250000, 'aberta', date('Y-m-d', strtotime('+7 days')), 'Pix/Boleto/Cartão', 'Fatura demonstrativa. Não integrada a gateway real.']);
    Audit::event('comercial.fatura_demo_criada','info',['mensagem'=>'Fatura demo criada','contexto'=>['valor_centavos'=>250000]]);
  }


  public static function supportTickets(): array {
    self::ensureSchema();
    return Database::connection('core')->query("SELECT c.id,c.titulo,c.prioridade,c.status,c.sla_resposta_horas,c.sla_resolucao_horas,c.aberto_em,c.prazo_resposta_em,c.prazo_resolucao_em,c.fechado_em,l.cliente_nome FROM comercial_suporte_chamados c LEFT JOIN comercial_clientes_licencas l ON l.id=c.cliente_licenca_id ORDER BY c.id DESC LIMIT 100")->fetchAll();
  }

  public static function createDemoTicket(): void {
    self::ensureSchema();
    $pdo = Database::connection('core');
    $licenseId = (int)($pdo->query('SELECT id FROM comercial_clientes_licencas ORDER BY id DESC LIMIT 1')->fetchColumn() ?: 0);
    $st = $pdo->prepare("INSERT INTO comercial_suporte_chamados(cliente_licenca_id,titulo,prioridade,status,sla_resposta_horas,sla_resolucao_horas,prazo_resposta_em,prazo_resolucao_em,observacoes) VALUES(?,?,?,?,?,?,?,?,?)");
    $st->execute([$licenseId ?: null, 'Chamado demo: validar fluxo Tiny → HUB → VSM', 'alta', 'aberto', 4, 24, date('Y-m-d H:i:s', strtotime('+4 hours')), date('Y-m-d H:i:s', strtotime('+24 hours')), 'Chamado demonstrativo sem dados reais.']);
    Audit::event('comercial.suporte_demo_criado','info',['mensagem'=>'Chamado demo de suporte/SLA criado.']);
  }

  public static function resetDemoEnvironment(): array {
    self::ensureSchema();
    $pdo = Database::connection('core');
    $pdo->exec("DELETE FROM comercial_cobranca_faturas WHERE observacoes LIKE '%demonstrativa%' OR observacoes LIKE '%demo%'");
    $pdo->exec("DELETE FROM comercial_suporte_chamados WHERE observacoes LIKE '%demonstrativo%' OR titulo LIKE 'Chamado demo:%'");
    $pdo->exec("DELETE FROM comercial_demo_ambientes WHERE usa_dados_reais=0");
    $st = $pdo->prepare("INSERT INTO comercial_demo_ambientes(nome,url,status,usa_dados_reais,observacoes) VALUES(?,?,?,?,?)");
    $st->execute(['Demo padrão sem dados reais', 'index.php?page=demo-online', 'ativo', 0, 'Ambiente reiniciado automaticamente. Não contém clientes, pedidos ou tokens reais.']);
    Audit::event('comercial.demo_reset','info',['mensagem'=>'Ambiente demo reiniciado sem dados reais.']);
    return ['ok'=>true,'mensagem'=>'Ambiente demo reiniciado sem dados reais.'];
  }

  public static function docs(): array {
    $base = __DIR__.'/../../docs/comercial';
    $files = glob($base.'/*.md') ?: [];
    $out = [];
    foreach ($files as $f) {
      $out[] = ['arquivo'=>basename($f), 'titulo'=>self::docTitle($f), 'path'=>$f, 'resumo'=>self::docSummary($f)];
    }
    usort($out, fn($a,$b)=>strcmp($a['arquivo'],$b['arquivo']));
    return $out;
  }

  public static function docContent(string $file): ?string {
    $base = realpath(__DIR__.'/../../docs/comercial');
    $path = realpath($base.'/'.basename($file));
    if (!$base || !$path || !str_starts_with($path, $base) || !is_file($path)) return null;
    return file_get_contents($path) ?: '';
  }

  private static function docTitle(string $path): string {
    $fh = fopen($path, 'r');
    if ($fh) {
      $line = fgets($fh); fclose($fh);
      if ($line !== false && str_starts_with(trim($line), '#')) return trim(ltrim(trim($line), '# '));
    }
    return basename($path);
  }

  private static function docSummary(string $path): string {
    $txt = file_get_contents($path) ?: '';
    $txt = trim(preg_replace('/\s+/', ' ', strip_tags(str_replace(['#','*','`'], '', $txt))));
    return mb_substr($txt, 0, 180) . (mb_strlen($txt) > 180 ? '...' : '');
  }
}
