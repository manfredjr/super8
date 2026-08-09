<?php

final class Placar
{
    /**
     * Grava o placar de uma partida de um campeonato.
     *
     * Grava um placar, ou seja, muda o estado do torneio - por isso segue o
     * mesmo contrato documentado no docblock de Campeonato::sortear: QUALQUER
     * codigo que grave um placar precisa travar a linha do campeonato (SELECT
     * ... FOR UPDATE) ANTES de qualquer outra trava, na mesma ordem que
     * sortear(), inscrever() e removerInscricao() ja usam (campeonatos
     * primeiro, sempre). Sem essa trava, um redesenho de sorteio concorrente
     * poderia apagar as partidas (DELETE) enquanto este metodo ainda esta no
     * meio de gravar um placar nelas, ou a guarda de sortear() (que le com
     * FOR UPDATE se ja existe algum placar lancado) poderia nao enxergar
     * esta escrita a tempo.
     *
     * $campeonatoId e parametro explicito (nao resolvido a partir da
     * partida) por um motivo de ordem de travas, nao so de conveniencia: uma
     * versao anterior deste metodo resolvia o campeonato com um JOIN
     * partidas -> rodadas ANTES de travar o campeonato, o que trava a
     * PARTIDA primeiro e o CAMPEONATO depois - a ordem invertida em relacao
     * a sortear()/inscrever()/removerInscricao(). Isso e uma receita de
     * deadlock real: a guarda de placar de sortear() trava TODAS as partidas
     * do proprio campeonato (via COUNT(*) ... FOR UPDATE) logo depois de
     * travar a linha do campeonato, no caminho normal, sem precisar de
     * nenhum caso especial de tabela quase vazia. Se sortear() trava o
     * campeonato e espera pela partida (que gravar() ja travou), e gravar()
     * trava a partida e espera pelo campeonato (que sortear() ja travou), as
     * duas transacoes ficam esperando uma pela outra para sempre, ate o
     * MariaDB detectar o ciclo e destruir uma das duas (SQLSTATE 40001,
     * erro 1213) - e um deadlock derruba a TRANSACAO INTEIRA no servidor,
     * nao so a instrucao que causou o erro; se gravar() estiver rodando
     * dentro da transacao de quem chama, todo o trabalho anterior dessa
     * transacao e perdido, mesmo que $pdo->inTransaction() continue
     * devolvendo true (esse metodo so reflete o que o PDO acha que tem, nao
     * pergunta ao servidor) e um commit() seguinte nao avise que nao havia
     * mais nada para comitar. Recebendo $campeonatoId pronto, este metodo
     * pode travar o campeonato PRIMEIRO em todo caminho, sem excecao,
     * restaurando a ordem "campeonatos primeiro" de verdade.
     *
     * A confirmacao de que a partida pertence a ESTE campeonato (linha
     * abaixo, com WHERE p.id = ? AND r.campeonato_id = ?) continua sendo uma
     * leitura TRAVADA (FOR UPDATE), pelo mesmo motivo de antes: sob
     * REPEATABLE READ, uma transacao de quem chama cujo retrato seja
     * anterior a criacao desta partida nunca a enxergaria com leitura comum,
     * mesmo que ela ja exista de verdade no banco. Uma leitura travada
     * ignora esse retrato. Essa mesma consulta tambem cobre o controle de
     * posse de graca: se o id de partida informado pertencer a OUTRO
     * campeonato, a consulta nao acha nada e a excecao dispara com a MESMA
     * mensagem de "nao existe" - quem chama (por exemplo, um controller que
     * ja resolveu e autorizou o campeonato antes de chamar este metodo) nao
     * consegue usar a mensagem de erro para descobrir se aquele id de
     * partida existe em outro campeonato alheio.
     */
    public static function gravar(PDO $pdo, int $campeonatoId, int $partidaId, int $gamesA, int $gamesB, int $usuarioId): void
    {
        if ($gamesA < 0 || $gamesA > 99 || $gamesB < 0 || $gamesB > 99) {
            throw new InvalidArgumentException('Os games precisam ficar entre 0 e 99.');
        }

        // Mesma forma de guarda de transacao de Campeonato::sortear/inscrever:
        // so abre e fecha transacao propria quando nao ha nenhuma em
        // andamento, para nunca aninhar.
        $transacaoPropria = !$pdo->inTransaction();
        if ($transacaoPropria) {
            $pdo->beginTransaction();
        }

        try {
            $trava = $pdo->prepare('SELECT status FROM campeonatos WHERE id = ? FOR UPDATE');
            $trava->execute([$campeonatoId]);
            $status = $trava->fetchColumn();
            if ($status === false) {
                throw new RuntimeException('O campeonato informado não existe.');
            }
            // Depois de encerrado, o placar historico nao pode mais mudar:
            // o ranking acumulado ja contou este evento, e editar um game
            // aqui reescreveria esse resultado retroativamente sem deixar
            // rastro.
            if ($status === 'encerrado') {
                throw new RuntimeException('O campeonato está encerrado e o placar não pode mais ser alterado.');
            }

            $confirmaPartida = $pdo->prepare(
                'SELECT p.id FROM partidas p JOIN rodadas r ON r.id = p.rodada_id
                 WHERE p.id = ? AND r.campeonato_id = ? FOR UPDATE'
            );
            $confirmaPartida->execute([$partidaId, $campeonatoId]);
            if ($confirmaPartida->fetchColumn() === false) {
                throw new RuntimeException('A partida informada não existe neste campeonato.');
            }

            $comando = $pdo->prepare(
                'UPDATE partidas SET games_a = ?, games_b = ?, encerrada = 1, gravado_por = ?, gravado_em = NOW()
                 WHERE id = ?'
            );
            $comando->execute([$gamesA, $gamesB, $usuarioId, $partidaId]);

            if ($transacaoPropria) {
                $pdo->commit();
            }
        } catch (Throwable $erro) {
            if ($transacaoPropria && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $erro;
        }
    }

    public static function classificacao(PDO $pdo, int $campeonatoId): array
    {
        // ORDER BY id: garante que a ordem de entrada em classificarLinhas seja
        // deterministica entre chamadas. Sem isso, duas execucoes de
        // classificacao() no mesmo campeonato podiam devolver as linhas de
        // classificarLinhas construidas em ordens diferentes (a ordem de um
        // SELECT sem ORDER BY nao e garantida pelo MariaDB), o que por sua vez
        // podia fazer um grupo empatado por games/saldo/vitorias/confronto sair
        // em posicoes de podio diferentes de uma chamada para a outra.
        $buscaInscricoes = $pdo->prepare('SELECT id, nome_exibicao FROM inscricoes WHERE campeonato_id = ? ORDER BY id');
        $buscaInscricoes->execute([$campeonatoId]);

        $buscaPartidas = $pdo->prepare(
            'SELECT p.dupla_a_j1, p.dupla_a_j2, p.dupla_b_j1, p.dupla_b_j2, p.games_a, p.games_b, p.encerrada
             FROM partidas p JOIN rodadas r ON r.id = p.rodada_id
             WHERE r.campeonato_id = ?'
        );
        $buscaPartidas->execute([$campeonatoId]);

        return self::classificarLinhas($buscaInscricoes->fetchAll(), $buscaPartidas->fetchAll());
    }

    /**
     * Soma os games de cada jogador e ordena a classificacao.
     * Funcao pura, sem banco, para poder ser testada por linha de comando.
     *
     * Ordem: games ganhos, saldo, vitorias, confronto direto, nome.
     */
    public static function classificarLinhas(array $inscricoes, array $partidas): array
    {
        $linhas = [];
        foreach ($inscricoes as $inscricao) {
            $id = (int) $inscricao['id'];
            $linhas[$id] = [
                'inscricao_id' => $id,
                'nome'         => $inscricao['nome_exibicao'],
                'games'        => 0,
                'sofridos'     => 0,
                'saldo'        => 0,
                'vitorias'     => 0,
                'jogadas'      => 0,
                'empatado'     => false,
            ];
        }

        // Games que cada jogador fez contra cada adversario, para o confronto direto.
        $confronto = [];

        foreach ($partidas as $partida) {
            if ((int) $partida['encerrada'] !== 1 || $partida['games_a'] === null || $partida['games_b'] === null) {
                continue;
            }

            $gamesA = (int) $partida['games_a'];
            $gamesB = (int) $partida['games_b'];
            $duplaA = [(int) $partida['dupla_a_j1'], (int) $partida['dupla_a_j2']];
            $duplaB = [(int) $partida['dupla_b_j1'], (int) $partida['dupla_b_j2']];

            foreach ([[$duplaA, $gamesA, $gamesB, $duplaB], [$duplaB, $gamesB, $gamesA, $duplaA]] as $lado) {
                [$dupla, $feitos, $tomados, $adversarios] = $lado;
                foreach ($dupla as $jogador) {
                    if (!isset($linhas[$jogador])) {
                        continue;
                    }
                    $linhas[$jogador]['games'] += $feitos;
                    $linhas[$jogador]['sofridos'] += $tomados;
                    $linhas[$jogador]['jogadas']++;
                    if ($feitos > $tomados) {
                        $linhas[$jogador]['vitorias']++;
                    }
                    foreach ($adversarios as $adversario) {
                        $confronto[$jogador][$adversario] = ($confronto[$jogador][$adversario] ?? 0) + $feitos;
                    }
                }
            }
        }

        foreach ($linhas as $id => $linha) {
            $linhas[$id]['saldo'] = $linha['games'] - $linha['sofridos'];
        }

        $linhas = array_values($linhas);

        usort($linhas, static function (array $um, array $outro) use ($confronto): int {
            $comparacao = $outro['games'] <=> $um['games'];
            if ($comparacao !== 0) {
                return $comparacao;
            }

            $comparacao = $outro['saldo'] <=> $um['saldo'];
            if ($comparacao !== 0) {
                return $comparacao;
            }

            $comparacao = $outro['vitorias'] <=> $um['vitorias'];
            if ($comparacao !== 0) {
                return $comparacao;
            }

            $doUm = $confronto[$um['inscricao_id']][$outro['inscricao_id']] ?? 0;
            $doOutro = $confronto[$outro['inscricao_id']][$um['inscricao_id']] ?? 0;
            $comparacao = $doOutro <=> $doUm;
            if ($comparacao !== 0) {
                return $comparacao;
            }

            return strcmp($um['nome'], $outro['nome']);
        });

        // Marca quem fica empatado: agrupa por games/saldo/vitorias, e dentro
        // de cada grupo tenta usar o confronto direto para desempatar de
        // verdade.
        //
        // Uma comparacao par a par (so olhar se CADA DUPLA tem o mesmo
        // confronto) nao basta: ela so pega o caso de um par com confronto
        // igual, mas nao pega um CICLO de tres ou mais - A bate B, B bate C
        // e C bate A - onde toda comparacao de par e par e "diferente" (cada
        // par tem um vencedor de confronto), mas o grupo inteiro continua
        // sem uma ordem real, porque a comparacao nao e transitiva. Nesse
        // caso a comparacao par a par acima marcava empatado=false para os
        // tres, e o usort() (que so enxerga comparacoes de dois em dois, sem
        // saber que esta dentro de um ciclo) produzia uma ordem que dependia
        // so da sequencia interna do algoritmo de ordenacao - podendo mudar
        // so por causa da ordem de entrada dos jogadores, sem nenhuma
        // mudanca no placar.
        //
        // Por isso o desempate por confronto so vale se ele ORDENA O GRUPO
        // INTEIRO de forma estrita: cada membro do grupo bate um numero
        // DIFERENTE de outros membros do MESMO grupo (0, 1, 2, ...). Se dois
        // membros baterem o mesmo tanto de gente dentro do grupo -- inclusive
        // o caso de dois jogadores empatados entre si (cada um bate 0 dos
        // outros do grupo, ou os dois se enfrentaram e empataram no
        // confronto), inclusive um ciclo (todos batem exatamente 1 cada) --
        // o grupo inteiro fica marcado como empatado, e a ordem que sobra do
        // usort() e so um jeito de mostrar as linhas, nunca uma classificacao
        // real entre elas.
        $grupos = [];
        foreach ($linhas as $indice => $linha) {
            $chave = $linha['games'] . '|' . $linha['saldo'] . '|' . $linha['vitorias'];
            $grupos[$chave][] = $indice;
        }

        foreach ($grupos as $indicesDoGrupo) {
            if (count($indicesDoGrupo) < 2) {
                continue;
            }

            // Quantos outros membros do MESMO grupo cada jogador bate no
            // confronto direto entre eles.
            $contagemDeVitoriasNoGrupo = [];
            foreach ($indicesDoGrupo as $indice) {
                $id = $linhas[$indice]['inscricao_id'];
                $contagem = 0;
                foreach ($indicesDoGrupo as $outroIndice) {
                    if ($outroIndice === $indice) {
                        continue;
                    }
                    $outroId = $linhas[$outroIndice]['inscricao_id'];
                    $doUm = $confronto[$id][$outroId] ?? 0;
                    $doOutro = $confronto[$outroId][$id] ?? 0;
                    if ($doUm > $doOutro) {
                        $contagem++;
                    }
                }
                $contagemDeVitoriasNoGrupo[$indice] = $contagem;
            }

            $ordenaOGrupoInteiro = count(array_unique($contagemDeVitoriasNoGrupo)) === count($contagemDeVitoriasNoGrupo);
            if (!$ordenaOGrupoInteiro) {
                foreach ($indicesDoGrupo as $indice) {
                    $linhas[$indice]['empatado'] = true;
                }
            }
        }

        return $linhas;
    }
}
