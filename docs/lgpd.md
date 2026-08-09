# Tratamento de dados pessoais

## O que o sistema guarda

Nome e e-mail de quem cria conta. Nome de exibicao dos competidores. Placares e classificacoes.
O sistema nao pede telefone, documento nem data de nascimento.

## Base legal

Organizador: execucao de contrato, ele se cadastra por vontade propria para usar a ferramenta.
Competidor inscrito por terceiro: legitimo interesse na organizacao do torneio, com aviso na tela de inscricao
informando que o nome aparece no chaveamento, na classificacao e no ranking.

## Exclusao a pedido do titular

```
C:\xampp\php\php.exe admin/anonimizar.php email@do.titular
```

A rotina (`Auth::anonimizarPorEmail`, chamada por este script) faz duas coisas, na mesma transacao:

1. Desfaz o vinculo de toda inscricao desse titular com a conta (`inscricoes.jogador_id` vira nulo em cada
   campeonato em que ele jogou). E esse vinculo, nao o nome de exibicao, que faz o ranking acumulado somar o
   titular entre eventos - uma inscricao sem `jogador_id` simplesmente para de entrar naquela soma.
2. Troca nome, e-mail, senha e foto da conta por um identificador anonimo e desativa a conta.

O nome de exibicao gravado em cada campeonato na hora da inscricao NAO muda: a partir da exclusao ele passa a
ser so o historico do proprio evento, o mesmo status que um convidado sem conta sempre teve. Os placares
tambem ficam, porque deixam de ter qualquer ligacao com uma conta identificavel. Resultado pratico: um
campeonato ja encerrado continua com a classificacao e os games de sempre, mas o titular some do ranking
acumulado e da propria lista de contas ativas.

Nenhuma linha e apagada em lugar nenhum, em nenhum dos dois passos - so atualizada.

## Retencao

Dados de campeonato ficam enquanto o organizador mantiver a conta ativa.
Nao ha compartilhamento com terceiros nem uso para publicidade.

## Pendencia da etapa 2

Nada. A politica de privacidade passou a ser obrigatoria nesta etapa, nao na etapa 2, porque o modelo de
negocio tem finalidade publicitaria e a pessoa precisa poder ler onde seu dado vai antes de aceitar. Entrou
na tarefa 10.
