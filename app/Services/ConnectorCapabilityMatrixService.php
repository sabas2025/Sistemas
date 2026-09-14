<?php
/** V104.18 - Matriz contratual/técnica de conectores. */
class ConnectorCapabilityMatrixService {
  public static function matrix(): array {
    $rows=[];
    foreach(ConnectorRegistryService::all() as $connector){
      $rows[]=[
        'codigo'=>$connector->code(),
        'nome'=>$connector->name(),
        'categoria'=>$connector->category(),
        'status'=>$connector->status(),
        'capabilities'=>$connector->capabilities(),
        'requirements'=>$connector->requirements(),
        'operations'=>method_exists($connector,'operations') ? $connector->operations() : [],
        'risks'=>method_exists($connector,'risks') ? $connector->risks() : [],
        'health'=>$connector->health(),
      ];
    }
    return $rows;
  }

  public static function summary(): array {
    $matrix=self::matrix(); $byStatus=[]; $byCategory=[];
    foreach($matrix as $row){ $byStatus[$row['status']] = ($byStatus[$row['status']] ?? 0)+1; $byCategory[$row['categoria']] = ($byCategory[$row['categoria']] ?? 0)+1; }
    return ['total'=>count($matrix),'status'=>$byStatus,'category'=>$byCategory];
  }
}
