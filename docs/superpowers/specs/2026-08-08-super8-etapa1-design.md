# Super 8 Padel - Especificacao da etapa 1

Data: 08/08/2026
Escopo: motor do Super 8 mais ranking acumulado, sem login com Google.

---

## 1. O que entra e o que fica de fora

Entra nesta etapa:

- Cadastro e login do organizador por e-mail e senha.
- Criacao de campeonato com nome, data, local, custo e descricao.
- Cadastro dos 8 competidores de cada campeonato.
- Sorteio com semente gravada e geracao automatica das 7 rodadas.
- Lancamento de placar das 14 partidas.
- Classificacao do evento por games vencidos.
- Ranking acumulado entre eventos, com filtro por periodo.

Fica para depois:

- Login com Google via OAuth (etapa 2).
- Pagina publica de divulgacao e canal de contato com o organizador.
- Foto de perfil do jogador.

---

## 2. Decisoes de arquitetura

### PHP puro com PDO, sem framework

O sistema tem 7 tabelas e cerca de 8 telas. Laravel exigiria Composer instalado, DocumentRoot apontando para a pasta `public` e um cuidado extra na subida para o VPS. Em PHP puro a pasta vai inteira para `htdocs` no teste e para `_PUBLICAR\enviar` na producao, por FTP.

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
    csrf.php          geracao e conferencia de token
  src/
    Sorteio.php       embaralha os 8 jogadores a partir da semente
    Rodizio.php       tabela fixa das 7 rodadas
    Campeonato.php    criacao, inscricao, geracao das rodadas
    Placar.php        gravacao de placar e classificacao do evento
    Ranking.php       agregacao entre eventos por periodo
    Auth.php          cadastro, login, logout, checagem de dono
  views/
    layout.php  login.php  campeonatos.php  campeonato_form.php
    inscricoes.php  chaveamento.php  placar.php  classificacao.php
    ranking.php
  public/
    index.php  login.php  logout.php  campeonato.php
    inscricoes.php  sortear.php  placar.php  ranking.php
    css/  js/
  sql/
    schema.sql        criacao das tabelas
    seed_demo.sql     campeonato de exemplo para teste
  testes/
    teste_rodizio.php  teste_sorteio.php  teste_placar.php
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

Chave unica em (campeonato_id, posicao_sorteio) e em (campeonato_id, nome_exibicao).

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

As partidas apontam para `inscricoes`, nao para `users`. Isso permite jogador convidado sem conta e mantem o placar preso ao evento.

O ranking acumulado nao tem tabela. Sai de consulta agregada sobre `partidas` juntando `inscricoes` e `users`, filtrada por intervalo de datas.

### tentativas_login

`email` PK, `tentativas` TINYINT, `bloqueado_ate` DATETIME nulo. Tabela de apoio para o bloqueio por forca bruta descrito na secao 9. Nao guarda dado pessoal alem do e-mail digitado na tentativa e pode ser esvaziada a qualquer momento.

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

O sorteio so acontece com exatamente 8 inscricoes e muda o status do campeonato para `sorteado`. Refazer o sorteio apaga rodadas e partidas, entao so e permitido enquanto nenhum placar tiver sido lancado.

---

## 7. Placar e classificacao

Cada partida recebe `games_a` e `games_b`. Os dois jogadores da dupla A somam `games_a` na conta individual, os da dupla B somam `games_b`.

Classificacao do evento: soma dos games de cada jogador nas 7 partidas que disputou, em ordem decrescente. Criterios de desempate, em ordem: saldo de games (ganhos menos sofridos), depois numero de partidas vencidas, depois confronto direto.

**Quando o sistema admite que nao sabe quem ficou na frente.** Confronto direto entre dois jogadores resolve bem. Entre tres ou mais, nao. Se A ganha de B, B ganha de C e C ganha de A, nao existe ordem correta: qualquer uma que a tela mostrar e invencao. A regra, entao, e por grupo e nao por par. Jogadores que empatam em games, saldo e vitorias formam um grupo. Dentro do grupo, conta-se quantos companheiros de grupo cada um venceu no confronto direto. Se essas contagens forem todas diferentes, o confronto direto ordenou o grupo e a classificacao segue normal. Se houver qualquer repeticao, seja por empate entre dois ou por ciclo entre tres, o grupo inteiro aparece marcado como empatado.

A alternativa seria ordenar pelo que veio primeiro do banco, que muda entre consultas e da a duas pessoas com o mesmo dado dois podios diferentes. Melhor a tela dizer que empatou.

Ranking acumulado: mesma soma, agora entre campeonatos encerrados dentro do periodo escolhido. As colunas sao jogador, eventos disputados, games totais, media de games por evento e melhor colocacao. O filtro de periodo tem atalhos para mes atual, ano atual e intervalo livre.

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
| Acesso a campeonato alheio | Toda leitura e escrita confere se `organizador_id` bate com o usuario da sessao. A checagem fica em `Auth.php` e e chamada no topo de cada ponto de entrada. |
| Forca bruta no login | Contador de tentativas por e-mail com espera crescente apos a quinta falha. |
| Placar adulterado | Validacao de faixa nos games, de 0 a 99, e registro de quem gravou com data e hora. |

Nao existe upload de arquivo nesta etapa, o que elimina uma classe inteira de risco.

### Limites conhecidos e aceitos nesta etapa

Levantados na revisao da camada de autenticacao. Ficam registrados porque sao decisoes, nao esquecimentos.

**O contador de tentativas e por e-mail, sem dimensao por IP.** Isso deixa duas frentes abertas. A primeira e o ataque de senha unica testada contra muitas contas: mil contas viram mil contadores separados, e nenhum deles chega perto do limite. A segunda e o bloqueio usado como arma: quem souber o e-mail de alguem erra cinco vezes e tranca a conta, e como o contador so zera num login bem-sucedido, que e justamente o que esta bloqueado, um script mantem a conta inutilizavel. Resolver exige contador por IP e por conta ao mesmo tempo, o que fica para depois do sistema estar em uso e com dado real de trafego.

**A tela nao pode dizer "conta bloqueada".** Como o contador cria linha ate para e-mail que nao existe, uma mensagem especifica de bloqueio entregaria quais e-mails tem conta. A mensagem de erro do login e sempre a mesma.

**Todo cadastro vira organizador.** Nao existe, nesta etapa, forma de criar conta de jogador puro pela tela. O jogador entra no sistema como inscricao, com ou sem conta. Quando o login com Google chegar na etapa 2, a conta criada por ele nasce sem `e_organizador`, e essa separacao passa a valer.

**O login nao guarda o destino.** Quem abre um link direto e cai na tela de login volta para a lista de campeonatos, nao para o link que tentou abrir.

## 10. LGPD

Dados pessoais tratados: nome e e-mail do organizador, nome de exibicao do jogador e, quando ele tem conta, e-mail.

Base legal: execucao de contrato para o organizador, que se cadastra por vontade propria. Para o jogador inscrito por terceiro, legitimo interesse na organizacao do torneio, com aviso visivel na tela de inscricao informando que o nome aparecera em chaveamento, classificacao e ranking publico.

Minimizacao: jogador convidado entra apenas com nome de exibicao. O sistema nao pede telefone, documento nem data de nascimento, porque nao precisa deles para nada.

Direitos do titular: exclusao a pedido troca o nome de exibicao por um identificador anonimo e mantem os placares, que sao dado estatistico do evento e nao identificam mais ninguem. A conta de usuario e desativada, nao apagada, conforme a regra do projeto que proibe exclusao.

Politica de privacidade: obrigatoria a partir da etapa 2, quando o Google passar a devolver e-mail e foto. Nesta etapa fica um aviso curto de tratamento de dados na tela de cadastro.

Retencao: dados de campeonato ficam enquanto o organizador mantiver a conta ativa. Nao ha compartilhamento com terceiros nem uso para publicidade.

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
