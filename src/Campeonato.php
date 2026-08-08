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

            // Leitura travada (FOR UPDATE), nao listarInscricoes(): o mesmo
            // buraco do sortear() sob REPEATABLE READ existe aqui. Se a
            // transacao de quem chama ja tiver feito qualquer leitura comum
            // antes (por exemplo, para render a tela de inscritos), uma
            // contagem via listarInscricoes() continuaria presa aquela foto
            // antiga mesmo depois da trava acima, e nao veria um 8o
            // competidor que outra conexao acabou de comitar - permitindo um
            // 9o, estado que o sorteio nunca mais aceita.
            $travaContagem = $pdo->prepare('SELECT COUNT(*) FROM inscricoes WHERE campeonato_id = ? FOR UPDATE');
            $travaContagem->execute([$campeonatoId]);
            if ((int) $travaContagem->fetchColumn() >= 8) {
                throw new RuntimeException('O campeonato ja tem 8 competidores.');
            }

            $comando = $pdo->prepare(
                'INSERT INTO inscricoes (campeonato_id, jogador_id, nome_exibicao) VALUES (?, ?, ?)'
            );
            try {
                $comando->execute([$campeonatoId, $jogadorId, $nomeExibicao]);
            } catch (PDOException $excecao) {
                // Este INSERT pode falhar por tres motivos diferentes, e os
                // tres caem na mesma classe SQLSTATE 23000: a UNIQUE KEY
                // uk_camp_nome (nome duplicado), a FOREIGN KEY
                // fk_insc_camp (campeonato_id inexistente) e a FOREIGN KEY
                // fk_insc_jogador (jogador_id inexistente). So a primeira e
                // "nome ja existe"; olhamos o codigo de erro do driver
                // (1062 = entrada duplicada), nao a classe SQLSTATE, para
                // nao confundir um jogador_id invalido com nome duplicado.
                // Qualquer outro 23000 sobe cru, sem disfarcar o problema
                // real.
                if (($excecao->errorInfo[1] ?? null) === 1062) {
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
        // Trava a linha do campeonato ANTES de tocar em inscricoes, na mesma
        // ordem que inscrever() e sortear() usam (campeonatos primeiro,
        // sempre). Se esta funcao travasse so a linha de inscricoes (ou
        // nenhuma), uma transacao que chamasse removerInscricao() e depois
        // inscrever()/sortear() adquiriria as travas em ordem invertida em
        // relacao a uma transacao concorrente que chamasse
        // inscrever()/sortear() primeiro - o cenario classico de deadlock
        // entre duas transacoes que travam as mesmas duas linhas em ordens
        // opostas. Manter uma ordem unica entre os tres metodos evita isso
        // por construcao.
        $transacaoPropria = !$pdo->inTransaction();
        if ($transacaoPropria) {
            $pdo->beginTransaction();
        }

        try {
            $trava = $pdo->prepare('SELECT id FROM campeonatos WHERE id = ? FOR UPDATE');
            $trava->execute([$campeonatoId]);

            // A condicao campeonato_id = ? impede que o id de uma inscricao
            // de OUTRO campeonato seja apagado por aqui: sem ela, qualquer
            // organizador que descobrisse o id de uma inscricao alheia
            // poderia remove-la, mesmo sem ser dono daquele campeonato.
            $comando = $pdo->prepare('DELETE FROM inscricoes WHERE id = ? AND campeonato_id = ?');
            try {
                $comando->execute([$inscricaoId, $campeonatoId]);
            } catch (PDOException $excecao) {
                // Diferente do INSERT de inscrever(), aqui a classe SQLSTATE
                // 23000 inteira pode virar a mesma mensagem sem risco de
                // confundir o problema: um DELETE em inscricoes so tem UM
                // jeito de esbarrar numa restricao de integridade, a
                // FOREIGN KEY de partidas.dupla_* (o sorteio ja rodou e
                // existem partidas apontando para esta inscricao). Nao ha
                // UNIQUE KEY nem outra FOREIGN KEY que um DELETE possa
                // violar aqui. Se um dia esta tabela ganhar outra restricao
                // que um DELETE possa disparar, esta captura precisa ser
                // estreitada do mesmo jeito que a de inscrever() foi.
                if ($excecao->getCode() === '23000') {
                    throw new RuntimeException('Nao e possivel remover um competidor depois do sorteio.');
                }
                throw $excecao;
            }

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
     *
     * Contrato de concorrencia: o servidor roda em REPEATABLE READ. A trava
     * SELECT ... FOR UPDATE na linha do campeonato serializa este metodo
     * contra qualquer OUTRO codigo que tambem trave a mesma linha antes de
     * escrever, mas nao contra leituras comuns feitas antes dela: sob
     * REPEATABLE READ, uma leitura comum (sem FOR UPDATE) que a transacao de
     * quem chamou ja tenha feito antes desta chamada continua enxergando a
     * foto de antes, mesmo depois da trava ser adquirida. Por isso as duas
     * checagens de guarda abaixo usam leitura travada, e nao
     * listarInscricoes()/temPlacarLancado(). QUALQUER codigo futuro que
     * grave um placar (partidas.games_a/games_b) OU que mude o conjunto de
     * inscricoes de um campeonato (inscrever/remover/qualquer coisa que
     * altere quem esta inscrito) PRECISA travar a mesma linha do campeonato
     * antes de escrever, e nessa mesma ordem (campeonatos primeiro): a trava
     * so serializa contra quem trava a mesma linha, entao uma escrita sem
     * essa trava fica invisivel para a guarda deste metodo, mesmo com a
     * leitura travada; e uma ordem de trava diferente entre metodos que
     * mexem nas mesmas duas linhas (campeonatos e inscricoes/partidas) e
     * receita para deadlock. inscrever() e removerInscricao() ja seguem
     * este contrato.
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
            // que a outra acabou de fazer. Ja le o status atual junto (usado
            // mais abaixo), para nao precisar de uma segunda ida ao banco
            // so para isso.
            $trava = $pdo->prepare('SELECT status FROM campeonatos WHERE id = ? FOR UPDATE');
            $trava->execute([$campeonatoId]);
            $statusAtual = $trava->fetchColumn();
            $statusAtual = $statusAtual === false ? null : $statusAtual;

            // Leitura travada (FOR UPDATE), nao listarInscricoes(): sob
            // REPEATABLE READ, uma leitura comum reaproveitaria a foto de
            // antes da trava acima, mesmo que outra conexao ja tenha
            // inscrito ou removido alguem depois dessa foto. So uma leitura
            // travada ve o commit mais recente. Os ids que saem daqui sao os
            // mesmos que alimentam o embaralhamento logo abaixo, entao a
            // contagem da guarda e o conjunto sorteado nunca podem divergir.
            $travaInscricoes = $pdo->prepare('SELECT id FROM inscricoes WHERE campeonato_id = ? FOR UPDATE');
            $travaInscricoes->execute([$campeonatoId]);
            $idsInscritos = array_map(
                static fn (array $linha): int => (int) $linha['id'],
                $travaInscricoes->fetchAll()
            );
            if (count($idsInscritos) !== 8) {
                throw new RuntimeException('O sorteio precisa de exatamente 8 competidores.');
            }

            // Mesmo raciocinio para o placar: leitura travada com a mesma
            // condicao alargada de temPlacarLancado (games preenchidos OU
            // encerrada = 1), para nao redesenhar por cima de um placar que
            // uma leitura comum, presa a uma foto antiga, nao enxergaria.
            $travaPlacar = $pdo->prepare(
                'SELECT COUNT(*) FROM partidas p
                 JOIN rodadas r ON r.id = p.rodada_id
                 WHERE r.campeonato_id = ?
                   AND (p.encerrada = 1 OR p.games_a IS NOT NULL OR p.games_b IS NOT NULL)
                 FOR UPDATE'
            );
            $travaPlacar->execute([$campeonatoId]);
            if ((int) $travaPlacar->fetchColumn() > 0) {
                throw new RuntimeException('Nao da para refazer o sorteio com placar ja lancado.');
            }

            $semente = $semente ?? Sorteio::gerarSemente();
            $ids = $idsInscritos;
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
            // em 'rascunho' ou ja em 'sorteado' (lido junto com a trava, no
            // comeco desta funcao). Um redesenho de auditoria sobre um
            // campeonato 'em_andamento' ou 'encerrado' nao pode rebaixar o
            // status silenciosamente.
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
            } elseif ($pdo->inTransaction()) {
                // Mesmo cuidado do ramo acima: numa conexao que ja perdeu a
                // transacao sozinha, ROLLBACK TO SAVEPOINT lancaria por
                // conta propria (o savepoint tambem some quando a transacao
                // some) e esconderia o erro original atras de um erro sobre
                // savepoint inexistente.
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
     * partida tem as chaves: numero (o mesmo numero de rodada do item pai,
     * repetido em cada partida porque a linha do SELECT carrega r.numero),
     * id, quadra, games_a, games_b, encerrada, a1, a2, b1, b2
     * (nome_exibicao dos 4 competidores da partida) e dupla_a_j1,
     * dupla_a_j2, dupla_b_j1, dupla_b_j2 (ids de inscricao desses mesmos 4
     * competidores). Nao ha chave "ids": os quatro ids de inscricao ja vem
     * em dupla_a_j1/dupla_a_j2/dupla_b_j1/dupla_b_j2, que cobrem a mesma
     * necessidade.
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
