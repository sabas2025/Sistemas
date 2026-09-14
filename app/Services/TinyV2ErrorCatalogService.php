<?php
class TinyV2ErrorCatalogService {
  public static function all(): array {
    return [
      ['codigo'=>'TINY_V2_TOKEN_INVALID','titulo'=>'Token Tiny V2 inválido','acao'=>'Revise o token no Tiny e atualize em Configurações.','retry'=>'não repetir até corrigir credencial'],
      ['codigo'=>'TINY_V2_RATE_LIMIT','titulo'=>'Limite de requisições Tiny V2','acao'=>'Aguardar janela de liberação e reduzir frequência do worker.','retry'=>'5, 15 e 30 minutos'],
      ['codigo'=>'TINY_V2_PRODUTO_NAO_ENCONTRADO','titulo'=>'Produto não encontrado','acao'=>'Vincular SKU manualmente ou criar produto no Tiny.','retry'=>'manual'],
      ['codigo'=>'TINY_V2_TIMEOUT','titulo'=>'Timeout Tiny V2','acao'=>'Verificar internet, DNS e disponibilidade Tiny.','retry'=>'1, 5 e 15 minutos'],
      ['codigo'=>'TINY_V2_JSON_INVALIDO','titulo'=>'Resposta inválida do Tiny','acao'=>'Abrir auditoria e conferir response body.','retry'=>'manual após análise'],
    ];
  }
}
