<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';

$checks=[];
$db=hub_read('app/Core/Database.php');
$migration=hub_read('app/Services/SchemaMigrationService.php');
$view=hub_read('views/enterprise_core.php');

hub_check($checks,'Detecção de tabela consulta a própria tabela antes de INFORMATION_SCHEMA',
    str_contains($db, "SELECT 1 FROM `'.\$table.'` LIMIT 0"));
hub_check($checks,'INFORMATION_SCHEMA usa nome explícito do banco selecionado',
    str_contains($db, 'TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1') && str_contains($db, '[$database, $table]'));
hub_check($checks,'SHOW TABLES não depende de placeholder em hospedagem compartilhada',
    str_contains($db, "SHOW TABLES LIKE '.\$quoted") && !str_contains($db, "prepare('SHOW TABLES LIKE ?')"));
hub_check($checks,'Detecção de coluna possui consulta direta e fallback robusto',
    str_contains($db, "SELECT `'.\$column.'` FROM `'.\$table.'` LIMIT 0") && str_contains($db, 'INFORMATION_SCHEMA.COLUMNS'));
hub_check($checks,'Criação de tabela é verificada na mesma conexão PDO',
    str_contains($db, 'CREATE TABLE foi executado, mas a tabela não ficou acessível na mesma conexão'));
hub_check($checks,'Enterprise Core registra banco real, banco configurado e usuário MySQL',
    str_contains($migration, "'database_diagnostics' => []") && str_contains($migration, 'Database::connectionDiagnostics($corePdo)'));
hub_check($checks,'Tela Enterprise Core exibe diagnóstico de conexão sem segredo',
    str_contains($view, 'Banco selecionado:') && str_contains($view, 'Banco configurado:') && str_contains($view, 'Usuário MySQL:'));
hub_check($checks,'Cache de schema completo é limpo após criação',
    str_contains($migration, 'Database::clearSchemaMetadataCache();'));
hub_check($checks,'Versão da recuperação mantém R3 ou avança para R4',
    str_contains($migration, 'v104_48_1_schema_detection_r3') || str_contains($migration, 'v104_49_2_enterprise_map_recovery_r4'));

hub_finish($checks);
