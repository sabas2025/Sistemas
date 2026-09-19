<?php
class ProductionGoLiveService {
  public static function ensureSchema(): void {
    SchemaRuntimePolicyService::requireTable('production_go_live_checks', 'go-live de produção');
  }

  public static function executar(): array {
    self::ensureSchema();
    $trace = RequestContext::id();
    $cfg = IntegrationConfig::get();
    $appCfg = App::config();
    $checks = [];
    $add = function(string $grupo, string $nome, string $status, string $risco, string $acao, array $ctx=[]) use (&$checks) {
      $checks[] = [
        'grupo'=>$grupo,
        'nome'=>$nome,
        'status'=>$status,
        'ok'=>$status === 'ok',
        'risco'=>$risco,
        'acao'=>$acao,
        'contexto'=>$ctx,
      ];
    };

    $https = self::httpsAtivo();
    $installExiste = is_file(__DIR__.'/../../public/install.php');
    $installLock = is_file(__DIR__.'/../../storage/installed.lock') || is_file(__DIR__.'/../../storage/install.lock');
    $debugSeguro = !App::isLocal();
    $storageOk = is_dir(__DIR__.'/../../storage') && is_writable(__DIR__.'/../../storage');
    $cacheOk = is_dir(__DIR__.'/../../storage/cache') ? is_writable(__DIR__.'/../../storage/cache') : @mkdir(__DIR__.'/../../storage/cache',0775,true);
    $encryptionKey = (string)($appCfg['security']['encryption_key'] ?? '');
    $db = $appCfg['db'] ?? [];

    $add('Ambiente','HTTPS ativo', (App::isLocal() || $https) ? 'ok' : 'bloqueio', 'OAuth, webhooks e tokens não devem trafegar sem HTTPS.', 'Ativar SSL no domínio do Hub antes de operar em produção.');
    $add('Ambiente','APP_ENV em produção', App::isProduction() ? 'ok' : 'alerta', 'Ambiente local/desenvolvimento pode expor erros e relaxar validações.', "Alterar config/config.php para app_env => 'production' no servidor real.", ['app_env'=>App::env()]);
    $add('Ambiente','Erros sensíveis ocultos', $debugSeguro ? 'ok' : 'alerta', 'display_errors ativo em ambiente real expõe caminhos e detalhes técnicos.', 'Usar app_env production fora do XAMPP.');
    $add('Ambiente','Storage gravável', $storageOk ? 'ok' : 'bloqueio', 'Sem permissão de escrita, logs, cache, backups e auditoria podem falhar.', 'Ajustar permissão da pasta storage para escrita pelo PHP.');
    $add('Ambiente','Cache gravável', $cacheOk ? 'ok' : 'alerta', 'Cache sem escrita prejudica performance e PWA/status.', 'Ajustar permissão de storage/cache.');

    $add('Segurança','install.php protegido', (!$installExiste || $installLock) ? 'ok' : 'bloqueio', 'Instalador exposto em produção permite reconfiguração indevida.', 'Criar storage/installed.lock, remover install.php ou bloquear por .htaccess após instalar.');
    $add('Segurança','Chave de criptografia configurada', strlen($encryptionKey) >= 24 ? 'ok' : 'bloqueio', 'Tokens Tiny/VSM podem ficar mal protegidos sem chave forte.', 'Gerar encryption_key forte no instalador ou config/config.php.');
    $add('Segurança','Senha MySQL não vazia', (App::isLocal() || trim((string)($db['pass'] ?? '')) !== '') ? 'ok' : 'bloqueio', 'Senha vazia em hospedagem é risco crítico.', 'Usar usuário MySQL dedicado com senha forte.');
    $add('Segurança','Usuário MySQL dedicado', ((string)($db['user'] ?? '')) !== 'root' || App::isLocal() ? 'ok' : 'alerta', 'Usuário root em produção amplia dano em caso de incidente.', 'Criar usuário exclusivo para o Hub com permissões apenas no banco do Hub.');
    $add('Segurança','2FA disponível', class_exists('TwoFactorService') ? 'ok' : 'alerta', 'Login administrativo sem segunda camada aumenta risco.', 'Ativar 2FA para administradores.');

    $tinyV2 = self::tinyResumo('v2', $cfg);
    $tinyV3 = self::tinyResumo('v3', $cfg);
    $tinySelecionado = strtolower((string)($cfg['tiny_versao'] ?? 'v2'));
    $tinyOperacionalOk = ($tinySelecionado === 'v3') ? $tinyV3['apto_producao'] : $tinyV2['apto_producao'];
    $add('Tiny','Tiny selecionado apto', $tinyOperacionalOk ? 'ok' : 'bloqueio', 'A versão Tiny selecionada ainda não está 100% homologada.', 'Concluir homologação da versão Tiny selecionada antes de liberar produção.', ['tiny_versao'=>$tinySelecionado,'v2'=>$tinyV2,'v3'=>$tinyV3]);
    $add('Tiny','Tiny V2 como fallback', ($tinySelecionado === 'v2' || $tinyV2['token_configurado']) ? 'ok' : 'alerta', 'Sem fallback, falha na V3 pode parar operação.', 'Manter Tiny V2 configurado como contingência enquanto V3 amadurece.');
    $add('Tiny','Tiny V3 operacional só com 100%', empty($cfg['tiny_v3_operacional']) || $tinyV3['apto_producao'] ? 'ok' : 'bloqueio', 'Tiny V3 operacional sem homologação completa pode gerar falhas de produto/estoque/pedido.', 'Executar Homologação Completa V3 até 100% antes de marcar operacional.');

    $vsmUrl = trim((string)($cfg['vsm_url'] ?? ''));
    $vsmToken = trim((string)($cfg['vsm_token'] ?? ''));
    $add('VSM','URL VSM configurada', $vsmUrl !== '' ? 'ok' : 'bloqueio', 'Sem endpoint VSM, o Hub não consulta estoque nem envia/recebe fluxo real.', 'Configurar URL oficial da VSM.', ['vsm_url'=>$vsmUrl]);
    $add('VSM','Token VSM configurado', $vsmToken !== '' ? 'ok' : 'bloqueio', 'Sem token VSM, consultas podem retornar 401/403.', 'Configurar token/chave VSM e liberar domínio/IP do Hub.');
    // Achado I-23 (2026-09-15): instalar com Ambiente=Produção deixava o Hub apontando para o
    // host de HOMOLOGAÇÃO da VSM, e NENHUMA checagem acusava. O instalador semeia
    // '{VSM_URL}' => 'https://conectavenda.homolog.vsm.com.br' como constante, qualquer que seja o
    // ambiente escolhido, e IntegrationConfig::get() repete esse mesmo host como fallback quando a
    // coluna está vazia — então nem apagar o valor resolve. As checagens existentes só exigem URL
    // não-vazia e bem formada. Medido: com ambiente='producao' gravado, o vsm_url efetivo era o de
    // homologação e o go-live respondia 'ok'. Aqui a coerência é conferida pelo HOST, por RÓTULO —
    // nunca por substring na URL inteira, que é a armadilha do B-06.
    $endpointsAmbiente = self::endpointsForaDoAmbiente($cfg);
    $add('Ambiente','Endpoints coerentes com o ambiente',
        $endpointsAmbiente['coerente'] ? 'ok' : 'bloqueio',
        'Em produção, apontar para host de homologação envia pedido, estoque e nota para o ambiente errado — e o erro só aparece quando o cliente reclama.',
        'Configurar a URL oficial de produção em Configurações → VSM/Tiny antes de liberar a operação.',
        $endpointsAmbiente);

    $add('VSM','Produto novo sob aprovação', !empty($cfg['sync_bloquear_produto_novo_vsm']) || !empty($cfg['sync_aprovacao_manual_produto_novo_vsm']) ? 'ok' : 'alerta', 'Produto novo automático pode duplicar cadastro e causar divergência.', 'Manter produto novo em aprovação manual no início da produção.');

    $xmlStatus = self::xmlNfeResumo();
    $add('XML/NF-e','Módulo XML/NF-e disponível', $xmlStatus['servico_ok'] ? 'ok' : 'bloqueio', 'Sem módulo XML/NF-e, o retorno VSM → Hub → Tiny não fica rastreável.', 'Validar XmlNfeHomologationService e worker_xml_nfe.php.', $xmlStatus);
    $add('XML/NF-e','Worker XML/NF-e presente', is_file(__DIR__.'/../../public/worker_xml_nfe.php') ? 'ok' : 'alerta', 'Sem worker dedicado, XML pode depender de ação manual.', 'Configurar cron para worker_xml_nfe.php.');

    $backup = self::backupResumo();
    $add('Backups','Backup automático/manual disponível', $backup['estrutura_ok'] ? 'ok' : 'bloqueio', 'Entrar em produção sem backup validado dificulta recuperação.', 'Gerar backup e testar restauração antes de liberar.', $backup);
    $add('Backups','Restauração testada', $backup['tem_historico'] ? 'ok' : 'alerta', 'Backup sem teste de restauração pode falhar no momento crítico.', 'Fazer um backup de teste e simular restauração em ambiente separado.');

    $pwaOk = is_file(__DIR__.'/../../public/manifest.webmanifest') && is_file(__DIR__.'/../../public/sw.js');
    $add('Operação','PWA instalado/atualizável', $pwaOk ? 'ok' : 'alerta', 'Operadores podem usar versão antiga se cache não atualizar.', 'Usar página PWA do Hub para atualizar cache após publicar versão nova.');
    $add('Operação','Auditoria ativa', class_exists('Audit') ? 'ok' : 'bloqueio', 'Sem auditoria, não há rastreabilidade por Trace ID.', 'Manter auditoria e logs ativos em produção.');

    $bloqueios = count(array_filter($checks, fn($c)=>$c['status']==='bloqueio'));
    $alertas = count(array_filter($checks, fn($c)=>$c['status']==='alerta'));
    $ok = count(array_filter($checks, fn($c)=>$c['status']==='ok'));
    $score = (int)round(($ok / max(1, count($checks))) * 100);
    $status = $bloqueios > 0 ? 'bloqueado' : ($alertas > 0 ? 'apto_com_alertas' : 'apto');
    $res = [
      'trace_id'=>$trace,
      'status'=>$status,
      'score'=>$score,
      'bloqueios'=>$bloqueios,
      'alertas'=>$alertas,
      'checks'=>$checks,
      'resumo'=>self::resumoExecutivo($status,$bloqueios,$alertas,$score),
      'executado_em'=>date('Y-m-d H:i:s'),
    ];
    $st = Database::connection('core')->prepare('INSERT INTO production_go_live_checks(trace_id,status,score,bloqueios,alertas,resultado_json) VALUES(?,?,?,?,?,?)');
    $st->execute([$trace,$status,$score,$bloqueios,$alertas,json_encode(SensitiveDataService::mask($res), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
    Audit::event('production.golive.check', $status==='apto'?'sucesso':'alerta', ['mensagem'=>$res['resumo'], 'contexto'=>SensitiveDataService::mask($res)]);
    return $res;
  }

  /**
   * Um host é de homologação quando algum RÓTULO dele é de teste — `conectavenda.homolog.vsm.com.br`
   * tem o rótulo `homolog`. Comparar rótulo, e não a URL inteira, evita o falso positivo de um
   * caminho `/homologacao` e o falso negativo de `homologvsm` grudado. O que ele NÃO prova: que um
   * host de aparência produtiva seja mesmo de produção — isso só o provedor sabe.
   */
  private static function hostDeHomologacao(string $url): bool {
    $host = strtolower((string)(parse_url(trim($url), PHP_URL_HOST) ?? ''));
    if ($host === '') return false;
    foreach (explode('.', $host) as $rotulo) {
      if (in_array($rotulo, ['homolog','homologacao','homologação','sandbox','staging','teste','test','dev'], true)) return true;
    }
    return false;
  }

  /** @return array{coerente:bool,ambiente:string,suspeitos:array<int,string>} */
  private static function endpointsForaDoAmbiente(array $cfg): array {
    $ambiente = strtolower(trim((string)($cfg['ambiente'] ?? '')));
    $suspeitos = [];
    if ($ambiente === 'producao') {
      foreach (['vsm_url'=>'VSM','tiny_v2_url'=>'Tiny V2','tiny_v3_url'=>'Tiny V3'] as $chave=>$rotulo) {
        $url = (string)($cfg[$chave] ?? '');
        if ($url !== '' && self::hostDeHomologacao($url)) $suspeitos[] = $rotulo.': '.(string)(parse_url($url, PHP_URL_HOST) ?? '');
      }
    }
    return ['coerente'=>$suspeitos===[], 'ambiente'=>$ambiente!==''?$ambiente:'não declarado', 'suspeitos'=>$suspeitos];
  }

  public static function historico(int $limit=5): array {
    self::ensureSchema();
    $st = Database::connection('core')->prepare('SELECT * FROM production_go_live_checks ORDER BY id DESC LIMIT ?');
    $st->bindValue(1, max(1,min(50,$limit)), PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll(PDO::FETCH_ASSOC);
  }

  public static function bloquearInstalador(): array {
    $dir = __DIR__.'/../../storage';
    if (!is_dir($dir)) @mkdir($dir,0775,true);
    $file = $dir.'/installed.lock';
    file_put_contents($file, 'Hub de Integração instalado em '.date('c').PHP_EOL.'Trace: '.RequestContext::id().PHP_EOL);
    Audit::event('production.install.lock','sucesso',['mensagem'=>'Lock de instalação criado.','contexto'=>['arquivo'=>'storage/installed.lock']]);
    return ['ok'=>is_file($file),'arquivo'=>'storage/installed.lock','mensagem'=>'Lock criado. Bloqueie/remova install.php no servidor para segurança máxima.'];
  }

  private static function httpsAtivo(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
  }

  private static function tinyResumo(string $versao, array $cfg): array {
    try {
      if ($versao === 'v3') {
        $r = TinyV3HomologationService::resumo($cfg);
        return ['progresso'=>(int)($r['progresso'] ?? 0),'apto_producao'=>((int)($r['progresso'] ?? 0) >= 100),'token_configurado'=>!empty($cfg['tiny_v3_manual_access_token']) || !empty($cfg['tiny_v3_token']),'status'=>$r['status_geral'] ?? 'pendente'];
      }
      $r = TinyV2HomologationService::resumo($cfg);
      return ['progresso'=>(int)($r['progresso'] ?? 0),'apto_producao'=>((int)($r['progresso'] ?? 0) >= 100),'token_configurado'=>trim((string)($cfg['tiny_v2_token'] ?? '')) !== '','status'=>$r['status_geral'] ?? 'pendente'];
    } catch(Throwable $e) {
      return ['progresso'=>0,'apto_producao'=>false,'token_configurado'=>false,'status'=>'erro','erro'=>$e->getMessage()];
    }
  }

  private static function xmlNfeResumo(): array {
    return [
      'servico_ok'=>class_exists('XmlNfeHomologationService'),
      'controller_ok'=>class_exists('XmlNfeController'),
      'worker_ok'=>is_file(__DIR__.'/../../public/worker_xml_nfe.php'),
    ];
  }

  private static function backupResumo(): array {
    $dir = __DIR__.'/../../storage/backups';
    $files = is_dir($dir) ? glob($dir.'/*') : [];
    return ['estrutura_ok'=>class_exists('BackupService') || is_dir($dir), 'diretorio'=>$dir, 'tem_historico'=>!empty($files), 'quantidade'=>is_array($files)?count($files):0];
  }

  private static function resumoExecutivo(string $status, int $bloqueios, int $alertas, int $score): string {
    if ($status === 'apto') return 'Produção liberada tecnicamente. Score '.$score.'%.';
    if ($status === 'apto_com_alertas') return 'Produção possível com alertas. Corrija '.$alertas.' ponto(s) antes da operação definitiva.';
    return 'Produção bloqueada. Corrija '.$bloqueios.' bloqueio(s) crítico(s).';
  }
}
