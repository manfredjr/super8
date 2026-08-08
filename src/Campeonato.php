<?php

final class Campeonato
{
    public static function criar(PDO $pdo, int $organizadorId, array $dados): int
    {
        // A coluna status ja tem DEFAULT 'rascunho' no schema, entao fica fora do INSERT.
        $comando = $pdo->prepare(
            'INSERT INTO campeonatos (organizador_id, nome, data_evento, local, custo, descricao, criado_em)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $comando->execute([
            $organizadorId,
            trim($dados['nome']),
            $dados['data_evento'],
            $dados['local'] ?? null,
            $dados['custo'] !== '' ? $dados['custo'] : null,
            $dados['descricao'] ?? null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function buscar(PDO $pdo, int $id): ?array
    {
        $busca = $pdo->prepare('SELECT * FROM campeonatos WHERE id = ?');
        $busca->execute([$id]);
        $linha = $busca->fetch();

        return $linha === false ? null : $linha;
    }

    public static function listarDoOrganizador(PDO $pdo, int $organizadorId): array
    {
        $busca = $pdo->prepare('SELECT * FROM campeonatos WHERE organizador_id = ? ORDER BY data_evento DESC, id DESC');
        $busca->execute([$organizadorId]);

        return $busca->fetchAll();
    }

    public static function atualizar(PDO $pdo, int $id, array $dados): void
    {
        $comando = $pdo->prepare(
            'UPDATE campeonatos SET nome = ?, data_evento = ?, local = ?, custo = ?, descricao = ? WHERE id = ?'
        );
        $comando->execute([
            trim($dados['nome']),
            $dados['data_evento'],
            $dados['local'] ?? null,
            $dados['custo'] !== '' ? $dados['custo'] : null,
            $dados['descricao'] ?? null,
            $id,
        ]);
    }

    public static function inscrever(PDO $pdo, int $campeonatoId, string $nomeExibicao, ?int $jogadorId): int
    {
        $nomeExibicao = trim($nomeExibicao);
        if ($nomeExibicao === '') {
            throw new InvalidArgumentException('Informe o nome do competidor.');
        }

        if (count(self::listarInscricoes($pdo, $campeonatoId)) >= 8) {
            throw new RuntimeException('O campeonato ja tem 8 competidores.');
        }

        $comando = $pdo->prepare(
            'INSERT INTO inscricoes (campeonato_id, jogador_id, nome_exibicao) VALUES (?, ?, ?)'
        );
        $comando->execute([$campeonatoId, $jogadorId, $nomeExibicao]);

        return (int) $pdo->lastInsertId();
    }

    public static function listarInscricoes(PDO $pdo, int $campeonatoId): array
    {
        $busca = $pdo->prepare(
            'SELECT * FROM inscricoes WHERE campeonato_id = ? ORDER BY posicao_sorteio IS NULL, posicao_sorteio, id'
        );
        $busca->execute([$campeonatoId]);

        return $busca->fetchAll();
    }

    public static function removerInscricao(PDO $pdo, int $inscricaoId): void
    {
        $comando = $pdo->prepare('DELETE FROM inscricoes WHERE id = ?');
        $comando->execute([$inscricaoId]);
    }

    public static function temPlacarLancado(PDO $pdo, int $campeonatoId): bool
    {
        $busca = $pdo->prepare(
            'SELECT COUNT(*) FROM partidas p
             JOIN rodadas r ON r.id = p.rodada_id
             WHERE r.campeonato_id = ? AND p.encerrada = 1'
        );
        $busca->execute([$campeonatoId]);

        return (int) $busca->fetchColumn() > 0;
    }

    /**
     * Sorteia as posicoes, grava a semente e gera as 7 rodadas com as 14 partidas.
     * Devolve a semente usada.
     */
    public static function sortear(PDO $pdo, int $campeonatoId, ?int $semente = null): int
    {
        $inscricoes = self::listarInscricoes($pdo, $campeonatoId);
        if (count($inscricoes) !== 8) {
            throw new RuntimeException('O sorteio precisa de exatamente 8 competidores.');
        }
        if (self::temPlacarLancado($pdo, $campeonatoId)) {
            throw new RuntimeException('Nao da para refazer o sorteio com placar ja lancado.');
        }

        $semente = $semente ?? Sorteio::gerarSemente();
        $ids = array_map(static fn (array $inscricao): int => (int) $inscricao['id'], $inscricoes);
        $ordenados = Sorteio::ordenar($ids, $semente);

        // Esta funcao pode ser chamada dentro de uma transacao que quem chamou
        // ja abriu (o teste faz isso). So abrimos e fechamos transacao propria
        // quando nao ha nenhuma em andamento, para nunca aninhar.
        $transacaoPropria = !$pdo->inTransaction();
        if ($transacaoPropria) {
            $pdo->beginTransaction();
        }

        try {
            $apagaPartidas = $pdo->prepare(
                'DELETE p FROM partidas p JOIN rodadas r ON r.id = p.rodada_id WHERE r.campeonato_id = ?'
            );
            $apagaPartidas->execute([$campeonatoId]);

            $apagaRodadas = $pdo->prepare('DELETE FROM rodadas WHERE campeonato_id = ?');
            $apagaRodadas->execute([$campeonatoId]);

            $limpaPosicao = $pdo->prepare('UPDATE inscricoes SET posicao_sorteio = NULL WHERE campeonato_id = ?');
            $limpaPosicao->execute([$campeonatoId]);

            $gravaPosicao = $pdo->prepare('UPDATE inscricoes SET posicao_sorteio = ? WHERE id = ?');
            $porPosicao = [];
            foreach ($ordenados as $indice => $inscricaoId) {
                $posicao = $indice + 1;
                $gravaPosicao->execute([$posicao, $inscricaoId]);
                $porPosicao[$posicao] = $inscricaoId;
            }

            $criaRodada = $pdo->prepare('INSERT INTO rodadas (campeonato_id, numero) VALUES (?, ?)');
            $criaPartida = $pdo->prepare(
                'INSERT INTO partidas (rodada_id, quadra, dupla_a_j1, dupla_a_j2, dupla_b_j1, dupla_b_j2)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );

            foreach (Rodizio::RODADAS as $numero => $partidas) {
                $criaRodada->execute([$campeonatoId, $numero]);
                $rodadaId = (int) $pdo->lastInsertId();

                foreach ($partidas as $indice => $partida) {
                    [$duplaA, $duplaB] = $partida;
                    $criaPartida->execute([
                        $rodadaId,
                        $indice + 1,
                        $porPosicao[$duplaA[0]],
                        $porPosicao[$duplaA[1]],
                        $porPosicao[$duplaB[0]],
                        $porPosicao[$duplaB[1]],
                    ]);
                }
            }

            $gravaSemente = $pdo->prepare(
                "UPDATE campeonatos SET seed_sorteio = ?, status = 'sorteado' WHERE id = ?"
            );
            $gravaSemente->execute([$semente, $campeonatoId]);

            if ($transacaoPropria) {
                $pdo->commit();
            }
        } catch (Throwable $erro) {
            // inTransaction() evita chamar rollBack() numa conexao que ja
            // perdeu a transacao sozinha (por exemplo, conexao caida no meio
            // do caminho): rollBack() nessa hora lancaria por conta propria e
            // esconderia o erro original atras de um erro sobre transacao.
            if ($transacaoPropria && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $erro;
        }

        return $semente;
    }

    /** Monta as 7 rodadas com nomes de exibicao para a tela. */
    public static function chaveamento(PDO $pdo, int $campeonatoId): array
    {
        $busca = $pdo->prepare(
            'SELECT r.numero, p.id, p.quadra, p.games_a, p.games_b, p.encerrada,
                    a1.nome_exibicao AS a1, a2.nome_exibicao AS a2,
                    b1.nome_exibicao AS b1, b2.nome_exibicao AS b2,
                    p.dupla_a_j1, p.dupla_a_j2, p.dupla_b_j1, p.dupla_b_j2
             FROM partidas p
             JOIN rodadas r ON r.id = p.rodada_id
             JOIN inscricoes a1 ON a1.id = p.dupla_a_j1
             JOIN inscricoes a2 ON a2.id = p.dupla_a_j2
             JOIN inscricoes b1 ON b1.id = p.dupla_b_j1
             JOIN inscricoes b2 ON b2.id = p.dupla_b_j2
             WHERE r.campeonato_id = ?
             ORDER BY r.numero, p.quadra'
        );
        $busca->execute([$campeonatoId]);

        $rodadas = [];
        foreach ($busca->fetchAll() as $linha) {
            $numero = (int) $linha['numero'];
            if (!isset($rodadas[$numero])) {
                $rodadas[$numero] = ['numero' => $numero, 'partidas' => []];
            }
            $rodadas[$numero]['partidas'][] = $linha;
        }

        return array_values($rodadas);
    }
}
