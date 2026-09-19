<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';

$checks=[];
$schema=hub_read('app/Services/SchemaMigrationService.php');
$quality=hub_read('app/Services/EnterpriseQualityGateService.php');
$version=hub_read('app/Services/SystemVersionService.php');
$vsm=hub_read('app/Controllers/VsmController.php');
$migrationController=hub_read('app/Controllers/MigrationController.php');
$workflow=hub_read('.github/workflows/hub-ci.yml');
$parity=hub_read('scripts/ci/mysql-module-parity-check.php');
$runtime=hub_read('scripts/ci/mysql-runtime-regression.php');
$modularRuntime=hub_read('scripts/ci/mysql-modular-runtime-regression.php');
$readme=hub_read('LEIA-ME-INSTALACAO-E-ATUALIZACAO.md');
$installer=hub_read('public/install.php');
$installAuthorization=hub_read('scripts/create-install-authorization.php');
$database=hub_read('app/Core/Database.php');
$enterpriseController=hub_read('app/Controllers/EnterpriseCoreController.php');
$autoRepair=hub_read('app/Services/DatabaseAutoRepairService.php');
$schemaBuilder=hub_read('scripts/ci/build-consolidated-schema.mjs');
$installProbe=hub_read('app/Services/InstallDatabaseProbe.php');

// Reauditoria 2026-09-14 (achado A-05): a árvore passou a ser R7 - o conteúdo mudou em relação
// à R5 e o artefato precisa de identidade própria. A data acompanha a release.
hub_check($checks,'Release R7 e data final estão declaradas',
    str_contains($version,"VERSION = 'V104.49.3'")
    && str_contains($version,"RELEASE = 'R7'")
    && str_contains($version,"RELEASE_DATE = '2026-09-17'") && str_contains($version,"BUILD = '20260917.1'"));

hub_check($checks,'Status do schema valida colunas por tipo, nulabilidade e default',
    str_contains($schema,'requiredColumnContracts')
    && str_contains($schema,'SHOW FULL COLUMNS')
    && str_contains($schema,'columnContractViolations'));

hub_check($checks,'Status valida composição e unicidade dos índices críticos',
    str_contains($schema,'requiredIndexContracts')
    && str_contains($schema,'indexMetadata')
    && str_contains($schema,'indexContractViolations')
    && str_contains($schema,"'uk_vsm_campo'=>['columns'=>['categoria','campo_vsm','campo_hub'],'unique'=>true]"));

hub_check($checks,'Enterprise Core repara todos os índices incluídos no contrato',
    str_contains($schema,"['schema_migrations','idx_schema_migrations_status'")
    && str_contains($schema,"['fila_integracao','idx_fila_locked'")
    && str_contains($schema,"['vsm_endpoints','idx_vsm_endpoints_categoria'")
    && str_contains($schema,"['vsm_endpoint_logs','idx_vsm_logs_criado'"));

hub_check($checks,'Quality Gate bloqueia qualquer contrato pendente e informa itens',
    str_contains($quality,"'status'=>\$schemaOk?'ok':'bloqueio'")
    && str_contains($quality,"(int)\$schema['ok'] === (int)\$schema['total']")
    && str_contains($quality,"'schema_pending'=>\$schemaPending"));

$legacyUpdaterRemoved=!str_contains($vsm,'atualizarV49');
$legacyUpdaterFailClosed=str_contains($vsm,'SchemaMigrationService::applyEnterpriseCore(false)')
    && str_contains($vsm,"empty(\$resultado['errors'])")
    && str_contains($vsm,"empty(\$resultado['aborted'])")
    && !str_contains($vsm,"Database::recordMigration('v49_vsm_seguro_modular'");
hub_check($checks,'Updater VSM não registra falso sucesso e rotas históricas permanecem bloqueadas',
    ($legacyUpdaterRemoved||$legacyUpdaterFailClosed)
    && str_contains($migrationController,"str_starts_with(\$page, 'atualizar-v')")
    && str_contains($migrationController,'legacyBlocked'));

hub_check($checks,'Paridade cobre módulos oficiais, contratos de coluna e definição de índices',
    str_contains($parity,"['core','pedidos','produtos','estoque','fiscal','fila','observabilidade','backups']")
    && str_contains($parity,'Contrato divergente em ')
    && str_contains($parity,'Índice divergente em '));

hub_check($checks,'CI executa lint, Enterprise, paridade, MySQL 8 e MariaDB',
    str_contains($workflow,'bash scripts/ci/php-lint.sh')
    && str_contains($workflow,'bash scripts/ci/enterprise-tests.sh')
    && str_contains($workflow,'php scripts/ci/mysql-module-parity-check.php')
    && str_contains($workflow,'mysql:8.0')
    && str_contains($workflow,'mariadb:11.4'));

hub_check($checks,'Consolidado é gerado dos módulos e conferido na CI',
    str_contains($schemaBuilder,"const modules = ['core', 'pedidos', 'produtos', 'estoque', 'fiscal', 'fila', 'observabilidade', 'backups']")
    && str_contains($workflow,'node scripts/ci/build-consolidated-schema.mjs --check'));

hub_check($checks,'Regressão real testa schema consolidado e modular sem banco placeholder',
    str_contains($runtime,"renderInstallSql(\$schema,\$db)")
    && str_contains($runtime,'assertColumnContract')
    && str_contains($runtime,'assertIndexContract')
    && str_contains($modularRuntime,"['core','pedidos','produtos','estoque','fiscal','fila','observabilidade','backups']")
    && str_contains($modularRuntime,"\$database=\$base.'_'.\$module")
    && str_contains($modularRuntime,'withoutAdminSeed')
    && str_contains($modularRuntime,'CI Sentinel')
    && str_contains($modularRuntime,"'vsm_campos_mapeamento','is_template'")
    && str_contains($workflow,'php scripts/ci/mysql-modular-runtime-regression.php'));

hub_check($checks,'README documenta R5, Quality Gate e dupla engine',
    str_contains($readme,'V104.49.3-R5')
    && str_contains($readme,'MySQL 8')
    && str_contains($readme,'MariaDB'));

hub_check($checks,'Instalador exige autorização CLI de uso único, CSRF e HTTPS público',
    str_contains($installAuthorization,"'token_hash' => hash('sha256', \$token)")
    && str_contains($installer,'claim_install_authorization')
    && str_contains($installer,'valid_csrf()')
    && str_contains($installer,'$httpsBlocked'));

hub_check($checks,'Instalador valida identificadores de conexão e publica artefatos com rollback',
    str_contains($installer,'function validate_db_host')
    && str_contains($installer,'function validate_db_name')
    && str_contains($installer,'function publish_install_artifacts')
    && str_contains($installer,'array_reverse(array_keys($contents))'));

$preflightPos=strrpos($installer,'assert_fresh_install_targets(');
$ddlProbePos=strrpos($installer,'test_database_ddl_access(');
$moduleDdlPos=$ddlProbePos===false?false:strpos($installer,'foreach($modules as $m){',$ddlProbePos);
hub_check($checks,'Preflight precede toda sonda/tabela modular e a permissão usa objeto aleatório comprovado',
    $preflightPos!==false&&$ddlProbePos!==false&&$moduleDdlPos!==false
    && $preflightPos<$ddlProbePos&&$ddlProbePos<$moduleDdlPos
    && str_contains($installProbe,'$created=true')
    && str_contains($installProbe,'ALTER TABLE')
    && str_contains($installProbe,'CREATE INDEX')
    && str_contains($installProbe,'if($created)')
    && str_contains($installProbe,"->exec('DROP TABLE '")
    && str_contains($installProbe,'bin2hex(random_bytes(12))')
    && !str_contains($installer,'DROP TABLE `_hub_install_permission_test`'));

hub_check($checks,'Instalador recusa base existente e nunca atualiza conta administrativa',
    str_contains($installer,'assert_fresh_install_targets')
    && str_contains($installer,"SELECT COUNT(*) FROM usuarios")
    && !str_contains($installer,'UPDATE usuarios SET'));

hub_check($checks,'Gate final inspeciona o contrato integral antes de publicar config e lock',
    str_contains($installer,'post_install_schema_gate')
    && str_contains($installer,'COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT')
    && str_contains($installer,'TABLE_NAME,ENGINE,TABLE_COLLATION')
    && str_contains($installer,'foreach ($requirements[\'columns\'] as $column=>$expected)')
    && str_contains($installer,"!contrato'")
    && strpos($installer,'$schemaGate = post_install_schema_gate') < strrpos($installer,'publish_install_artifacts($configPath,$root,$cfg)'));

hub_check($checks,'Proxy, Base URL e limites administrativos falham de forma fechada',
    str_contains($installer,'HUB_INSTALL_TRUSTED_PROXIES')
    && str_contains($installer,'function validate_base_url')
    && str_contains($installer,'function validate_admin_name')
    && str_contains($installer,'rawurldecode($path)')
    && str_contains($installer,"preg_match('/[\\x00-\\x1F\\x7F]/',\$decodedPath)")
    && str_contains($installer,'4096'));

hub_check($checks,'Falhas de permissão/sintaxe ao criar índice não são mascaradas como duplicidade',
    str_contains($database,'$driverCode===1061')
    && !str_contains($database,"getCode() === '42000'"));

hub_check($checks,'Aplicação do Enterprise Core rejeita método diferente de POST',
    str_contains($enterpriseController,"RequestContext::method() !== 'POST'")
    && str_contains($enterpriseController,"header('Allow: POST')")
    && str_contains($enterpriseController,'Csrf::validate()'));

hub_check($checks,'AutoRepair só conclui após validação estrita do SchemaMigrationService',
    str_contains($autoRepair,'verifyStrictEnterpriseContract')
    && str_contains($autoRepair,'SchemaMigrationService::status()')
    && str_contains($autoRepair,"'AutoRepair contrato estrito'"));

// Achado I-21 (2026-09-15): NENHUM portao executava os scripts de scripts/. `php -l` passava, os
// 13 portoes passavam, e diagnose-http-500.php morria com `Class "Database" not found` na primeira
// vez que alguem o rodava. O portao 14 (cli-scripts-smoke.php) executa. Ele precisa aparecer DUAS
// vezes no workflow: no job estatico e, principalmente, DEPOIS do provisionamento do job e2e -
// medido numa copia com o defeito reposto, so a execucao COM config/config.php e banco o pega;
// sem config o ramo de banco nem e alcancado e o passo fica verde. Uma ocorrencia so nao basta.
$ocorrenciasSmoke = substr_count($workflow, 'php scripts/ci/cli-scripts-smoke.php');
$posProvisionamento = strpos($workflow, 'php scripts/ci/provision-e2e-environment.php');
$posSmokeComBanco = strrpos($workflow, 'php scripts/ci/cli-scripts-smoke.php');
hub_check($checks,'O portao que EXECUTA os scripts de CLI roda nos dois jobs, um deles com banco',
    $workflow !== ''
    && $ocorrenciasSmoke === 2
    && $posProvisionamento !== false
    && $posSmokeComBanco !== false
    && $posSmokeComBanco > $posProvisionamento);

hub_finish($checks);
