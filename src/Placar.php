<?php

final class Placar
{
    /**
     * Grava o placar de uma partida.
     *
     * Grava um placar, ou seja, muda o estado do torneio - por isso segue o
     * mesmo contrato documentado no docblock de Campeonato::sortear: QUALQUER
     * codigo que grave um placar precisa travar a linha do campeonato (SELECT
     * ... FOR UPDATE) antes de escrever, na mesma ordem que sortear(),
     * inscrever() e removerInscricao() ja usam (campeonatos primeiro). Sem
     * essa trava, um redesenho de sorteio concorrente poderia apagar as
     * partidas (DELETE) enquanto este metodo ainda esta no meio de gravar um
     * placar nelas, ou a guarda de sortear() (que le com FOR UPDATE se ja
     * existe algum placar lancado) poderia nao enxergar esta escrita a tempo.
     *
     * A partida so aponta para a rodada (partidas.rodada_id), nao para o
     * campeonato direto - por isso o campeonato precisa ser resolvido antes
     * da trava, com um JOIN partidas -> rodadas. Se o id de partida nao
     * existir, nao ha o que travar nem o que atualizar: o UPDATE final
     * simplesmente nao afeta nenhuma linha, do mesmo jeito que aconteceria
     * sem a trava.
     */
    public static function gravar(PDO $pdo, int $partidaId, int $gamesA, int $gamesB, int $usuarioId): void
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
            $buscaCampeonato = $pdo->prepare(
                'SELECT r.campeonato_id FROM partidas p JOIN rodadas r ON r.id = p.rodada_id WHERE p.id = ?'
            );
            $buscaCampeonato->execute([$partidaId]);
            $campeonatoId = $buscaCampeonato->fetchColumn();

            if ($campeonatoId !== false) {
                $trava = $pdo->prepare('SELECT id FROM campeonatos WHERE id = ? FOR UPDATE');
                $trava->execute([(int) $campeonatoId]);
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
        $buscaInscricoes = $pdo->prepare('SELECT id, nome_exibicao FROM inscricoes WHERE campeonato_id = ?');
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

        // Marca quem empatou em tudo, inclusive no confronto direto.
        $total = count($linhas);
        for ($i = 0; $i < $total; $i++) {
            for ($j = $i + 1; $j < $total; $j++) {
                $mesmaConta = $linhas[$i]['games'] === $linhas[$j]['games']
                    && $linhas[$i]['saldo'] === $linhas[$j]['saldo']
                    && $linhas[$i]['vitorias'] === $linhas[$j]['vitorias'];

                $doUm = $confronto[$linhas[$i]['inscricao_id']][$linhas[$j]['inscricao_id']] ?? 0;
                $doOutro = $confronto[$linhas[$j]['inscricao_id']][$linhas[$i]['inscricao_id']] ?? 0;

                if ($mesmaConta && $doUm === $doOutro) {
                    $linhas[$i]['empatado'] = true;
                    $linhas[$j]['empatado'] = true;
                }
            }
        }

        return $linhas;
    }
}
