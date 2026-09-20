<?php require __DIR__.'/layout_top.php'; ?>
<?php
$policy = $policy ?? ($readiness['config'] ?? []);
$canAdminLlm = class_exists('LlmPolicyService') ? LlmPolicyService::canUse($policy, true) : false;
$providers = ['none'=>'Nenhum','openai'=>'OpenAI','anthropic'=>'Anthropic','gemini'=>'Gemini','deepseek'=>'DeepSeek','qwen'=>'Qwen','llama_local'=>'Llama local','custom'=>'Custom'];
$ambientes = ['homologacao'=>'Homologação','producao'=>'Produção'];
?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
  <div>
    <h2><i class="bi bi-robot"></i> Governança LLM</h2>
    <p class="text-muted mb-0">IA corporativa segura: desativada por padrão, chave no cofre, custo controlado, redaction, aprovação humana e anti prompt injection.</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-outline-secondary" href="index.php?page=enterprise-core">Enterprise Core</a>
    <a class="btn btn-outline-secondary" href="index.php?page=enterprise-observabilidade">Observabilidade</a>
  </div>
</div>

<div class="alert alert-warning border-0 shadow-sm">
  <b>Regra de segurança:</b> a IA não altera banco, Tiny, VSM, pedidos, estoque, fiscal, XML, produção, logs ou configurações automaticamente. Chamada externa real só ocorre por <b>ação humana</b>, apenas em <b>homologação</b>, com uma aprovação <b>aprovada</b>, dentro do limite de custo — e o texto tem de conferir com o prompt aprovado. Produção permanece bloqueada nesta versão.
</div>

<div class="row g-3 mb-3">
  <?php foreach(($readiness['checks'] ?? []) as $c):
    $status = strtolower((string)($c['status'] ?? 'atencao'));
    $cls = $status==='ok' ? 'success' : ($status==='bloqueio' ? 'danger' : 'warning');
  ?>
  <div class="col-xl-3 col-md-4 col-sm-6">
    <div class="card-soft h-100">
      <small class="text-muted"><?=e($c['item'])?></small>
      <h5 class="text-<?=$cls?>"><?=e(strtoupper($status))?></h5>
      <p class="small mb-0"><?=e($c['mensagem'])?></p>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card-soft mb-3">
      <h5><i class="bi bi-shield-lock"></i> Política LLM</h5>
      <form method="post" class="row g-3">
        <?=Csrf::input()?>
        <input type="hidden" name="acao_llm" value="save_policy">
        <div class="col-md-4">
          <label class="form-label">Ambiente</label>
          <select name="environment" class="form-select" <?=$canAdminLlm?'':'disabled'?>>
            <?php foreach($ambientes as $k=>$v): ?><option value="<?=$k?>" <?=($policy['environment']??'homologacao')===$k?'selected':''?>><?=$v?></option><?php endforeach; ?>
          </select>
          <small class="text-muted">Recomendado: homologação.</small>
        </div>
        <div class="col-md-4">
          <label class="form-label">Provider</label>
          <select name="provider" class="form-select" <?=$canAdminLlm?'':'disabled'?>>
            <?php foreach($providers as $k=>$v): ?><option value="<?=$k?>" <?=($policy['provider']??'none')===$k?'selected':''?>><?=$v?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Modelo</label>
          <input name="model" class="form-control" value="<?=e($policy['model'] ?? '')?>" placeholder="ex: gpt-4.1-mini" <?=$canAdminLlm?'':'disabled'?>>
        </div>
        <div class="col-md-4"><label class="form-label">Max tokens</label><input type="number" name="max_tokens" class="form-control" value="<?=e((string)($policy['max_tokens'] ?? 2048))?>" <?=$canAdminLlm?'':'disabled'?>></div>
        <div class="col-md-4"><label class="form-label">Temperatura</label><input type="number" step="0.01" min="0" max="1" name="temperature" class="form-control" value="<?=e((string)($policy['temperature'] ?? 0.2))?>" <?=$canAdminLlm?'':'disabled'?>></div>
        <div class="col-md-4"><label class="form-label">Papéis permitidos</label><input name="allowed_roles" class="form-control" value="<?=e($policy['allowed_roles'] ?? 'admin,supervisor')?>" <?=$canAdminLlm?'':'disabled'?>></div>
        <div class="col-md-4"><label class="form-label">Limite diário R$</label><input type="number" step="0.01" name="daily_cost_limit" class="form-control" value="<?=e((string)($policy['daily_cost_limit'] ?? 10))?>" <?=$canAdminLlm?'':'disabled'?>></div>
        <div class="col-md-4"><label class="form-label">Limite mensal R$</label><input type="number" step="0.01" name="monthly_cost_limit" class="form-control" value="<?=e((string)($policy['monthly_cost_limit'] ?? 100))?>" <?=$canAdminLlm?'':'disabled'?>></div>
        <div class="col-md-4"><label class="form-label">Limite por requisição R$</label><input type="number" step="0.01" name="per_request_cost_limit" class="form-control" value="<?=e((string)($policy['per_request_cost_limit'] ?? 1))?>" <?=$canAdminLlm?'':'disabled'?>></div>
        <div class="col-md-4"><label class="form-label">Máx. caracteres de contexto</label><input type="number" name="max_input_chars" class="form-control" value="<?=e((string)($policy['max_input_chars'] ?? 12000))?>" <?=$canAdminLlm?'':'disabled'?>></div>
        <div class="col-md-4"><label class="form-label">Retenção auditoria dias</label><input type="number" name="audit_retention_days" class="form-control" value="<?=e((string)($policy['audit_retention_days'] ?? 180))?>" <?=$canAdminLlm?'':'disabled'?>></div>
        <div class="col-12"><div class="row g-2">
          <?php
          $checks = [
            'enabled'=>'Habilitar LLM', 'allow_external_calls'=>'Permitir chamadas externas', 'prompt_injection_guard'=>'Prompt Injection Guard',
            'log_prompts'=>'Auditoria de prompts', 'redact_sensitive_data'=>'Mascarar dados sensíveis', 'context_minimization'=>'Contexto mínimo',
            'require_human_approval'=>'Exigir aprovação humana', 'allow_sensitive_context'=>'Permitir contexto sensível', 'allow_automatic_actions'=>'Permitir ações automáticas (bloqueado por segurança)'
          ];
          foreach($checks as $k=>$label): ?>
          <div class="col-md-4"><label class="form-check"><input class="form-check-input" type="checkbox" name="<?=$k?>" value="1" <?=!empty($policy[$k])?'checked':''?> <?=$canAdminLlm?'':'disabled'?>> <span class="form-check-label"><?=$label?></span></label></div>
          <?php endforeach; ?>
        </div></div>
        <div class="col-12">
          <button class="btn btn-primary" <?=$canAdminLlm?'':'disabled'?>>Salvar política</button>
          <?php if(!$canAdminLlm): ?><small class="text-muted ms-2">Somente admin/supervisor ou perfil autorizado administra LLM.</small><?php endif; ?>
        </div>
      </form>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card-soft mb-3">
      <h5><i class="bi bi-key"></i> API key no Token Vault</h5>
      <p class="small text-muted">A chave nunca é exibida depois de salva. Ela é criptografada no cofre e auditada com mascaramento.</p>
      <form method="post" class="row g-2">
        <?=Csrf::input()?>
        <input type="hidden" name="acao_llm" value="store_api_key">
        <div class="col-md-6"><label class="form-label">Provider</label><select name="provider" class="form-select" <?=$canAdminLlm?'':'disabled'?>><?php foreach($providers as $k=>$v): if($k==='none') continue; ?><option value="<?=$k?>" <?=($policy['provider']??'')===$k?'selected':''?>><?=$v?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label">Ambiente</label><select name="environment" class="form-select" <?=$canAdminLlm?'':'disabled'?>><?php foreach($ambientes as $k=>$v): ?><option value="<?=$k?>" <?=($policy['environment']??'homologacao')===$k?'selected':''?>><?=$v?></option><?php endforeach; ?></select></div>
        <div class="col-12"><label class="form-label">API key</label><input type="password" name="api_key" class="form-control" autocomplete="new-password" placeholder="Cole a chave somente no servidor seguro" <?=$canAdminLlm?'':'disabled'?>></div>
        <div class="col-12"><button class="btn btn-outline-primary" <?=$canAdminLlm?'':'disabled'?>>Salvar no cofre</button></div>
      </form>
      <hr>
      <div class="small">
        <b>Uso hoje:</b> R$ <?=number_format((float)($usage['today'] ?? 0),2,',','.')?><br>
        <b>Uso mês:</b> R$ <?=number_format((float)($usage['month'] ?? 0),2,',','.')?><br>
        <b>Requisições hoje:</b> <?=e((string)($usage['requests_today'] ?? 0))?>
      </div>
    </div>
    <div class="card-soft mb-3">
      <h5><i class="bi bi-list-check"></i> Aprovações pendentes/recentes</h5>
      <?php if(empty($approvals)): ?><p class="text-muted small mb-0">Nenhuma solicitação LLM registrada.</p><?php endif; ?>
      <?php foreach(($approvals ?? []) as $a): ?>
        <div class="border rounded p-2 mb-2">
          <div class="d-flex justify-content-between"><b><?=e($a['approval_uuid'] ?? ('#'.$a['id']))?></b><span class="badge bg-<?=($a['status']??'')==='pendente'?'warning':'secondary'?>"><?=e($a['status'] ?? '')?></span></div>
          <small class="text-muted">Risco <?=e($a['risk_level'] ?? '')?> · <?=e($a['environment'] ?? '')?> · Trace <?=e($a['trace_id'] ?? '')?></small>
          <?php if(($a['status'] ?? '') === 'pendente' && $canAdminLlm): ?>
          <form method="post" class="d-flex gap-2 mt-2 flex-wrap">
            <?=Csrf::input()?> <input type="hidden" name="acao_llm" value="approval_decision"><input type="hidden" name="approval_id" value="<?=e((string)$a['id'])?>">
            <input class="form-control form-control-sm" name="decision_reason" placeholder="Motivo da decisão" style="max-width:240px">
            <button name="decision" value="aprovado" class="btn btn-sm btn-success">Aprovar</button>
            <button name="decision" value="rejeitado" class="btn btn-sm btn-outline-danger">Rejeitar</button>
          </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="card-soft mb-3">
  <h5><i class="bi bi-bug"></i> Teste de prompt injection e custo</h5>
  <form method="post" class="d-flex flex-column gap-2">
    <?=Csrf::input()?>
    <input type="hidden" name="acao_llm" value="validate_prompt">
    <input name="prompt_key" class="form-control" value="teste_governanca" placeholder="Chave do prompt / caso de uso">
    <textarea name="prompt_teste" class="form-control" rows="5" placeholder="Cole um prompt, erro, log mascarado ou contexto para validar risco antes de usar com LLM..."></textarea>
    <div class="d-flex gap-2 flex-wrap">
      <button class="btn btn-primary" type="submit">Validar segurança</button>
      <button class="btn btn-outline-warning" name="acao_llm" value="create_approval" type="submit">Criar solicitação de aprovação</button>
    </div>
  </form>
</div>

<div class="card-soft mb-3 border-warning">
  <h5><i class="bi bi-lightning-charge"></i> Execução real em homologação (chamada externa)</h5>
  <p class="small text-muted mb-2">Faz <b>uma</b> chamada real ao provedor, apenas em <b>homologação</b>, e só com uma aprovação <b>aprovada</b> e não expirada. Cole exatamente o texto aprovado: se o hash não conferir, é bloqueado. Requer <code>enabled</code>, <code>allow_external_calls</code>, chave no cofre e custo dentro do limite.</p>
  <?php if(!$canAdminLlm): ?>
    <p class="small text-danger mb-0">Requer permissão de administração da Governança LLM.</p>
  <?php else: ?>
  <form method="post" class="d-flex flex-column gap-2">
    <?=Csrf::input()?>
    <input type="hidden" name="acao_llm" value="execute_homologacao">
    <input name="approval_id" type="number" min="1" class="form-control" placeholder="ID da aprovação aprovada" required>
    <textarea name="prompt_teste" class="form-control" rows="4" placeholder="Cole exatamente o prompt que foi aprovado..." required></textarea>
    <button class="btn btn-warning" type="submit" onclick="return confirm('Isto fará uma chamada REAL ao provedor em homologação. Continuar?');">Executar em homologação</button>
  </form>
  <?php endif; ?>
</div>

<?php if(!empty($resultadoExec)): ?>
<div class="alert alert-<?=!empty($resultadoExec['ok'])?'success':'danger'?>">
  <b>Execução real:</b> <?= !empty($resultadoExec['ok']) ? 'concluída' : 'não realizada' ?>
  <?php if(!empty($resultadoExec['blocked'])): ?><br><b>Bloqueios:</b> <?=e(implode(' · ', $resultadoExec['blocked']))?><?php endif; ?>
  <?php if(!empty($resultadoExec['erro']) && empty($resultadoExec['blocked'])): ?><br><b>Erro:</b> <?=e((string)$resultadoExec['erro'])?><?php endif; ?>
  <?php if(!empty($resultadoExec['text'])): ?><pre class="json-box mt-2"><?=e((string)$resultadoExec['text'])?></pre><?php endif; ?>
  <div class="small text-muted mt-1">Tokens <?=e((string)($resultadoExec['usage']['input_tokens']??0))?> in / <?=e((string)($resultadoExec['usage']['output_tokens']??0))?> out · custo ~$<?=e(number_format((float)($resultadoExec['cost']??0),4))?> · aprovação <?=e((string)($resultadoExec['approval_uuid']??'—'))?></div>
</div>
<?php endif; ?>

<?php if($resultadoPrompt): ?>
<div class="alert alert-<?=$resultadoPrompt['allowed']?'success':'danger'?>">
  <b>Resultado:</b> risco <?=e($resultadoPrompt['risk_level'])?> — <?= $resultadoPrompt['allowed']?'permitido para simulação':'bloqueado para uso real' ?>
  <?php if(!empty($resultadoPrompt['requires_human_approval'])): ?><br><b>Aprovação humana:</b> obrigatória antes de qualquer chamada externa.<?php endif; ?>
  <pre class="json-box mt-2"><?=e(json_encode($resultadoPrompt, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?></pre>
</div>
<?php endif; ?>

<div class="alert alert-info">
  <b>Opinião técnica:</b> para grande porte, a IA deve ser assistente auditável. Ela pode explicar erros e sugerir ações, mas quem executa mudanças em Tiny, VSM, banco, estoque, fiscal e produção deve ser sempre o operador autorizado.
</div>
<?php require __DIR__.'/layout_bottom.php'; ?>
