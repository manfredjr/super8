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

A rotina (`Auth::anonimizarPorEmail`, chamada por este script) faz, na mesma transacao:

1. Troca o nome de exibicao de toda inscricao desse titular (em cada campeonato em que ele jogou) por um
   identificador anonimo. E este campo - `inscricoes.nome_exibicao`, nao `users.nome` - que aparece de verdade
   para qualquer competidor, no chaveamento e na classificacao do evento; `users.nome` nunca chega a uma tela
   que o competidor veja. E o que o termo de uso (secao 6) e a politica de privacidade prometem: "seu nome e
   substituido por um identificador anonimo".
2. Desfaz o vinculo de toda inscricao desse titular com a conta (`inscricoes.jogador_id` vira nulo). E esse
   vinculo, nao o nome, que faz o ranking acumulado somar o titular entre eventos - uma inscricao sem
   `jogador_id` simplesmente para de entrar naquela soma. Acontece DEPOIS do passo 1 de proposito: e o
   `jogador_id` que acha as linhas certas para renomear, e apaga-lo primeiro deixaria o passo 1 sem nada para
   achar.
3. Limpa a linha de `tentativas_login` desse e-mail, se houver. Sem isso o e-mail do titular sobrevive em texto
   puro numa tabela que a exclusao deveria alcancar tambem.
4. Troca nome, e-mail, senha e foto da conta por um identificador anonimo e desativa a conta.

Os placares ficam exatamente como estavam, porque `partidas` aponta para o id da inscricao, nunca para
`jogador_id` nem para o nome - o resultado das partidas nunca teve qualquer um dos dois como chave. Resultado
pratico: um campeonato ja encerrado continua com os games de sempre, mas o nome do titular some da tela do
proprio evento e a pessoa some do ranking acumulado e da lista de contas ativas.

Nenhuma linha e apagada em lugar nenhum, em nenhum dos quatro passos - so atualizada.

### Cuidado ao anonimizar quem tambem e organizador

Toda conta criada no sistema e organizadora (nao ha outro tipo de conta nesta etapa). Se o titular anonimizado
tambem organizou campeonatos proprios, esses campeonatos nao perdem dado nenhum, mas ficam inacessiveis por
dentro do produto: `exigirDonoDoCampeonato` so mostra um campeonato para quem esta logado como o proprio
organizador, e a conta anonimizada nunca mais consegue entrar (a senha foi apagada e `ativo` virou 0). Ninguem -
nem o antigo organizador, nem outro organizador, nem o operador pela tela - consegue mais abrir o chaveamento ou
a classificacao daquele campeonato depois disso, mesmo que as linhas continuem no banco. Antes de rodar a
exclusao, confira se o titular tambem organizou campeonato proprio e avise quem for afetado, porque essa parte
do historico deixa de ser alcancavel por qualquer tela do sistema.

## Retencao

Dados de campeonato ficam enquanto o organizador mantiver a conta ativa - mas ver a ressalva acima: uma conta de
organizador anonimizada tira o acesso aos proprios campeonatos, mesmo com os dados ainda no banco.
Nao ha compartilhamento com terceiros nem uso para publicidade.

## Pendencia da etapa 2

Nada. A politica de privacidade passou a ser obrigatoria nesta etapa, nao na etapa 2, porque o modelo de
negocio tem finalidade publicitaria e a pessoa precisa poder ler onde seu dado vai antes de aceitar. Entrou
na tarefa 10.
