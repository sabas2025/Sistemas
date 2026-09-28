# V90 - Campo Estoque de Homologação Tiny V2/V3

## Objetivo
Corrigir as telas `Tiny V2 - Homologação` e `Tiny V3 - Homologação` para incluir um campo explícito de **Estoque de Homologação**, permitindo finalizar a etapa de estoque com validação mais clara.

## Alterações aplicadas

### Telas
- `views/tiny_v2_homologacao.php`
- `views/tiny_v3_homologacao.php`

Incluído no formulário "Executar Homologação":
- Campo `Estoque de Homologação` (`estoque_homologacao`)
- Texto explicativo informando que esse saldo é usado para finalizar a homologação de estoque.
- Alerta explicando que o sistema compara o valor informado com o saldo retornado pelo Tiny e pela VSM.

### Serviços
- `app/Services/TinyV2HomologationService.php`
- `app/Services/TinyV3HomologationService.php`

Agora a etapa de estoque considera:
- SKU informado;
- saldo retornado pelo Tiny;
- saldo retornado pela VSM;
- saldo esperado informado no campo `Estoque de Homologação`.

A etapa de estoque só aprova quando:
- Tiny responde estoque;
- VSM responde estoque;
- Tiny e VSM conferem entre si;
- o saldo informado no campo Estoque de Homologação confere com Tiny e VSM.

### Resultado JSON
A evidência agora inclui:
- `estoque_homologacao`
- `estoque_esperado`
- `diferenca_esperado_tiny`
- `diferenca_esperado_vsm`

## Regras novas

Se o campo Estoque de Homologação estiver vazio:
- a etapa de estoque fica como falha/pendente;
- o sistema recomenda preencher o saldo esperado;
- a homologação completa não finaliza em 100%.

Se houver diferença:
- o sistema mostra alerta;
- a homologação parcial pode registrar evidência;
- produção não deve ser liberada até reconciliar Tiny x VSM.

## Validação
- PHP validado com `php -l` nos arquivos alterados.
- Nenhuma alteração de banco obrigatória.
