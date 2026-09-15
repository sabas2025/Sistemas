<?php
class JsonLogger {
  public static function write(string $canal, string $nivel, string $mensagem, array $contexto=[]): void {
    $dir = __DIR__.'/../../storage/logs'; if(!is_dir($dir)) @mkdir($dir,0775,true);
    $linha = json_encode(['ts'=>date('c'),'trace_id'=>RequestContext::id(),'canal'=>$canal,'nivel'=>$nivel,'mensagem'=>$mensagem,'contexto'=>$contexto], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    @file_put_contents($dir.'/'.$canal.'.jsonl', $linha.PHP_EOL, FILE_APPEND);
  }
}
