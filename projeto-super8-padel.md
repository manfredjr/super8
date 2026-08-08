# Projeto Super 8 — Plataforma de Torneios de Padel

Documento de projeto e requisitos. Sistema web para criar, gerenciar e ranquear torneios de padel no formato Super 8, rodando na infraestrutura VPS própria, com Laravel, PHP e MySQL.

---

## 1. Visão geral

O sistema tem duas camadas que se complementam:

1. **Gestor de eventos** — qualquer organizador cria um campeonato, monta uma página pública de divulgação e recebe inscrições/contatos.
2. **Motor do Super 8** — para cada evento, o sistema gera o sorteio, monta as rodadas automaticamente, registra placares e fecha a classificação.

Por cima das duas, um **ranking acumulado** soma o desempenho de cada jogador ao longo de vários eventos, com filtro por período.

---

## 2. O formato Super 8 (regra)

Oito jogadores jogam **com e contra todos**, trocando de parceiro a cada rodada. São **7 rodadas**, cada uma com **2 partidas** (2 quadras rodando ao mesmo tempo, 4 jogadores por partida). Ao final das 7 rodadas, cada jogador terá sido parceiro de cada um dos outros 7 **exatamente uma vez**.

A pontuação é **individual**: cada jogador acumula os games que venceu ao longo das partidas. Quem somar mais games no evento é o campeão.

---

## 3. Perfis de usuário

| Perfil | O que faz |
|---|---|
| **Organizador** | Cria campeonatos, cadastra competidores, lança placares, publica a página do evento, responde contatos |
| **Jogador** | Entra com Google, participa de eventos, consulta chaveamento, placar e ranking |
| **Visitante** | Acessa a página pública do evento sem login (divulgação e inscrição) |

---

## 4. Autenticação

- Login **exclusivamente via conta Google** (OAuth), usando **Laravel Socialite**.
- **Sem senha e sem formulário de cadastro**: na primeira entrada, o sistema cria a conta sozinho a partir dos dados que o Google devolve.
- Guardar no banco: `google_id`, nome, e-mail e foto. Sem armazenar senha.

**Pré-requisitos de configuração:**
- Projeto no **Google Cloud Console** com credenciais OAuth (Client ID + Client Secret).
- URL de **callback** cadastrada exatamente igual à do sistema (ex.: `https://super8.seudominio.com.br/auth/google/callback`).
- Funciona em **subdomínio** sem problema; o certificado **Let's Encrypt** cobre o subdomínio.

**Custo:** zero. OAuth do Google é gratuito nesse volume e o Socialite é aberto.

---

## 5. Infraestrutura

Aproveita o que já existe:

- VPS com PHP, servidor web e banco relacional.
- Único preparo novo: credenciais OAuth no Google Cloud + certificado no subdomínio.

**Escala e custo:** até ~1.000 usuários e dados apenas estatísticos (texto, número, placar) é carga leve — poucos megabytes de banco mesmo com muitos eventos acumulados. Cabe no VPS atual sem custo extra relevante.

---

## 6. Modelagem de dados (tabelas)

Esboço das tabelas principais:

**`users`** — jogadores e organizadores
`id`, `google_id`, `nome`, `email`, `foto_url`, `criado_em`

**`campeonatos`** — cada evento Super 8
`id`, `organizador_id` (→ users), `nome`, `data_evento`, `local`, `custo`, `descricao`, `status` (rascunho / publicado / em andamento / encerrado), `seed_sorteio`, `criado_em`

**`inscricoes`** — competidores de um campeonato
`id`, `campeonato_id`, `jogador_id` (→ users, pode ser nulo se convidado sem conta), `nome_exibicao`, `posicao_sorteio` (1 a 8)

**`rodadas`** — as 7 rodadas de um campeonato
`id`, `campeonato_id`, `numero` (1 a 7)

**`partidas`** — as 2 partidas por rodada
`id`, `rodada_id`, `dupla_a_j1`, `dupla_a_j2`, `dupla_b_j1`, `dupla_b_j2`, `games_a`, `games_b`, `encerrada`

**`ranking_acumulado`** (ou uma *view* calculada) — desempenho por jogador entre eventos
`jogador_id`, `periodo`, `eventos_disputados`, `games_totais`, `pontos`

> Observação: o ranking acumulado pode ser uma tabela materializada (recalculada ao fechar cada evento) ou uma *view*/consulta agregada. Para ~1.000 usuários, consulta agregada em cima de `partidas` resolve com folga.

---

## 7. Sorteio e chaveamento

O "sorteio" só decide a **posição inicial** (1 a 8) de cada jogador na tabela de rodízio. Depois disso, o rodízio é **fixo**.

- Embaralhar os 8 jogadores **uma vez**, usando uma **semente (`seed`) registrada** no campeonato.
- Guardar a semente garante que o sorteio seja **reproduzível e auditável**: se alguém questionar o chaveamento, dá pra provar que foi limpo.

**Tabela de rodízio fixa** (posições 1 a 8; cada par é uma dupla; "×" separa as duas duplas que se enfrentam):

| Rodada | Partida 1 | Partida 2 |
|---|---|---|
| 1 | 1+8 × 2+7 | 3+6 × 4+5 |
| 2 | 2+8 × 1+3 | 4+7 × 5+6 |
| 3 | 3+8 × 2+4 | 1+5 × 6+7 |
| 4 | 4+8 × 3+5 | 2+6 × 1+7 |
| 5 | 5+8 × 4+6 | 3+7 × 1+2 |
| 6 | 6+8 × 5+7 | 1+4 × 2+3 |
| 7 | 7+8 × 1+6 | 2+5 × 3+4 |

Nessa tabela cada jogador é parceiro de cada um dos outros **exatamente uma vez** ao longo das 7 rodadas. O sistema só encaixa os nomes sorteados nas posições 1 a 8 — a estrutura das duplas nunca muda.

---

## 8. Pontuação e ranking

**Por evento:** soma os games vencidos por cada jogador nas partidas em que jogou. Fecha a classificação do campeonato.

**Ranking acumulado:** soma o desempenho do jogador em vários eventos, com **filtro por período** (mês, temporada, ano). Mesma lógica de pontuação acumulada já usada no rank2 (RoboCore). Definir a métrica de pontos (games totais, média por evento, ou um sistema de pontos por colocação) na fase de requisitos.

---

## 9. Gestão de eventos (página pública)

Cada campeonato tem **duas caras**:

**Página pública** (sem login) — divulgação:
- Nome do evento, data, local
- Custo / gratuito
- Como se inscrever
- **Canal de contato direto com o organizador** pela própria plataforma

**Área interna** (com login) — operação:
- Cadastro de competidores
- Sorteio e chaveamento
- Lançamento de placares
- Classificação do evento

O organizador preenche as informações e o sistema publica a página sozinho. Suporta **múltiplos campeonatos** simultâneos.

---

## 10. Fases de execução

1. **Requisitos e modelagem** — perfis de usuário e as tabelas do banco.
2. **Infraestrutura** — VPS já pronto; registrar OAuth no Google Cloud e certificado no subdomínio.
3. **Autenticação** — Socialite + login Google com criação automática de conta.
4. **Gestão de eventos** — múltiplos campeonatos, cada um com página pública de divulgação e contato com o organizador.
5. **Núcleo do Super 8** — criação do campeonato, cadastro dos jogadores, sorteio com semente e geração automática das 7 rodadas.
6. **Pontuação e ranking** — soma dos games, classificação do evento e ranking acumulado por período.
7. **Interface e testes** — telas simples pro celular (uso na quadra) e um teste de ponta a ponta antes de abrir pros usuários.

---

## 11. Resumo de custos

| Item | Custo |
|---|---|
| Login com Google (OAuth) | Gratuito |
| Laravel Socialite | Gratuito (open source) |
| VPS, PHP, banco, servidor web | Já pago (infra atual) |
| Certificado SSL (Let's Encrypt) | Gratuito |
| Armazenamento (dados estatísticos) | Desprezível |

**Custo adicional do projeto: praticamente zero.**
