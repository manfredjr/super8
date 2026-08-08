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
            trim($dados['nome'] ?? ''),
            $dados['data_evento'] ?? null,
            $dados['local'] ?? null,
            ($dados['custo'] ?? '') !== '' ? $dados['custo'] : null,
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
            trim($dados['nome'] ?? ''),
            $dados['data_evento'] ?? null,
            $dados['local'] ?? null,
            ($dados['custo'] ?? '') !== '' ? $dados['custo'] : null,
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

        // Trava a linha do campeonato para serializar inscricoes concorrentes:
        // sem isso, duas conexoes podem contar 7 inscritos ao mesmo tempo,
        // as duas passarem no limite de 8 e o campeonato terminar com 9
        // competidores, o que trava o sorteio para sempre (Rodizio exige
        // exatamente 8). Mesma forma de guarda de transacao de
        // Auth::registrarFalha: so abre e fecha transacao propria quando nao
        // ha nenhuma em andamento, para nunca aninhar.
        $transacaoPropria = !$pdo->inTransaction();
        if ($transacaoPropria) {
            $pdo->beginTransaction();
        }

        try {
            $trava = $pdo->prepare('SELECT id FROM campeonatos WHERE id = ? FOR UPDATE');
            $trava->execute([$campeonatoId]);

            if (count(self::listarInscricoes($pdo, $campeonatoId)) >= 8) {
                throw new RuntimeException('O campeonato ja tem 8 competidores.');
            }

            $comando = $pdo->prepare(
                'INSERT INTO inscricoes (campeonato_id, jogador_id, nome_exibicao) VALUES (?, ?, ?)'
            );
            try {
                $comando->execute([$campeonatoId, $jogadorId, $nomeExibicao]);
            } catch (PDOException $excecao) {
                // SQLSTATE 23000 aqui so pode ser a UNIQUE KEY uk_camp_nome
                // (campeonato_id, nome_exibicao): outro competidor do mesmo
                // campeonato ja usa esse nome de exibicao.
                if ($excecao->getCode() === '23000') {
                    throw new RuntimeException('Ja existe um competidor com esse nome.');
                }
                throw $excecao;
            }

            $id = (int) $pdo->lastInsertId();

            if ($transacaoPropria) {
                $pdo->commit();
            }
        } catch (Throwable $erro) {
            if ($transacaoPropria && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $erro;
        }

        return $id;
    }

    public static function listarInscricoes(PDO $pdo, int $campeonatoId): array
    {
        $busca = $pdo->prepare(
            'SELECT * FROM inscricoes WHERE campeonato_id = ? ORDER BY posicao_sorteio IS NULL, posicao_sorteio, id'
        );
        $busca->execute([$campeonatoId]);

        return $busca->fetchAll();
    }

    public static function removerInscricao(PDO $pdo, int $campeonatoId, int $inscricaoId): void
    {
        // A condicao campeonato_id = ? impede que o id de uma inscricao de
        // OUTRO campeonato seja apagado por aqui: sem ela, qualquer
        // organizador que descobrisse o id de uma inscricao alheia poderia
        // remove-la, mesmo sem ser dono daquele campeonato.
        $comando = $pdo->prepare('DELETE FROM inscricoes WHERE id = ? AND campeonato_id = ?');
        try {
            $comando->execute([$inscricaoId, $campeonatoId]);
        } catch (PDOException $excecao) {
            // SQLSTATE 23000 aqui e a FOREIGN KEY de partidas.dupla_*: o
            // sorteio ja rodou e existem partidas apontando para esta
            // inscricao. Sem esta captura, quem chama recebe um PDOException
            // cru, com nome de tabela e coluna do schema.
            if ($excecao->getCode() === '23000') {
                throw new RuntimeException('Nao e possivel remover um competidor depois do sorteio.');
            }
            throw $excecao;
        }
    }

    public static function temPlacarLancado(PDO $pdo, int $campeonatoId): bool
    {
        // p.encerrada = 1 sozinho nao basta: um placar pode ter sido gravado
        // (games_a/games_b preenchidos) sem a partida ter sido marcada como
        // encerrada. Se essa checagem so olhasse encerrada, sortear()
        // apagaria esse placar em silencio ao redesenhar o sorteio.
        $busca = $pdo->prepare(
            'SELECT COUNT(*) FROM partidas p
             JOIN rodadas r ON r.id = p.rodada_id
             WHERE r.campeonato_id = ?
               AND (p.encerrada = 1 OR p.games_a IS NOT NULL OR p.games_b IS NOT NULL)'
        );
        $busca->execute([$campeonatoId]);

        return (int) $busca->fetchColumn() > 0;
    }

    /**
     * Sorteia as posicoes, grava a semente e gera as 7 rodadas com as 14 partidas.
     * Devolve a semente usada.
     *
     * O sorteio e funcao pura da semente e do conjunto de ids das inscricoes:
     * mesma semente e mesmos ids inscritos sempre dao o mesmo chaveamento,
     * mesmo que o campeonato ja tenha sido sorteado antes. Essa garantia vale
     * enquanto os ids inscritos nao mudarem: remover um competidor e
     * reinscrever alguem com o mesmo nome de exibicao troca o id, e o
     * chaveamento muda mesmo com a mesma semente.
     *
     * Contrato de transacao: se quem chama ja tem uma transacao aberta (o
     * MariaDB nao aninha transacao), este metodo abre um SAVEPOINT proprio e
     * da ROLLBACK TO SAVEPOINT se algo falhar no meio do caminho, sem tocar
     * na transacao do chamador. Isso evita deixar o campeonato pela metade
     * (rodadas e partidas apagadas, status e semente ainda apontando para um
     * chaveamento que nao existe mais) caso uma excecao interrompa o
     * trabalho: o rollback do savepoint desfaz so o que este metodo fez, e a
     * transacao de quem chamou continua aberta, para essa pessoa decidir se
     * da commit ou rollback nela. Se ninguem tem transacao aberta, este
     * metodo abre e fecha a sua propria transacao normalmente.
     */
    public static function sortear(PDO $pdo, int $campeonatoId, ?int $semente = null): int
    {
        $transacaoPropria = !$pdo->inTransaction();
        if ($transacaoPropria) {
            $pdo->beginTransaction();
        } else {
            $pdo->exec('SAVEPOINT sortear_campeonato');
        }

        try {
            // Trava a linha do campeonato para serializar sorteios
            // concorrentes: sem isso, duas conexoes podem ler "8 inscritos,
            // sem placar" ao mesmo tempo e uma delas redesenhar por cima de
            // um placar que a outra acabou de gravar, ou apagar um sorteio
            // que a outra acabou de fazer.
            $trava = $pdo->prepare('SELECT id FROM campeonatos WHERE id = ? FOR UPDATE');
            $trava->execute([$campeonatoId]);

            $inscricoes = self::listarInscricoes($pdo, $campeonatoId);
            if (count($inscricoes) !== 8) {
                throw new RuntimeException('O sorteio precisa de exatamente 8 competidores.');
            }
            if (self::temPlacarLancado($pdo, $campeonatoId)) {
                throw new RuntimeException('Nao da para refazer o sorteio com placar ja lancado.');
            }

            $semente = $semente ?? Sorteio::gerarSemente();
            $ids = array_map(static fn (array $inscricao): int => (int) $inscricao['id'], $inscricoes);
            // A ordem dos ids antes do sorteio tem que depender so dos proprios ids,
            // nunca de um posicao_sorteio deixado por um sorteio anterior (isso e so
            // ordenacao de exibicao, de listarInscricoes). Sem este sort, refazer o
            // sorteio com a mesma semente para de reproduzir o mesmo chaveamento, e
            // a promessa de auditoria (mesma semente, mesmo resultado) cai por terra.
            sort($ids);
            $ordenados = Sorteio::ordenar($ids, $semente);

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

            // So promove o status para 'sorteado' quando o campeonato estava
            // em 'rascunho' ou ja em 'sorteado'. Um redesenho de auditoria
            // sobre um campeonato 'em_andamento' ou 'encerrado' nao pode
            // rebaixar o status silenciosamente.
            $statusAtual = self::buscar($pdo, $campeonatoId)['status'] ?? null;
            if (in_array($statusAtual, ['rascunho', 'sorteado'], true)) {
                $gravaSemente = $pdo->prepare(
                    "UPDATE campeonatos SET seed_sorteio = ?, status = 'sorteado' WHERE id = ?"
                );
            } else {
                $gravaSemente = $pdo->prepare('UPDATE campeonatos SET seed_sorteio = ? WHERE id = ?');
            }
            $gravaSemente->execute([$semente, $campeonatoId]);

            if ($transacaoPropria) {
                $pdo->commit();
            } else {
                $pdo->exec('RELEASE SAVEPOINT sortear_campeonato');
            }
        } catch (Throwable $erro) {
            if ($transacaoPropria) {
                // inTransaction() evita chamar rollBack() numa conexao que ja
                // perdeu a transacao sozinha (por exemplo, conexao caida no
                // meio do caminho): rollBack() nessa hora lancaria por conta
                // propria e esconderia o erro original atras de um erro
                // sobre transacao.
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } else {
                $pdo->exec('ROLLBACK TO SAVEPOINT sortear_campeonato');
            }
            throw $erro;
        }

        return $semente;
    }

    /**
     * Monta as 7 rodadas com nomes de exibicao para a tela.
     *
     * Cada item do array devolvido e uma rodada, com as chaves numero
     * (int, 1 a 7) e partidas (array com as 2 partidas da rodada). Cada
     * partida tem as chaves: id, quadra, games_a, games_b, encerrada, a1,
     * a2, b1, b2 (nome_exibicao dos 4 competidores da partida) e
     * dupla_a_j1, dupla_a_j2, dupla_b_j1, dupla_b_j2 (ids de inscricao
     * desses mesmos 4 competidores). Nao ha chave "ids": os quatro ids de
     * inscricao ja vem em dupla_a_j1/dupla_a_j2/dupla_b_j1/dupla_b_j2, que
     * cobrem a mesma necessidade.
     */
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
