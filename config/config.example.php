<?php
return [
  'app_name' => 'Hub de Integração Enterprise',
  'installation_id' => '', // install.php gera identificador único; usado para namespacing de locks distribuídos
  'base_url' => '', // install.php detecta o subdiretório real; vazio evita caminho de exemplo incorreto
  'app_env' => 'production', // use 'local' somente no XAMPP/local
  'security' => [
    'block_search_engines' => true,
    'force_https' => true,
    'admin_ip_allowlist' => '', // opcional: IPs (ou faixas CIDR, ex.: 177.10.10.0/24) separados por vírgula; vazio = sem restrição. Aplicado em todo o painel (login incluso) por AdminIpAllowlistService; não afeta site comercial público nem webhooks Tiny/VSM.
    // Auditoria de capacidade 2026-09-14 (achado C-07): 'file' (padrão) guarda a sessão em disco
    // local e impede escalar horizontalmente — com um segundo servidor atrás de balanceador o
    // usuário cai no login a cada troca de nó. 'database' guarda na tabela `sessoes`, no MySQL que
    // o Hub já usa. Em nó único não há motivo para trocar.
    'session_driver' => 'file',
    'session_idle_timeout_seconds' => 1800,
    'session_absolute_timeout_seconds' => 28800,
    'max_login_attempts' => 5,
    'password_min_length' => 10,
    'login_lock_minutes' => 15,
    'protect_sensitive_files' => true,

    'trusted_proxies' => '', // IPs de proxy/CDN confiáveis separados por vírgula. Só estes podem informar X-Forwarded-For/CF-Connecting-IP.
    'canonical_host' => '', // P0-08: domínio(s) oficiais separados por vírgula (ex.: hub.suaempresa.com.br). Vazio = comportamento antigo (Host header cru). Configure em produção para bloquear Host header injection e fixar o host usado na URL de callback OAuth.
    'route_rate_limit_per_minute' => 90,
    'route_rate_limit_sensitive_per_minute' => 20,
    // Achado C-06: dias de retenção de item de fila JÁ CONCLUÍDO. Item pendente, em processamento,
    // em retry ou em falha definitiva nunca é apagado por idade — isso seria perder trabalho.
    'queue_done_retention_days' => 30,
    // Auditoria de capacidade 2026-09-14 (achado C-01): orçamento SEPARADO para os dez webhooks de
    // entrada Tiny/VSM. Eles são máquina-a-máquina e de alto volume: com 500 pedidos por minuto o
    // limite de rota sensível (20/min) recusaria a própria integração e depois bloquearia o IP do
    // Tiny. Dimensione com folga sobre o pico real; a defesa do canal é HMAC + allowlist de IP +
    // contador de tentativas pré-autenticação + guarda de replay, não este número.
    'webhook_route_rate_limit_per_minute' => 3000,
    'api_status_public_mode' => 'minimal', // minimal|private. Minimal não expõe fila, DLQ, Tiny/VSM ou circuit breaker sem login.
    'backup_signature_key' => '', // preenchido automaticamente pelo install.php; use chave forte em produção
    'fim_manifest_hmac_key' => '', // preenchido automaticamente pelo install.php; assina manifesto de integridade
    'csp_report_uri' => 'index.php?page=api/csp-report',
    'content_security_policy' => "default-src 'self'; base-uri 'self'; frame-ancestors 'self'; object-src 'none'; img-src 'self' data:; font-src 'self' data:; style-src 'self' 'nonce-__NONCE__'; script-src 'self' 'nonce-__NONCE__'; connect-src 'self'; form-action 'self'; report-uri __CSP_REPORT_URI__; upgrade-insecure-requests",
    'login_ip_rate_limit_per_hour' => 20,
    'login_ip_rate_limit_per_minute' => 5,

    'waf_panel_only' => true,
    'waf_panel_routes' => 'dashboard,dashboard-executivo,configuracoes,usuarios,central-tecnica,seguranca-extrema,security-center,security-soc,security-events,security-ips,security-circuit-breakers,security-assisted-test,security-code-audit,security-inventory,security-backup-trust,security-health,security-audit-signatures,security-hardening,security-ssl,security-user-audit,security-pentest,security-score,security-fim,backups,backup,backup-download,backup-importar,backup-restaurar,validar-banco,mapa-banco,health-modulos,enterprise-core,enterprise-core-aplicar,migracoes-seguras,migracao-aplicar,entrada-producao,producao-ready',
    'waf_never_inspect_routes' => 'api/tiny/*,api/vsm/*,api/webhook/tiny/*,api/webhook/vsm/*,webhook/tiny/*,webhook/vsm/*',
    'login_user_rate_limit_per_hour' => 10,
    'login_user_rate_limit_per_minute' => 3,
    'require_admin_2fa' => false, // seguro para primeiro acesso; ative no painel após cadastrar/validar 2FA dos administradores
    'two_factor_time_window_steps' => 2, // tolerância TOTP: 2 janelas de 30s antes/depois para evitar falso inválido por relógio levemente fora
    'session_fingerprint_use_ip_prefix' => true,
    'tiny_oauth_required' => true,
    'integration_retry_attempts' => 3,
    'integration_retry_base_ms' => 350,
    'queue_processing_timeout_minutes' => 30,
    'queue_lease_minutes' => 5, // Default; pode ser sobrescrito no painel.
    'queue_lease_minutes_by_type' => [
      'pedido_tiny_para_vsm' => 5,
      'baixa_estoque_vsm' => 5,
      'produto_vsm_para_tiny' => 10,
      'produto_vsm_atualizar_tiny' => 10,
      'produto_vsm_estoque_para_tiny' => 5,
      'produto_vsm_status_para_tiny' => 5,
    ],
    'database_validation_max_seconds' => 20,
    'database_repair_max_seconds' => 90,
    'database_validation_max_checks' => 320,
    'vsm_hmac_enabled' => false,
    'vsm_hmac_secret' => '',
    'vsm_allowed_ips' => '', // exemplo: 177.10.10.10,177.10.10.11
    'vsm_allowed_hosts' => 'conectavenda.homolog.vsm.com.br',
    'vsm_allowed_ports' => '443,80', // homologação por padrão; adicione host de produção somente após liberação oficial da VSM
    'tiny_allowed_hosts' => 'api.tiny.com.br,accounts.tiny.com.br',
    'tiny_allowed_ports' => '443',
    'tiny_allowed_ips' => '', // opcional: allowlist de IPs para webhooks Tiny
    'tiny_webhook_exigir_secret' => true,
    'require_hmac_in_production' => true,
    'webhook_hmac_window_seconds' => 300,
    // Assinatura v2 cobre versão, método HTTP, rota canônica, timestamp, nonce e hash do corpo:
    // sha256=hash_hmac('sha256', "v2:{METODO}:{ROTA}:{TIMESTAMP}:{NONCE}:{SHA256_DO_CORPO}", secret).
    // Melhoria 2 da seção 8 (relatório V104.49.3-R6): INSTALAÇÕES NOVAS nascem com true — o
    // install.php grava true —, porque numa base nova não existe integração legada e manter a v1
    // aceita seria criar dívida no dia zero. Este exemplo mantém false para descrever o caminho de
    // MIGRAÇÃO de uma instalação existente: deixe false enquanto a VSM ainda assinar no formato v1
    // (timestamp.nonce.corpo) e vire para true assim que o outro lado migrar. Enquanto estiver
    // false, cada webhook v1 aceito é registrado como evento de segurança e aparece como controle
    // degradado no painel e em api/status, para que a pendência não seja esquecida. Com true, além
    // de exigir v2, passa a recusar identificadores de pedido vindos por query string no retorno
    // XML, que não são cobertos por assinatura.
    'webhook_signature_require_v2' => false,
    'webhook_max_bytes' => 1048576,
    'webhook_rate_limit_per_minute' => 60,
    'integration_anti_replay_window_seconds' => 600,
    'integration_replay_hmac_key' => '', // obrigatório em produção/host público; install.php gera automaticamente
    'audit_daily_signature_enabled' => true,
    'audit_daily_signature_key' => '',
    'token_vault_hmac_key' => '',
    'encryption_key' => '' // preenchido automaticamente pelo install.php
  ],

  'enterprise' => [
    'schema_auto_apply' => false, // aplicar via Central Técnica > Enterprise Core ou SQL versionado
    'schema_runtime_repair_enabled' => false, // nunca executar DDL em requisições operacionais por padrão
    'queue_worker_batch_limit' => 50,
    'dlq_classification_enabled' => true,
    'observability_snapshot_enabled' => true,
    'quality_gate_min_score' => 85,
    'idempotency_strict_mode' => true,
    'idempotency_failure_policy' => 'dlq', // block|retry|dlq|allow; produção deve usar dlq ou block
    'queue_skip_locked_enabled' => true,
    'queue_retry_profile' => 'enterprise',
    'queue_worker_graceful_stop_seconds' => 300,
    'futuristic_ui_enabled' => true,
    'futuristic_ui_density' => 'comfortable',
  ],
  'llm' => [
    // V104.33: IA/LLM permanece desativada por padrão. Ative somente após configurar
    // cofre de chave, custos, auditoria, aprovação humana e homologação.
    'enabled' => false,
    'environment' => 'homologacao', // homologacao|producao
    'provider' => 'none', // none|openai|anthropic|gemini|deepseek|qwen|llama_local|custom
    'model' => '',
    'max_tokens' => 2048,
    'temperature' => 0.20,
    'allow_external_calls' => false,
    'api_key_storage' => 'token_vault',
    'allowed_roles' => 'admin,supervisor',
    'prompt_injection_guard' => true,
    'log_prompts' => true,
    'redact_sensitive_data' => true,
    'context_minimization' => true,
    'require_human_approval' => true,
    'allow_automatic_actions' => false,
    'allow_sensitive_context' => false,
    'monthly_cost_limit' => 100.00,
    'daily_cost_limit' => 10.00,
    'per_request_cost_limit' => 1.00,
    'max_input_chars' => 12000,
    'audit_retention_days' => 180,
  ],
  'db_storage_mode' => 'single', // single recomendado em hospedagem compartilhada; modular somente em VPS com bancos separados
  'db_modular_strict' => false, // true somente se TODOS os bancos modulares existem e têm permissão CREATE/ALTER
  'db_single_database_rescue' => true, // se config modular apontar para banco vazio, procura/cria tabelas no banco core
  'db' => [
    'host' => '127.0.0.1',
    'name' => 'hub_vsm_tiny_core',
    'user' => 'root',
    'pass' => '',
    'charset' => 'utf8mb4'
  ],
  // V104.13: em modo single todos os módulos apontam para o mesmo banco principal.
  // Em VPS com bancos separados, altere db_storage_mode para 'modular' e configure cada name abaixo.
  'db_modules' => [
    'core' => ['name' => 'hub_vsm_tiny_core'],
    'pedidos' => ['name' => 'hub_vsm_tiny_core'],
    'produtos' => ['name' => 'hub_vsm_tiny_core'],
    'estoque' => ['name' => 'hub_vsm_tiny_core'],
    'fiscal' => ['name' => 'hub_vsm_tiny_core'],
    'fila' => ['name' => 'hub_vsm_tiny_core'],
    'observabilidade' => ['name' => 'hub_vsm_tiny_core'],
    'backups' => ['name' => 'hub_vsm_tiny_core'],
  ],
  'tiny' => [
    'version' => 'v2',
    'v2_url' => 'https://api.tiny.com.br/api2',
    'v3_url' => 'https://api.tiny.com.br/public-api/v3'
  ],
  'commercial' => [
    'license_mode' => 'monitor', // off|monitor|enforce. Use enforce quando vender como SaaS/produção comercial.
    'license_hmac_key' => '', // preencha com chave forte para assinar licenças comerciais; se vazio usa backup_signature_key
    'allow_unlicensed_internal_use' => true,
    'tenant_scope_required' => false, // Exige empresa selecionada na sessão para abrir rota operacional. O isolamento de dados em si NÃO depende desta flag: ele é aplicado pelo TenantScopeService desde a R6. Ligar isto numa instalação de empresa única só atrapalha, porque nada carimba a empresa na sessão de quem não tem empresa atribuída.
    'public_landing_noindex' => true,
  ],
  'vsm' => [
    'url' => 'https://conectavenda.homolog.vsm.com.br'
  ]
];
