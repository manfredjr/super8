# Super 8 Padel - Especificacao da etapa 1

Produto da MT - Manfred Tecnologia.

Data: 08/08/2026
Escopo: motor do Super 8 mais ranking acumulado, sem login com Google.

---

## 0. O produto e o modelo

O Super 8 e produto da MT - Manfred Tecnologia, e existe para trazer cliente para a MT.

O uso e gratuito, para o competidor e para quem cria a competicao. Nao ha plano pago nem cobranca por evento. O retorno vem por publicidade: em troca do uso gratuito, a MT figura como apoiadora e patrocinadora de cada campeonato criado na plataforma.

**O que isso exige do produto.** A marca da MT nao e um selo solto no rodape de uma tela. Ela aparece em toda superficie que o competidor ve: pagina do evento, chaveamento, classificacao e ranking. E o que o organizador esta contratando ao usar de graca, e por isso e requisito, nao enfeite.

Com um limite que vem do uso real: o chaveamento e a tela de placar sao usados na beira da quadra, no celular, entre partidas. Marca que rouba espaco da informacao do jogo estraga as duas coisas ao mesmo tempo, porque o organizador abandona a ferramenta e a publicidade deixa de alcancar alguem. A marca fica presente e identificavel, nunca competindo com o placar.

**O que isso exige juridicamente, e nao e opcional.** Usar nome de pessoa e nome de evento com finalidade publicitaria precisa de base legal e de aviso aceito, nao presumido. Um sistema que simplesmente assume o consentimento fica exposto, e o problema aparece justamente quando o modelo estiver funcionando e alguem reclamar.

Por isso o cadastro do organizador passa por termo de uso com aceite registrado, e o aceite fica gravado com versao e data. Detalhe na secao 4 e na secao 10.

O competidor sem conta, cadastrado pelo organizador apenas pelo nome, nao aceita nada, porque nao tem cadastro. Quem responde por ele e o organizador, que aceitou o termo e se compromete a informar os jogadores que inscreve. Isso precisa estar escrito no proprio termo, e a tela de inscricao precisa dizer ao organizador que essa responsabilidade e dele.

---

## 1. O que entra e o que fica de fora

Entra nesta etapa:

- Cadastro e login do organizador por e-mail e senha, com termo de uso aceito e registrado.
- Criacao de campeonato com nome, data, local, custo e descricao.
- Cadastro dos 8 competidores de cada campeonato.
- Sorteio com semente gravada e geracao automatica das 7 rodadas.
- Lancamento de placar das 14 partidas.
- Classificacao do evento por games vencidos.
- Ranking acumulado entre eventos, com filtro por periodo.
- Marca da MT como apoiadora do evento nas telas que o competidor ve.

Fica para depois:

- Login com Google via OAuth (etapa 2).
- Pagina publica de divulgacao e canal de contato com o organizador.
- Foto de perfil do jogador.

---

## 2. Decisoes de arquitetura

### PHP puro com PDO, sem framework

O sistema tem 7 tabelas e cerca de 10 telas. Laravel exigiria Composer instalado, DocumentRoot apontando para a pasta `public` e um cuidado extra na subida para o VPS. Em PHP puro a pasta vai inteira para `htdocs` no teste e para `_PUBLICAR\enviar` na producao, por FTP.

Na etapa 2, o OAuth do Google entra pela biblioteca oficial `google/apiclient` ou por chamada direta ao endpoint. Nao existe dependencia do Laravel Socialite que nao tenha equivalente.

Risco assumido: se o projeto crescer muito alem do previsto, a migracao para framework custa. Como o escopo esta fechado em torneio, placar e ranking, o risco e baixo.

### Login por e-mail e senha, com a porta aberta para o Google

A tabela `users` nasce com a coluna `google_id` nula. Na etapa 2, o primeiro login pelo Google localiza o usuario pelo e-mail, grava o `google_id` e mantem historico, inscricoes e ranking. Sem isso, a etapa 2 viraria retrabalho na camada de acesso.

### Ranking por consulta agregada, nao por tabela materializada

O ranking acumulado sai de uma consulta sobre `partidas`, filtrada por periodo. Tabela materializada precisaria de recalculo a cada fechamento de evento e sairia de sincronia na primeira correcao de placar. Com mil usuarios e alguns milhares de partidas, a consulta agregada responde em milissegundos.

---

## 3. Estrutura de pastas

```
super8/
  config/
    db.php            conexao PDO
    sessao.php        inicio de sessao com cookie seguro
    csrf.php          geracao e conferencia de token, funcao e()
    acesso.php        usuario logado, exige login, exige dono do campeonato
  src/
    Sorteio.php       embaralha os 8 jogadores a partir da semente
    Rodizio.php       tabela fixa das 7 rodadas
    Campeonato.php    criacao, inscricao, geracao das rodadas
    Placar.php        gravacao de placar e classificacao do evento
    Ranking.php       agregacao entre eventos por periodo
    Auth.php          cadastro, login, bloqueio por tentativa, registro de aceite
  views/
    layout.php  marca.php   login.php  campeonatos.php
    campeonato_form.php     inscricoes.php  chaveamento.php
    placar.php  classificacao.php  ranking.php
    termo.php   privacidade.php
  public/
    index.php  login.php  logout.php  campeonato.php
    inscricoes.php  sortear.php  placar.php  ranking.php
    termo.php  privacidade.php
    css/
  ferramentas/
    sincronizar-htdocs.ps1  espelha a copia de teste no XAMPP
    montar-pacote.ps1       monta _PUBLICAR\enviar, rodando a suite antes
  sql/
    schema.sql        criacao das tabelas
  testes/
    13 arquivos teste_*.php, mais ajudantes _ajuda_*.php chamados
    por subprocesso. O runner recolhe so teste_*.php.
```

A pasta `src/` nao contem uma linha de HTML. Cada arquivo dela roda e e testado sem navegador.

---

## 4. Banco de dados

### users

| coluna | tipo | observacao |
|---|---|---|
| id | INT AUTO_INCREMENT PK | |
| google_id | VARCHAR(64) NULL UNIQUE | reservado para a etapa 2 |
| nome | VARCHAR(120) NOT NULL | |
| email | VARCHAR(160) NULL UNIQUE | nulo em jogador convidado |
| senha_hash | VARCHAR(255) NULL | nulo em jogador convidado |
| foto_url | VARCHAR(255) NULL | reservado para a etapa 2 |
| e_organizador | TINYINT(1) DEFAULT 0 | |
| ativo | TINYINT(1) DEFAULT 1 | `exigirLogin` rele esta coluna a cada requisicao; zerar corta a sessao de quem ja esta logado |
| criado_em | DATETIME NOT NULL | |

### campeonatos

| coluna | tipo | observacao |
|---|---|---|
| id | INT AUTO_INCREMENT PK | |
| organizador_id | INT NOT NULL FK users | |
| nome | VARCHAR(160) NOT NULL | |
| data_evento | DATE NOT NULL | |
| local | VARCHAR(160) | |
| custo | DECIMAL(10,2) NULL | nulo significa gratuito |
| descricao | TEXT | |
| status | ENUM rascunho, sorteado, em_andamento, encerrado | |
| seed_sorteio | INT UNSIGNED NULL | gravada no momento do sorteio |
| criado_em | DATETIME NOT NULL | |

### inscricoes

| coluna | tipo | observacao |
|---|---|---|
| id | INT AUTO_INCREMENT PK | |
| campeonato_id | INT NOT NULL FK | |
| jogador_id | INT NULL FK users | nulo em convidado sem conta |
| nome_exibicao | VARCHAR(120) NOT NULL | |
| posicao_sorteio | TINYINT NULL | 1 a 8, preenchida no sorteio |

Chaves unicas em (campeonato_id, posicao_sorteio), em (campeonato_id, nome_exibicao) e em (campeonato_id, jogador_id). A terceira impede que a mesma conta seja inscrita duas vezes no mesmo campeonato sob nomes diferentes, o que dobraria os games dela no ranking sem nada aparecer na tela.

### rodadas

`id`, `campeonato_id` FK, `numero` TINYINT de 1 a 7. Unica em (campeonato_id, numero).

### partidas

| coluna | tipo | observacao |
|---|---|---|
| id | INT AUTO_INCREMENT PK | |
| rodada_id | INT NOT NULL FK | |
| quadra | TINYINT | 1 ou 2 |
| dupla_a_j1, dupla_a_j2 | INT NOT NULL FK inscricoes | |
| dupla_b_j1, dupla_b_j2 | INT NOT NULL FK inscricoes | |
| games_a, games_b | TINYINT NULL | nulo enquanto nao jogada |
| encerrada | TINYINT(1) DEFAULT 0 | |
| gravado_por | INT UNSIGNED NULL | quem lancou o placar |
| gravado_em | DATETIME NULL | quando |

As partidas apontam para `inscricoes`, nao para `users`. Isso permite jogador convidado sem conta e mantem o placar preso ao evento.

O ranking acumulado nao tem tabela. Sai de consulta agregada sobre `partidas` juntando `inscricoes` e `users`, filtrada por intervalo de datas.

### tentativas_login

`email` PK, `tentativas` TINYINT, `bloqueado_ate` DATETIME nulo. Tabela de apoio para o bloqueio por forca bruta descrito na secao 9. Nao guarda dado pessoal alem do e-mail digitado na tentativa e pode ser esvaziada a qualquer momento.

### aceites_termo

| coluna | tipo | observacao |
|---|---|---|
| id | INT UNSIGNED AUTO_INCREMENT PK | |
| user_id | INT UNSIGNED NOT NULL FK users | |
| versao | VARCHAR(20) NOT NULL | versao do termo aceito |
| aceito_em | DATETIME NOT NULL | |
| ip | VARCHAR(45) NULL | comporta IPv4 e IPv6 |

Chave unica em (user_id, versao).

**Por que tabela e nao coluna em `users`.** O termo vai mudar, e quando mudar e preciso saber quem aceitou qual versao e quando. Uma coluna seria sobrescrita no primeiro reaceite, e junto com ela a prova de que a versao anterior foi aceita. Tabela guarda o historico, que e exatamente o que serve de evidencia se alguem questionar.

A versao do termo em vigor fica em constante na configuracao. No login, se a versao aceita pelo usuario for menor que a em vigor, ele passa pela tela de aceite antes de continuar.

O `ip` e dado pessoal, e esta ali de proposito: sem ele o registro de aceite vale pouco como evidencia. A finalidade e estritamente essa, e o campo aceita nulo para o caso de o endereco nao estar disponivel.

---

## 5. Rodizio das 7 rodadas

Tabela fixa, conferida par a par. As 28 duplas possiveis aparecem e cada jogador e parceiro de cada um dos outros exatamente uma vez.

| Rodada | Quadra 1 | Quadra 2 |
|---|---|---|
| 1 | 1+8 x 2+7 | 3+6 x 4+5 |
| 2 | 2+8 x 1+3 | 4+7 x 5+6 |
| 3 | 3+8 x 2+4 | 1+5 x 6+7 |
| 4 | 4+8 x 3+5 | 2+6 x 1+7 |
| 5 | 5+8 x 4+6 | 3+7 x 1+2 |
| 6 | 6+8 x 5+7 | 1+4 x 2+3 |
| 7 | 7+8 x 1+6 | 2+5 x 3+4 |

No codigo isso vira uma constante em `Rodizio.php`, um array de 7 rodadas com 2 partidas cada. A estrutura nunca muda. O sorteio so decide qual jogador ocupa cada posicao de 1 a 8.

---

## 6. Sorteio com semente

Ao sortear, o sistema gera um inteiro aleatorio, grava em `campeonatos.seed_sorteio`, chama `mt_srand($seed)` e embaralha as 8 inscricoes. As posicoes 1 a 8 sao gravadas em `inscricoes.posicao_sorteio`. Em seguida cria as 7 rodadas e as 14 partidas encaixando as inscricoes nas posicoes da tabela de rodizio.

Guardar a semente torna o sorteio reproduzivel. Se alguem questionar o chaveamento, roda de novo com a mesma semente e chega no mesmo resultado.

O sorteio so acontece com exatamente 8 inscricoes. Ele muda o status para `sorteado` apenas se o status atual for `rascunho` ou `sorteado`; um resorteio de auditoria sobre evento `em_andamento` ou `encerrado` deixa o status como esta, para nao rebaixar em silencio um evento que ja andou. Refazer o sorteio apaga rodadas e partidas, entao so e permitido enquanto nenhum placar tiver sido lancado.

---

## 7. Placar e classificacao

Cada partida recebe `games_a` e `games_b`. Os dois jogadores da dupla A somam `games_a` na conta individual, os da dupla B somam `games_b`.

Classificacao do evento: soma dos games de cada jogador nas 7 partidas que disputou, em ordem decrescente. Criterios de desempate, em ordem: saldo de games (ganhos menos sofridos), depois numero de partidas vencidas, depois confronto direto.

**Quando o sistema admite que nao sabe quem ficou na frente.** Confronto direto entre dois jogadores resolve bem. Entre tres ou mais, nao. Se A ganha de B, B ganha de C e C ganha de A, nao existe ordem correta: qualquer uma que a tela mostrar e invencao. A regra, entao, e por grupo e nao por par. Jogadores que empatam em games, saldo e vitorias formam um grupo. Dentro do grupo, conta-se quantos companheiros de grupo cada um venceu no confronto direto. Se essas contagens forem todas diferentes, o confronto direto ordenou o grupo e a classificacao segue normal. Se houver qualquer repeticao, seja por empate entre dois ou por ciclo entre tres, o grupo inteiro aparece marcado como empatado.

A alternativa seria ordenar pelo que veio primeiro do banco, que muda entre consultas e da a duas pessoas com o mesmo dado dois podios diferentes. Melhor a tela dizer que empatou.

Ranking acumulado: mesma soma, agora entre campeonatos encerrados dentro do periodo escolhido. As colunas sao jogador, eventos disputados, jogos disputados, games, games sofridos, saldo e media de games por evento. O filtro de periodo tem atalhos para mes atual, ano atual e intervalo livre.

Melhor colocacao nao entra. Ela exigiria recalcular a classificacao inteira de cada evento dentro da consulta agregada, e o custo nao se paga nesta etapa.

O jogador so aparece no ranking entre eventos se tiver `jogador_id` preenchido, ou seja, conta de usuario. Convidado sem conta aparece na classificacao do evento dele e some do acumulado. Isso precisa estar avisado na tela de inscricao.

---

## 8. Telas

1. Login e cadastro do organizador.
2. Lista de campeonatos do organizador.
3. Formulario de campeonato, criacao e edicao.
4. Inscricoes, cadastro dos 8 competidores.
5. Chaveamento, as 7 rodadas com as duplas.
6. Placar, lancamento dos games por partida.
7. Classificacao do evento.
8. Ranking acumulado com filtro de periodo.

Todas pensadas para celular primeiro, porque o uso real acontece na beira da quadra com o organizador segurando o telefone. A tela de placar merece atencao extra: botoes grandes, poucos toques por partida e confirmacao antes de gravar.

---

## 9. Seguranca

| Risco | Tratamento |
|---|---|
| Injecao de SQL | PDO com prepared statements em toda consulta, sem excecao. Nenhuma concatenacao de variavel em SQL. |
| XSS | `htmlspecialchars` com ENT_QUOTES em toda saida de dado vindo do banco ou do formulario. |
| CSRF | Token por sessao conferido em todo POST. Formulario sem token nao grava. |
| Senha fraca ou vazada | `password_hash` com PASSWORD_ARGON2ID. Minimo de 8 caracteres. Nunca gravar senha em texto nem em log. |
| Sequestro de sessao | Cookie com HttpOnly, SameSite Strict e Secure quando houver HTTPS. `session_regenerate_id` no login. |
| Acesso a campeonato alheio | Toda leitura e escrita confere se `organizador_id` bate com o usuario da sessao. A checagem fica em `config/acesso.php`, e nao em `src/`, porque le sessao e escreve cabecalho, o que a restricao global proibe em `src/`. E chamada no topo de cada ponto de entrada. |
| Forca bruta no login | Contador de tentativas por e-mail com espera crescente apos a quinta falha. |
| Placar adulterado | Validacao de faixa nos games, de 0 a 99, e registro de quem gravou com data e hora. |

Nao existe upload de arquivo nesta etapa, o que elimina uma classe inteira de risco.

### Limites conhecidos e aceitos nesta etapa

Levantados na revisao da camada de autenticacao. Ficam registrados porque sao decisoes, nao esquecimentos.

**O contador de tentativas e por e-mail, sem dimensao por IP.** Isso deixa duas frentes abertas. A primeira e o ataque de senha unica testada contra muitas contas: mil contas viram mil contadores separados, e nenhum deles chega perto do limite. A segunda e o bloqueio usado como arma: quem souber o e-mail de alguem erra cinco vezes e tranca a conta, e como o contador so zera num login bem-sucedido, que e justamente o que esta bloqueado, um script mantem a conta inutilizavel. Resolver exige contador por IP e por conta ao mesmo tempo, o que fica para depois do sistema estar em uso e com dado real de trafego.

**A inscricao por e-mail responde se existe conta com aquele endereco.** Para vincular um competidor ao ranking, o organizador digita o e-mail dele, e o sistema precisa dizer com clareza quando nao existe conta ativa com aquele endereco, senao o vinculo falharia em silencio e a pessoa sumiria do ranking sem ninguem entender por que. O efeito colateral e que qualquer organizador logado consegue descobrir se um e-mail tem conta aqui, tentando um por vez.

A exposicao e limitada: exige estar logado, revela apenas presenca de conta e nunca o nome de quem a tem, e nao ha como fechar sem quebrar o comportamento que o produto precisa ter. Fica registrada como aceita, e nao como esquecimento. Se um dia o cadastro deixar de ser aberto a qualquer um, isso muda de peso e merece revisao.

**A tela nao pode dizer "conta bloqueada".** Como o contador cria linha ate para e-mail que nao existe, uma mensagem especifica de bloqueio entregaria quais e-mails tem conta. A mensagem de erro do login e sempre a mesma.

**Todo cadastro vira organizador.** Nao existe, nesta etapa, forma de criar conta de jogador puro pela tela. O jogador entra no sistema como inscricao, com ou sem conta. Quando o login com Google chegar na etapa 2, a conta criada por ele nasce sem `e_organizador`, e essa separacao passa a valer.

**O login nao guarda o destino.** Quem abre um link direto e cai na tela de login volta para a lista de campeonatos, nao para o link que tentou abrir.

## 10. LGPD

Dados pessoais tratados: nome e e-mail do organizador, nome de exibicao do jogador e, quando ele tem conta, e-mail. Mais o IP no momento do aceite do termo, guardado como evidencia do aceite e para nada mais.

### Base legal, e por que o modelo de negocio muda a analise

Uma versao anterior deste documento dizia que nao havia uso para publicidade. Isso deixou de ser verdade quando o modelo passou a ser uso gratuito em troca de a MT figurar como apoiadora dos eventos. A frase foi corrigida porque uma declaracao de privacidade errada e pior do que declaracao nenhuma.

**Organizador:** execucao de contrato, com aceite de termo registrado. O contrato e explicito e e o proprio modelo: ele usa o sistema de graca, e em troca a MT aparece como apoiadora do campeonato que ele criar. O aceite fica gravado com versao, data e IP na tabela `aceites_termo`.

**Jogador inscrito pelo organizador, sem conta:** legitimo interesse na organizacao do torneio, com aviso na tela de inscricao. Vale distinguir duas coisas que se confundem facil: o nome do jogador aparece porque ele esta jogando, e nao para endossar a MT. A marca da MT esta na pagina como patrocinadora do evento, do mesmo jeito que um banner de patrocinador aparece atras da quadra num torneio presencial. O dado do jogador serve para rodar o torneio; a marca convive com ele na mesma pagina.

Essa distincao e o que sustenta o legitimo interesse. Se em algum momento o produto passar a usar nome ou imagem de jogador em peca publicitaria da MT, em anuncio, post ou material de venda, a base legal muda: ali passa a ser consentimento especifico, de cada jogador, e legitimo interesse nao cobre.

**Responsabilidade de informar.** O jogador sem conta nao aceita nada, porque nao tem cadastro. Quem responde por informar esse jogador e o organizador, e isso precisa estar escrito no termo que ele aceita e visivel na tela de inscricao. Nao e detalhe de texto: e o que liga o aviso a alguem que pode de fato dar o aviso.

### Pendencia que nao e tecnica

O modelo de negocio depende dessa analise estar certa, e ela foi feita por quem escreve software, nao por quem exerce advocacia. Antes de abrir o sistema para organizadores que nao sejam voce, o termo de uso e essa secao merecem leitura de um advogado. O custo de descobrir um erro aqui depois de o produto estar rodando com clientes reais e muito maior do que o de uma revisao agora.

### Minimizacao

Jogador convidado entra apenas com nome de exibicao. O sistema nao pede telefone, documento nem data de nascimento, porque nao precisa deles para nada.

### Direitos do titular

Exclusao a pedido troca o nome de exibicao por um identificador anonimo e mantem os placares, que sao dado estatistico do evento e nao identificam mais ninguem. A conta de usuario e desativada, nao apagada, conforme a regra do projeto que proibe exclusao.

### Politica de privacidade

Obrigatoria desde esta etapa, e nao mais a partir da etapa 2 como este documento dizia antes. A razao mudou: com finalidade publicitaria no modelo, a pessoa precisa poder ler onde seu dado vai antes de aceitar. O termo aceito no cadastro cumpre isso, e a politica fica acessivel por link em toda tela publica.

### Retencao

Dados de campeonato ficam enquanto o organizador mantiver a conta ativa. Nao ha venda de dado, nao ha compartilhamento com terceiros, e nao ha envio de dado de jogador para nenhum servico de publicidade. O ganho publicitario da MT vem de a marca dela aparecer, e nao de o dado de alguem sair do sistema. Essa e uma distincao que precisa continuar verdadeira conforme o produto crescer.

---

## 11. Testes

Tres arquivos em `testes/`, executados por linha de comando com o PHP do XAMPP, sem navegador e sem banco:

1. `teste_rodizio.php` confere que a tabela gera 28 duplas distintas, que cada jogador e parceiro dos outros exatamente uma vez, e que cada rodada tem os 8 jogadores sem repeticao.
2. `teste_sorteio.php` confere que a mesma semente devolve sempre a mesma ordem e que sementes diferentes devolvem ordens diferentes.
3. `teste_placar.php` confere a soma de games por jogador e a ordem da classificacao, incluindo os criterios de desempate.

Depois desses, um teste de ponta a ponta no navegador: criar campeonato, inscrever 8, sortear, lancar as 14 partidas, conferir classificacao e ranking.

---

## 12. Ambiente

Tres lugares, com papeis distintos:

| Pasta | Papel |
|---|---|
| `C:\COWORK\CODE\SUPER8` | O projeto. Fonte de verdade, repositorio git, e de onde a suite roda |
| `C:\xampp\htdocs\super8` | Copia de teste no XAMPP, para validar no navegador. Nunca se edita |
| `C:\COWORK\CODE\SUPER8\_PUBLICAR\enviar` | Pacote que vai para o servidor publicado |

Banco `super8` no MySQL do XAMPP. Repositorio no GitHub com um commit por alteracao.

Os dois destinos recebem copia a partir do projeto, nunca um do outro, por dois scripts em `ferramentas`. O de empacotamento roda a suite antes e aborta se algum teste falhar, porque um pacote com teste vermelho nao deveria existir e descobrir isso depois do FTP custa muito mais.

Ponto pendente: o repositorio ainda nao existe. Precisa ser criado antes do primeiro commit.
