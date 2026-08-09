# padelsuper8 - Documento de analise

Projeto: **padelsuper8**
Produto de: MT - Manfred Tecnologia
Documento: analise funcional para entrega ao desenvolvimento
Data: 08/08/2026
Etapa coberta: 1 de 2

---

## 1. Para que serve este documento

Descrever o que o sistema precisa fazer e por que, com detalhe suficiente para alguem que nunca viu o projeto conseguir construi-lo.

Ele responde o que e o porque. Nao responde o como: nao escolhe linguagem, banco, framework nem estrutura de arquivo. Onde uma decisao tecnica ja foi tomada e importa para o entendimento, ela aparece marcada como tal.

Quem le isto e o desenvolvedor. Quem decide o que esta escrito aqui e o Manfred.

---

## 2. Contexto

O padel cresceu rapido no Brasil e o Super 8 virou o formato favorito de quem organiza jogo entre amigos e em clube. Oito jogadores, todo mundo joga com e contra todo mundo, e no fim sai um campeao individual.

Hoje isso e organizado no papel e no grupo de mensagem. Quem organiza desenha a tabela de rodizio a mao, e errar e facil: basta uma dupla repetida para o torneio deixar de ser justo, e ninguem percebe no meio do jogo. A soma dos games sai na calculadora do celular, entre uma partida e outra, e some quando o organizador fecha o aplicativo. Quem quer saber como foi indo ao longo do ano nao tem onde olhar.

Tres dores concretas:

1. **Montar o rodizio da errado e ninguem nota.** Com oito jogadores existem 28 duplas possiveis, e o formato exige que cada uma aconteca exatamente uma vez ao longo das 7 rodadas. Feito a mao, e comum alguem jogar duas vezes com o mesmo parceiro e nunca com outro. O torneio parece normal e o resultado nao vale.
2. **A pontuacao se perde.** A conta e feita e some. Quando alguem questiona um placar depois, nao ha registro.
3. **Nao existe historico.** Cada torneio comeca do zero. Quem joga toda semana nao tem como acompanhar a propria evolucao, e o organizador nao tem como criar uma temporada.

---

## 3. Objetivos

### 3.1 Objetivo de negocio

Trazer cliente para a MT - Manfred Tecnologia.

O sistema e gratuito para todo mundo, competidor e organizador. Nao ha plano pago, nao ha cobranca por evento, nao ha limite de uso. O retorno vem de exposicao: em troca do uso gratuito, a MT figura como apoiadora e patrocinadora de cada campeonato criado na plataforma.

O calculo por tras disso: quem organiza Super 8 costuma ser dono de clube, professor ou empresario da regiao, e o publico que ve as telas e gente da mesma cidade com renda para praticar padel. A marca aparece para o organizador toda vez que ele usa o sistema, e para os competidores toda vez que o chaveamento ou a classificacao circula no grupo.

### 3.2 Objetivos do produto

1. Tornar impossivel montar um rodizio errado.
2. Registrar placar de forma que ele nao se perca e possa ser conferido depois.
3. Dar historico entre eventos, para existir temporada e nao so torneio solto.
4. Ser usavel na beira da quadra, no celular, entre partidas, por quem esta com pressa.
5. Colocar a marca da MT diante de quem joga, sem atrapalhar quem joga.

### 3.3 Como saber se deu certo

| Indicador | Meta da etapa 1 |
|---|---|
| Torneio completo lancado sem erro de rodizio | 100 por cento |
| Tempo para lancar o placar de uma partida | menos de 15 segundos no celular |
| Organizador volta a criar um segundo torneio | pelo menos metade |
| Reclamacao sobre chaveamento injusto | zero, e quando houver, respondida com a prova do sorteio |

---

## 4. Escopo

### 4.1 Entra na etapa 1

- Conta de organizador, com termo de uso aceito e registrado.
- Criacao e edicao de campeonato.
- Cadastro dos 8 competidores.
- Sorteio das posicoes e geracao automatica das 7 rodadas com 14 partidas.
- Lancamento de placar.
- Classificacao do evento.
- Encerramento do evento.
- Ranking acumulado entre eventos, com filtro por periodo.
- Marca da MT nas telas que o competidor ve.
- Exclusao de dado a pedido do titular.

### 4.2 Nao entra na etapa 1

- Entrada por conta Google. Fica para a etapa 2.
- Pagina publica de divulgacao do evento, aberta sem login, com canal de contato com o organizador. Fica para a etapa 2.
- Foto de perfil do jogador.
- Formato diferente de Super 8. O sistema faz um formato so.
- Pagamento, cobranca de inscricao, controle financeiro.
- Aplicativo de celular instalavel. O sistema e web e funciona no navegador do celular.

---

## 5. Atores

| Ator | Quem e | O que faz |
|---|---|---|
| Organizador | quem cria e toca o torneio | cria conta, cria campeonato, cadastra competidores, sorteia, lanca placar, encerra |
| Competidor com conta | jogador cadastrado no sistema | e inscrito em campeonatos e acumula desempenho no ranking |
| Competidor convidado | jogador cadastrado so pelo nome | joga e aparece na classificacao do evento, mas nao acumula entre eventos |
| MT | dona do produto | aparece como apoiadora em todo campeonato |

Nesta etapa o competidor nao tem tela propria: quem opera o sistema e o organizador. O competidor ve as telas pelo celular do organizador ou por captura de tela que circula no grupo.

---

## 6. O formato Super 8, a regra do jogo

Esta secao e a regra de negocio central. Errar aqui invalida o produto.

Oito jogadores. Sete rodadas. Em cada rodada acontecem 2 partidas ao mesmo tempo, uma em cada quadra, com 4 jogadores cada. Logo, os 8 jogam em todas as rodadas, e o torneio tem 14 partidas.

A cada rodada o jogador troca de parceiro. Ao final das 7 rodadas, **cada jogador tera sido parceiro de cada um dos outros 7 exatamente uma vez**. Como existem 28 duplas possiveis entre 8 pessoas, e cada rodada forma 4 duplas, as 7 rodadas formam exatamente as 28 duplas, sem repetir nenhuma e sem faltar nenhuma.

A pontuacao e individual e por games. Cada jogador soma os games que a dupla dele venceu em cada partida que disputou. Quem somar mais games no evento e o campeao.

O rodizio e uma tabela fixa por posicao, de 1 a 8:

| Rodada | Quadra 1 | Quadra 2 |
|---|---|---|
| 1 | 1+8 contra 2+7 | 3+6 contra 4+5 |
| 2 | 2+8 contra 1+3 | 4+7 contra 5+6 |
| 3 | 3+8 contra 2+4 | 1+5 contra 6+7 |
| 4 | 4+8 contra 3+5 | 2+6 contra 1+7 |
| 5 | 5+8 contra 4+6 | 3+7 contra 1+2 |
| 6 | 6+8 contra 5+7 | 1+4 contra 2+3 |
| 7 | 7+8 contra 1+6 | 2+5 contra 3+4 |

A tabela nunca muda. O sorteio decide apenas qual jogador ocupa cada posicao de 1 a 8.

---

## 7. Regras de negocio

**RN01 - Oito competidores, nem mais nem menos.** Um campeonato aceita exatamente 8 inscritos. Nao da para sortear com 7 nem com 9.

**RN02 - O rodizio e fixo.** As duplas de cada rodada saem da tabela da secao 6 e nao sao geradas por algoritmo em tempo de execucao. O que varia e so quem ocupa cada posicao.

**RN03 - O sorteio e reproduzivel.** O sistema guarda a semente usada no sorteio. Refazendo o sorteio com a mesma semente e os mesmos inscritos, o chaveamento sai identico. Isso existe para responder a acusacao de favorecimento: da para provar que o sorteio foi limpo.

**RN04 - Refazer o sorteio apaga o anterior.** Nao acumula rodada. E so e permitido enquanto nenhum placar tiver sido lancado.

**RN05 - Pontuacao por games.** Os dois jogadores de uma dupla somam os games que a dupla fez, e registram os games que a dupla sofreu.

**RN06 - Ordem da classificacao.** Games vencidos, depois saldo de games, depois numero de partidas vencidas, depois confronto direto.

**RN07 - Quando o sistema admite que nao sabe.** Confronto direto resolve entre duas pessoas. Entre tres ou mais pode nao resolver: se A ganha de B, B ganha de C e C ganha de A, nao existe ordem correta. Nesse caso o grupo inteiro aparece marcado como empatado, em vez de a tela inventar uma ordem. A alternativa seria ordenar pelo que vier primeiro do banco, que muda entre consultas e daria a duas pessoas dois podios diferentes para o mesmo torneio.

**RN08 - Partida sem placar nao conta.** Ela nao entra na soma nem na contagem de jogos disputados.

**RN09 - So evento encerrado entra no ranking.** Enquanto o torneio esta em andamento, ele nao mexe no acumulado.

**RN10 - So quem tem conta acumula.** Competidor cadastrado apenas pelo nome aparece na classificacao do evento dele e nao entra no ranking entre eventos, porque nao ha como saber que dois convidados de mesmo nome sao a mesma pessoa.

**RN11 - A mesma conta nao entra duas vezes no mesmo campeonato.** Sem essa regra, um organizador que cadastrasse a mesma pessoa como "Joao" e "Joao S." faria essa pessoa contar dois eventos e o dobro de games no ranking, sem nada aparecer na tela.

**RN12 - Evento encerrado nao muda mais.** Depois de encerrado, nao se lanca nem se corrige placar, e nao se muda a data. Editar placar depois reescreveria o ranking historico de forma silenciosa; mudar a data moveria o evento para dentro ou para fora de todo filtro de periodo.

**RN13 - Cada organizador so enxerga o que e dele.** Campeonato de outro organizador nao aparece, nao abre e nao aceita alteracao. A resposta para tentativa de acesso e a mesma de campeonato inexistente, para nao revelar que aquele identificador existe.

**RN14 - A MT aparece como apoiadora.** Todo campeonato criado exibe a marca da MT como apoiadora e patrocinadora, nas telas que o competidor ve. Nao e opcional nem configuravel pelo organizador: e a contrapartida do uso gratuito.

**RN15 - Nada se apaga por pedido de exclusao.** Exclusao a pedido do titular troca o nome por identificador anonimo e desativa a conta. Os placares permanecem, porque a partir dai sao numero de evento sem ligacao com pessoa identificavel.

---

## 8. Requisitos funcionais

### Conta e acesso

**RF01** O sistema permite criar conta de organizador com nome, e-mail e senha.

**RF02** O cadastro exige aceite do termo de uso, com registro de qual versao foi aceita, quando, e de qual endereco de origem. Sem aceite nao ha conta.

**RF03** O sistema permite entrar com e-mail e senha, e sair.

**RF04** Apos varias tentativas seguidas de senha errada, o sistema impoe espera crescente antes de aceitar nova tentativa.

**RF05** A mensagem de erro de login e a mesma para e-mail inexistente e para senha errada, e o tempo de resposta tambem, para nao permitir descobrir de fora quais e-mails tem conta.

**RF06** Quando o termo de uso mudar de versao, o organizador passa por uma tela de aceite da nova versao antes de continuar usando o sistema.

### Campeonato

**RF07** O organizador cria campeonato informando nome, data, local, custo por jogador e descricao. Custo vazio significa gratuito.

**RF08** O sistema recusa dado invalido e diz o que esta errado: nome vazio ou longo demais, data inexistente no calendario, data em formato diferente do esperado, custo negativo ou nao numerico.

**RF09** O organizador ve a lista dos campeonatos dele, com nome, data, local e situacao.

**RF10** O organizador edita um campeonato enquanto ele nao estiver encerrado.

### Competidores

**RF11** O organizador cadastra competidores pelo nome de exibicao, ate 8.

**RF12** O sistema recusa o nono competidor e recusa nome repetido dentro do mesmo campeonato.

**RF13** O organizador remove competidor enquanto o sorteio nao tiver acontecido.

**RF14** A tela de inscricao avisa ao organizador que o nome do competidor aparecera no chaveamento, na classificacao e no ranking, e que cabe a ele informar os jogadores que inscreve.

### Sorteio e chaveamento

**RF15** Com 8 competidores cadastrados, o organizador aciona o sorteio. O sistema distribui as posicoes de 1 a 8 e gera as 7 rodadas com 14 partidas.

**RF16** O chaveamento mostra, por rodada e por quadra, quais duplas se enfrentam.

**RF17** A tela do chaveamento mostra a semente do sorteio, para permitir conferencia posterior.

**RF18** O organizador refaz o sorteio enquanto nenhum placar tiver sido lancado.

### Placar

**RF19** O organizador lanca os games de cada dupla em cada partida.

**RF20** O sistema recusa valor fora da faixa aceitavel de games.

**RF21** O placar lancado pode ser corrigido enquanto o campeonato nao estiver encerrado.

**RF22** O sistema registra quem lancou o placar e quando.

### Classificacao e encerramento

**RF23** A classificacao do evento mostra, por jogador, games vencidos, games sofridos, saldo, partidas vencidas e partidas disputadas, na ordem da RN06.

**RF24** A classificacao indica quando ha empate que os criterios nao resolvem, conforme RN07.

**RF25** A tela informa quantas partidas ainda estao sem placar, e a classificacao continua visivel e correta para o que ja foi lancado.

**RF26** Com todas as partidas lancadas, o organizador encerra o campeonato.

**RF27** O sistema recusa encerrar campeonato com partida pendente.

### Ranking

**RF28** O ranking soma o desempenho dos jogadores com conta entre campeonatos encerrados.

**RF29** O ranking tem filtro por periodo, com atalho para mes atual, ano atual, tudo, e intervalo livre.

**RF30** O ranking mostra, por jogador, eventos disputados, jogos disputados, games, games sofridos, saldo e media de games por evento.

**RF31** A tela do ranking explica que competidor sem conta nao acumula entre eventos.

### Dados pessoais

**RF32** O sistema oferece rotina de exclusao a pedido do titular, conforme RN15.

**RF33** O termo de uso e a politica de privacidade sao acessiveis por link a partir das telas.

---

## 9. Requisitos nao funcionais

**RNF01 - Celular primeiro.** As telas sao usadas em pe, na beira da quadra, com o celular na mao. A tela de placar em especial precisa de campo grande e poucos toques.

**RNF02 - Sem instalacao.** Funciona no navegador, sem aplicativo.

**RNF03 - Escala esperada.** Ate cerca de mil usuarios e alguns milhares de partidas. Nao ha requisito de alta disponibilidade nem de resposta em milissegundos sob carga.

**RNF04 - Seguranca.** Senha guardada com algoritmo de hash moderno e nunca em texto claro. Protecao contra injecao de SQL, contra execucao de script vindo de campo de formulario, e contra acao disparada de outro site em nome do usuario logado. Sessao com cookie protegido.

**RNF05 - Isolamento entre organizadores.** Conforme RN13.

**RNF06 - Auditabilidade do sorteio.** Conforme RN03.

**RNF07 - Concorrencia.** Dois acessos simultaneos do mesmo organizador, ou uma aba esquecida aberta, nao podem produzir estado invalido: nove competidores num campeonato de oito, ou placar apagado por um sorteio disparado ao mesmo tempo.

**RNF08 - Idioma.** Portugues do Brasil em toda a interface.

**RNF09 - Codigo fechado.** Produto proprietario da MT, publicado em infraestrutura da MT. Nada no produto revela ferramenta de desenvolvimento usada para construi-lo.

---

## 10. Fluxo principal

O caminho completo, do zero ao ranking:

1. O organizador cria conta e aceita o termo de uso.
2. Cria o campeonato com nome, data, local e custo.
3. Cadastra os 8 competidores.
4. Aciona o sorteio. O sistema gera as 7 rodadas com 14 partidas.
5. Mostra o chaveamento para os jogadores.
6. Ao longo do torneio, lanca o placar de cada partida.
7. Acompanha a classificacao parcial entre rodadas.
8. Com tudo lancado, encerra o campeonato.
9. A classificacao final circula no grupo, com a marca da MT.
10. O desempenho entra no ranking acumulado do periodo.

Desvios previstos: sorteio refeito antes do primeiro placar; placar corrigido antes do encerramento; competidor trocado antes do sorteio; campeonato deixado pela metade e retomado depois.

---

## 11. Modelo de dados conceitual

Nao e o desenho fisico da tabela. E o que precisa existir e como se relaciona.

- **Usuario:** nome, e-mail, senha, se e organizador, se esta ativo. Reserva espaco para identificador externo e foto, que a etapa 2 vai usar.
- **Aceite de termo:** liga usuario a versao aceita, com data e origem. Guarda historico e nao so o ultimo, porque quando o termo mudar e preciso saber quem aceitou o que.
- **Campeonato:** dono, nome, data, local, custo, descricao, situacao, semente do sorteio.
- **Inscricao:** liga campeonato a um competidor. Pode apontar para um usuario ou guardar so o nome, no caso do convidado. Recebe a posicao de 1 a 8 no sorteio.
- **Rodada:** as 7 de cada campeonato.
- **Partida:** as 2 de cada rodada, com as quatro inscricoes envolvidas, os games de cada lado, e o registro de quem lancou e quando.

O ranking nao tem tabela propria. Ele e calculado a partir das partidas no momento da consulta. Tabela de ranking guardada precisaria de recalculo a cada fechamento e sairia de sincronia na primeira correcao de placar.

---

## 12. Exigencias legais

Esta secao nao e recomendacao. E requisito, e o modelo de negocio depende dela.

### 12.1 Dados pessoais tratados

Nome e e-mail do organizador. Nome de exibicao do competidor, e o e-mail quando ele tem conta. Endereco de origem no momento do aceite do termo, guardado como evidencia desse aceite e para nada mais.

### 12.2 Base legal

**Organizador:** execucao de contrato, com termo aceito e registrado. O contrato e o proprio modelo: uso gratuito em troca de a MT figurar como apoiadora dos campeonatos criados.

**Competidor inscrito por terceiro, sem conta:** legitimo interesse na organizacao do torneio, com aviso na tela de inscricao.

Vale separar duas coisas que se confundem com facilidade. O nome do jogador aparece porque ele esta jogando, e nao para endossar a MT. A marca da MT esta na pagina como patrocinadora do evento, do mesmo jeito que um banner de patrocinador aparece atras da quadra num torneio presencial.

Essa distincao e o que sustenta o legitimo interesse. Se em algum momento o produto passar a usar nome ou imagem de jogador em peca publicitaria da MT, em anuncio, post ou material de venda, a base legal muda: ali passa a ser consentimento especifico de cada jogador, e legitimo interesse nao cobre.

### 12.3 Quem informa o competidor

O competidor sem conta nao aceita nada, porque nao tem cadastro. Quem responde por informa-lo e o organizador, que aceitou o termo. Isso precisa estar escrito no termo e visivel na tela de inscricao.

### 12.4 Minimizacao e direitos

O sistema nao pede telefone, documento nem data de nascimento, porque nao precisa deles. Exclusao a pedido segue a RN15.

### 12.5 Pendencia que nao e tecnica

Esta analise foi escrita por quem trabalha com software, nao por quem exerce advocacia. Antes de o sistema ser aberto para organizadores fora do circulo do Manfred, o termo de uso e esta secao merecem leitura de um advogado. O custo de descobrir um erro aqui depois de haver clientes reais e muito maior do que o de uma revisao agora.

---

## 13. A marca da MT nas telas

A marca e requisito de produto, nao enfeite. Sem ela o modelo de negocio nao existe.

Onde aparece: pagina do evento, chaveamento, classificacao e ranking. Ou seja, toda superficie que o competidor ve.

Como aparece, e aqui esta o cuidado que importa: o chaveamento e a tela de placar sao usados no celular, em pe, entre partidas. Marca que rouba espaco da informacao do jogo estraga as duas coisas ao mesmo tempo, porque o organizador abandona a ferramenta e a publicidade deixa de alcancar alguem.

A regra pratica: **presenca proporcional ao tempo de leitura da tela**. Na tela de operacao, discreta e constante. Na tela que circula depois do torneio, que e onde a publicidade de fato trabalha, com destaque proprio.

---

## 14. Criterios de aceite

O sistema esta pronto para a etapa 1 quando, num teste de ponta a ponta:

**CA01** Criar conta sem aceitar o termo e recusado, e com aceite gera registro de qual versao foi aceita.

**CA02** Cinco tentativas de senha errada bloqueiam, e o login correto depois do bloqueio expirar funciona.

**CA03** Criar campeonato com data em formato brasileiro, ou com data inexistente, e recusado com mensagem clara.

**CA04** O nono competidor e recusado, e o nome repetido tambem.

**CA05** Feito o sorteio, as 14 partidas formam 28 duplas distintas, cada jogador e parceiro de cada outro exatamente uma vez, e cada rodada contem os 8 jogadores uma unica vez.

**CA06** Refazer o sorteio com a mesma semente produz chaveamento identico.

**CA07** Lancados os 14 placares, a soma de games de dois jogadores escolhidos ao acaso confere com a conta feita a mao.

**CA08** Encerrar com partida pendente e recusado; com tudo lancado, funciona.

**CA09** Apos encerrar, tentar corrigir placar e recusado.

**CA10** O evento encerrado aparece no ranking do periodo correspondente, e nao aparece em periodo diferente.

**CA11** Competidor sem conta aparece na classificacao do evento e nao aparece no ranking.

**CA12** Um organizador nao consegue abrir nem alterar campeonato de outro, e recebe a mesma resposta de campeonato inexistente.

**CA13** A marca da MT aparece no chaveamento, na classificacao e no ranking.

**CA14** Todas as telas sao usaveis em tela de celular, e a de placar aceita o lancamento de uma partida em menos de 15 segundos.

---

## 15. Decisoes em aberto

**D01 - Criterio principal da classificacao.** A regra atual e games em primeiro lugar, conforme o documento de requisitos original. Rodando com dados reais apareceu um efeito que vai gerar discussao na quadra: um jogador com 4 vitorias pode ficar atras de outro com 1 vitoria, porque o que conta primeiro e o total de games. Tres saidas possiveis: manter e a tela explicar o criterio; inverter para vitorias primeiro e games como desempate; ou manter games e dar destaque visual a coluna de vitorias, para a conversa acontecer com o numero a vista. Decisao do Manfred.

**D02 - Forma da marca.** Falta definir o que exibir: arquivo de logotipo, ou apenas texto. Ate a definicao, texto resolve, isolado num unico ponto do sistema para que a troca por imagem depois nao mexa em tela nenhuma.

**D03 - Texto do termo de uso e da politica de privacidade.** Precisa ser escrito e aprovado pelo Manfred antes de virar tela.

---

## 16. Riscos

**RI01 - A analise juridica esta em pe sozinha.** O modelo de negocio inteiro se apoia na secao 12, escrita sem advogado. Mitigacao: revisao antes de abrir para organizadores de fora.

**RI02 - Erro de rodizio e invisivel.** Um chaveamento errado continua parecendo um chaveamento: mesmo numero de rodadas, mesmo numero de partidas, oito jogadores por rodada. So a conferencia das 28 duplas distingue. Mitigacao: essa conferencia precisa ser automatica e rodar sempre, nunca depender de olhar humano.

**RI03 - Marca demais afasta o organizador.** Se a publicidade atrapalhar o uso na quadra, o organizador volta para o papel e a MT perde o canal. Mitigacao: a regra da secao 13.

**RI04 - Organizador unico.** Nesta etapa o competidor depende do organizador para ver qualquer coisa. Se o organizador some no meio do torneio, ninguem mais lanca placar. Mitigacao aceita nesta etapa: nenhuma. A pagina publica da etapa 2 reduz o problema.

---

## 17. Etapa 2, para dimensionar o que vem

Registrado aqui para o desenvolvimento nao fechar portas que serao reabertas:

- Entrada por conta Google, sem senha. O cadastro de usuario ja deve nascer com espaco para identificador externo e foto.
- Pagina publica do evento, aberta sem login, com divulgacao, informacao de inscricao e canal de contato com o organizador.
- Tela propria para o competidor acompanhar o proprio desempenho.

---

## 18. Situacao atual do desenvolvimento

Complemento honesto para quem receber este documento e for continuar o trabalho.

**Pronto e testado:** toda a logica descrita nas secoes 6 a 11. Rodizio, sorteio auditavel, conta e acesso, campeonato, inscricoes, chaveamento, placar, classificacao com os criterios de desempate, encerramento, ranking acumulado, e o registro de aceite do termo. Existe suite de teste automatizada cobrindo essa camada, incluindo casos de concorrencia com dois processos disputando o mesmo recurso.

**Nao existe ainda:** nenhuma tela. A camada de apresentacao esta vazia. Tambem faltam o texto do termo e da politica, e a definicao da marca.

**Vale saber:** a logica ja passou por revisao que encontrou defeitos que os testes iniciais nao pegavam, entre eles um chaveamento que geraria 18 duplas em vez de 28 e um ranking que retornaria vazio em producao. Ambos corrigidos, com teste que falha se alguem desfizer. Esse historico esta no repositorio.
