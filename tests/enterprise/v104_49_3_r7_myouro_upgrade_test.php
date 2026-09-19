<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
require_once hub_root().'/app/Services/MyOuroGraphqlService.php';
require_once hub_root().'/app/Services/UpgradeSqlService.php';
$checks=[];
$body=json_encode(['data'=>['estoque_produto'=>['codigoLoja'=>1,'codigoProduto'=>123,'quantidadeEstoque'=>10,'nomeProduto'=>'Teste']]]);
hub_check($checks,'Consulta estoque aceita resposta válida',MyOuroGraphqlService::decode(200,$body,123,1)['ok']);
hub_check($checks,'Resposta de outra loja rejeitada',!MyOuroGraphqlService::decode(200,$body,123,2)['ok']);
hub_check($checks,'Resposta de outro produto rejeitada',!MyOuroGraphqlService::decode(200,$body,124,1)['ok']);
hub_check($checks,'HTTP 200 com errors é falha',!MyOuroGraphqlService::decode(200,'{"errors":[{"message":"bad"}]}',123,1)['ok']);
hub_check($checks,'Dados parciais nunca são sucesso',MyOuroGraphqlService::decode(200,'{"data":{"estoque_produto":null},"errors":[{}]}',123,1)['codigo']==='MYOURO_PARTIAL_RESPONSE');
hub_check($checks,'JSON inválido rejeitado',!MyOuroGraphqlService::decode(200,'<html>',123,1)['ok']);
hub_check($checks,'JSON null rejeitado',!MyOuroGraphqlService::decode(200,'null',123,1)['ok']);
hub_check($checks,'Produto inexistente não é saldo zero',MyOuroGraphqlService::decode(200,'{"data":{"estoque_produto":null}}',123,1)['codigo']==='MYOURO_NOT_FOUND');
hub_check($checks,'401 distinguido',MyOuroGraphqlService::decode(401,'{}',123,1)['codigo']==='MYOURO_TOKEN_INVALID');
hub_check($checks,'403 distinguido',MyOuroGraphqlService::decode(403,'{}',123,1)['codigo']==='MYOURO_FORBIDDEN');
$e=MyOuroGraphqlService::envelope(123,1);
hub_check($checks,'Variáveis separadas do texto', $e['variables']===['produto'=>123,'loja'=>1] && !str_contains($e['query'],'123'));
hub_check($checks,'Operação fixa é query, não mutation',str_starts_with($e['query'],'query EstoquePorProdutoLoja('));
$blocked=0;
foreach ([0,-1,2147483648] as $id) { try { MyOuroGraphqlService::envelope($id,1); } catch (InvalidArgumentException $e) { $blocked++; } }
hub_check($checks,'Limites GraphQL Int respeitados',$blocked===3);
$sql="-- comentário com ;\nSET @sql='SELECT ''x;y'''; PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt; /* fim; */ SELECT 1;";
$parts=UpgradeSqlService::split($sql);
hub_check($checks,'Parser preserva literal com ponto e vírgula',count($parts)===5 && $parts[0]==="SET @sql='SELECT ''x;y'''");
hub_check($checks,'PREPARE/EXECUTE preservados',$parts[1]==='PREPARE stmt FROM @sql' && $parts[2]==='EXECUTE stmt');
$count=0;
foreach (['SELECT \'sem fim','/* sem fim','DELIMITER $$'] as $bad) { try { UpgradeSqlService::split($bad); } catch (RuntimeException $e) { $count++; } }
hub_check($checks,'SQL incompleto/DELIMITER não é executado',$count===3);
$needle="\$pdo->prepare('INSERT INTO fila_integracao";
hub_check($checks,'Teste literal detecta regressão proibida',str_contains('<?php '.$needle."(tipo) VALUES(?)');",$needle));
hub_check($checks,'Teste literal não acusa chamada com escopo',!str_contains("TenantScopeService::run('fila_integracao', 'INSERT INTO fila_integracao(tipo) VALUES(?)');",$needle));
hub_finish($checks);
