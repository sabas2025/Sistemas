<?php
$root = dirname(__DIR__,2);
$sql = file_get_contents($root.'/database/install_final_current.sql');
if ($sql === false) { fwrite(STDERR,"install_final_current.sql ausente\n"); exit(1); }
preg_match_all('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`?([a-zA-Z0-9_]+)`?/i', $sql, $m);
$tables = $m[1] ?? [];
$dups = array_diff_assoc($tables, array_unique($tables));
if ($dups) { fwrite(STDERR,"Tabelas duplicadas: ".implode(',',array_unique($dups))."\n"); exit(1); }
$inventoryPath=$root.'/database/schema_inventory_current.json';
$inventory=is_file($inventoryPath)?json_decode((string)file_get_contents($inventoryPath),true):null;
if(!is_array($inventory)){fwrite(STDERR,"schema_inventory_current.json ausente ou inválido\n");exit(1);}
$actual=array_values(array_unique(array_map('strtolower',$tables)));sort($actual);
$declared=array_values(array_unique(array_map('strtolower',(array)($inventory['tables']??[]))));sort($declared);
if($actual!==$declared||(int)($inventory['total_tables']??0)!==count($tables)||(int)($inventory['unique_tables']??0)!==count($actual)){
  fwrite(STDERR,"Inventário JSON divergente do install_final_current.sql\n");exit(1);
}
echo "SQL inventory OK: ".count($tables)." tables\n";
