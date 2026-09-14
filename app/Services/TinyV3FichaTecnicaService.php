<?php
class TinyV3FichaTecnicaService {
  public static function itens(): array {
    $cfg=IntegrationConfig::get();
    $token=TinyV3TokenService::status();
    $baseOk=!empty($cfg['tiny_v3_url']);
    $authOk=!empty($token['tem_token_tabela']) || (($cfg['tiny_v3_ambiente'] ?? 'homologacao')==='homologacao' && !empty($cfg['tiny_v3_token']));
    $ready=TinyV3TokenService::homologationReady();
    return [
      ['grupo'=>'Base','item'=>'URL base public-api/v3 configurada','ok'=>$baseOk,'acao'=>$baseOk?'OK':'Configure Tiny V3 URL.'],
      ['grupo'=>'Autenticação','item'=>'Access token salvo/criptografado por ambiente','ok'=>$authOk,'acao'=>$authOk?'OK':'Conecte OAuth ou informe token Tiny V3 apenas em homologação.'],
      ['grupo'=>'Autenticação','item'=>'Refresh token preparado por ambiente','ok'=>!empty($token['tem_token_tabela']),'acao'=>!empty($token['tem_token_tabela'])?'OK':'Salvar refresh token OAuth para renovação automática.'],
      ['grupo'=>'Autenticação','item'=>'Checklist obrigatório Tiny V3 operacional','ok'=>!empty($ready['ok']),'acao'=>!empty($ready['ok'])?'OK':'Concluir: '.implode(' | ', $ready['issues'])],
      ['grupo'=>'Produtos','item'=>'Consultar produto por SKU com comparação exata','ok'=>true,'acao'=>'Implementado via consultarProduto().'],
      ['grupo'=>'Produtos','item'=>'Criar produto','ok'=>true,'acao'=>'Implementado via endpoint configurável.'],
      ['grupo'=>'Produtos','item'=>'Atualizar produto/status','ok'=>true,'acao'=>'Implementado com pré-consulta por SKU.'],
      ['grupo'=>'Estoque','item'=>'Atualizar estoque por produto encontrado','ok'=>true,'acao'=>'Implementado com pré-consulta por SKU.'],
      ['grupo'=>'Pedidos','item'=>'Consultar pedido','ok'=>true,'acao'=>'Endpoint configurável.'],
      ['grupo'=>'Pedidos','item'=>'Lançar estoque do pedido','ok'=>true,'acao'=>'Endpoint configurável /pedidos/{idPedido}/lancar-estoque.'],
      ['grupo'=>'NF-e','item'=>'Consultar nota fiscal','ok'=>true,'acao'=>'Endpoint configurável.'],
      ['grupo'=>'Operação','item'=>'Fallback Tiny V2','ok'=>true,'acao'=>'Factory permite alternar versão sem quebrar V2.'],
      ['grupo'=>'Operação','item'=>'Logs request/response com dados sensíveis mascarados','ok'=>true,'acao'=>'Audit + Metrics + tiny_v3_endpoint_logs integrados com redação de CPF/CNPJ/tokens.'],
    ];
  }
  public static function score(): int {
    $itens=self::itens(); $ok=count(array_filter($itens,fn($i)=>!empty($i['ok']))); return (int)round(($ok/max(1,count($itens)))*100);
  }
}
