<?php
class ErrorCatalog {
  public static function explain(string $code, string $message=''): array {
    $map = [
      'TINY_TOKEN_MISSING' => ['causa'=>'Token do Tiny V2 não foi configurado.', 'acao'=>'Acesse Configurações e informe o token Tiny. Depois reprocesse a fila.', 'gravidade'=>'alta'],
      'TINY_CURL_ERROR' => ['causa'=>'Falha de comunicação com a API Tiny.', 'acao'=>'Verifique internet, URL do Tiny, SSL/cURL do XAMPP e tente novamente.', 'gravidade'=>'alta'],
      'TINY_INVALID_JSON' => ['causa'=>'Tiny retornou resposta não JSON ou inesperada.', 'acao'=>'Abra o detalhe da auditoria e confira o retorno bruto da API.', 'gravidade'=>'media'],
      'VSM_PAYLOAD_INVALID' => ['causa'=>'Payload recebido da VSM está vazio ou fora do formato esperado.', 'acao'=>'Compare o JSON recebido com o contrato da VSM e ajuste o mapeamento.', 'gravidade'=>'alta'],
      'DB_ERROR' => ['causa'=>'Erro ao gravar ou consultar o banco MySQL.', 'acao'=>'Confira se o banco foi instalado, tabelas existem e usuário do MySQL tem permissão.', 'gravidade'=>'alta'],
      'QUEUE_PROCESS_ERROR' => ['causa'=>'Erro durante processamento da fila.', 'acao'=>'Abra o item da fila, veja payload, retorno e stack trace da auditoria.', 'gravidade'=>'alta'],
      'PEDIDO_STATUS_ERRO' => ['causa'=>'Uma etapa do ciclo de vida do pedido (validação de NF-e/XML ou envio ao Tiny) terminou em erro.', 'acao'=>'Abra o Ciclo de Vida do Pedido pelo Trace ID, identifique a etapa que falhou e o detalhe da auditoria; corrija a causa (ex.: chave NF-e inválida) e reprocesse.', 'gravidade'=>'alta'],
      'AUTH_FAILED' => ['causa'=>'Tentativa de login com usuário ou senha inválidos.', 'acao'=>'Confira credenciais ou redefina usuário administrador.', 'gravidade'=>'baixa'],
      'UNHANDLED_EXCEPTION' => ['causa'=>'Erro não tratado no sistema.', 'acao'=>'Use o Trace ID para localizar o detalhe em Auditoria e verificar arquivo/linha.', 'gravidade'=>'alta'],
    ];
    $item = $map[$code] ?? ['causa'=>'Erro não catalogado.', 'acao'=>'Use o Trace ID e payload técnico para investigar.', 'gravidade'=>'media'];
    $item['codigo'] = $code;
    $item['mensagem_original'] = $message;
    return $item;
  }
}
