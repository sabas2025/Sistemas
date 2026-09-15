<?php
declare(strict_types=1);
require __DIR__.'/_helpers.php';
require_once hub_root().'/app/Core/Database.php';

final class SchemaProbeStatement extends PDOStatement {
    private array $rows;
    private int $idx=0;
    public function __construct(array $rows=[]){$this->rows=$rows;}
    public function execute(?array $params=null): bool { return true; }
    public function fetchColumn(int $column=0): mixed {
        if (!isset($this->rows[$this->idx])) return false;
        $row=$this->rows[$this->idx++];
        if (is_array($row)) return array_values($row)[$column] ?? false;
        return $row;
    }
    public function fetch(int $mode=PDO::FETCH_DEFAULT, int $cursorOrientation=PDO::FETCH_ORI_NEXT, int $cursorOffset=0): mixed {
        if (!isset($this->rows[$this->idx])) return false;
        return $this->rows[$this->idx++];
    }
}

final class SchemaProbePDO extends PDO {
    public array $tables=[];
    public array $columns=[];
    public string $database='ctbatop1_loja02';
    public function __construct(){}
    public function query(string $query, ?int $fetchMode=null, mixed ...$fetchModeArgs): PDOStatement|false {
        if (preg_match('/^SELECT DATABASE\(\)$/i',$query)) return new SchemaProbeStatement([$this->database]);
        if (str_contains($query,'CURRENT_USER() AS current_user')) return new SchemaProbeStatement([[
            'db'=>$this->database,'current_user'=>'ctbatop1_loja02@localhost','session_user'=>'ctbatop1_loja02@localhost','server_version'=>'8.0-test'
        ]]);
        if (preg_match('/^SELECT 1 FROM `([a-zA-Z0-9_]+)` LIMIT 0$/i',$query,$m)) {
            if (!in_array($m[1],$this->tables,true)) throw new PDOException("Table doesn't exist");
            return new SchemaProbeStatement([]);
        }
        if (preg_match('/^SELECT `([a-zA-Z0-9_]+)` FROM `([a-zA-Z0-9_]+)` LIMIT 0$/i',$query,$m)) {
            if (!in_array($m[2],$this->tables,true) || !in_array($m[1],$this->columns[$m[2]] ?? [],true)) throw new PDOException('Unknown column');
            return new SchemaProbeStatement([]);
        }
        if (preg_match("/^SHOW TABLES LIKE '([^']+)'$/i",$query,$m)) return new SchemaProbeStatement(in_array($m[1],$this->tables,true)?[[$m[1]]]:[]);
        if (preg_match("/^SHOW COLUMNS FROM `([a-zA-Z0-9_]+)` LIKE '([^']+)'$/i",$query,$m)) return new SchemaProbeStatement(in_array($m[2],$this->columns[$m[1]] ?? [],true)?[['Field'=>$m[2]]]:[]);
        throw new PDOException('Unsupported query: '.$query);
    }
    public function prepare(string $query, array $options=[]): PDOStatement|false {
        // Simula INFORMATION_SCHEMA bloqueado pelo provedor.
        throw new PDOException('INFORMATION_SCHEMA restricted');
    }
    public function quote(string $string, int $type=PDO::PARAM_STR): string|false { return "'".str_replace("'","''",$string)."'"; }
    public function getAttribute(int $attribute): mixed { return $attribute===PDO::ATTR_DRIVER_NAME?'mysql':null; }
}

$checks=[];
$pdo=new SchemaProbePDO();
$pdo->tables=['vsm_endpoints'];
$pdo->columns=['vsm_endpoints'=>['id','chave']];
hub_check($checks,'Tabela existente é detectada mesmo com INFORMATION_SCHEMA bloqueado',Database::tableExistsOn($pdo,'vsm_endpoints'));
hub_check($checks,'Tabela ausente continua ausente',!Database::tableExistsOn($pdo,'vsm_endpoint_logs'));
hub_check($checks,'Coluna existente é detectada por consulta direta',Database::columnExistsOn($pdo,'vsm_endpoints','chave'));
hub_check($checks,'Coluna ausente não gera falso positivo',!Database::columnExistsOn($pdo,'vsm_endpoints','contract_verified'));
$diag=Database::connectionDiagnostics($pdo);
hub_check($checks,'Diagnóstico retorna banco selecionado',($diag['database']??'')==='ctbatop1_loja02');
hub_check($checks,'Diagnóstico retorna usuário MySQL sem senha',($diag['current_user']??'')==='ctbatop1_loja02@localhost' && !array_key_exists('password',$diag));
hub_finish($checks);
