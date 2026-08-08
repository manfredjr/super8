<?php

final class Ranking
{
    /**
     * Soma o desempenho de cada jogador com conta entre campeonatos encerrados.
     *
     * As datas vem no formato AAAA-MM-DD. Nula, string vazia, ou qualquer
     * valor que nao bata exatamente com o formato AAAA-MM-DD (por exemplo
     * um parametro de tela mal formado) e tratado como "sem filtro" nesse
     * lado, exatamente como nulo - nunca deixado passar cru para dentro da
     * clausula WHERE, onde uma data invalida faria o MariaDB truncar o
     * valor num aviso silencioso e alargar a janela para tudo, em vez de
     * filtrar o periodo pedido.
     *
     * Cada linha devolvida tem as chaves jogador_id, nome, eventos, jogadas
     * (total de partidas somadas, 7 por evento disputado), games, sofridos,
     * saldo e media, ordenadas por games (do maior para o menor, com nome
     * em ordem alfabetica como desempate).
     */
    public static function acumulado(PDO $pdo, ?string $de, ?string $ate): array
    {
        $condicoes = ["c.status = 'encerrado'", 'i.jogador_id IS NOT NULL', 'p.encerrada = 1'];
        $valores = [];

        if (self::dataValida($de)) {
            $condicoes[] = 'c.data_evento >= ?';
            $valores[] = $de;
        }
        if (self::dataValida($ate)) {
            $condicoes[] = 'c.data_evento <= ?';
            $valores[] = $ate;
        }

        $onde = implode(' AND ', $condicoes);

        $sql = "
            SELECT u.id AS jogador_id,
                   u.nome,
                   COUNT(DISTINCT c.id) AS eventos,
                   COUNT(*) AS jogadas,
                   SUM(CASE WHEN i.id IN (p.dupla_a_j1, p.dupla_a_j2) THEN p.games_a ELSE p.games_b END) AS games,
                   SUM(CASE WHEN i.id IN (p.dupla_a_j1, p.dupla_a_j2) THEN p.games_b ELSE p.games_a END) AS sofridos
            FROM inscricoes i
            JOIN users u ON u.id = i.jogador_id
            JOIN campeonatos c ON c.id = i.campeonato_id
            JOIN rodadas r ON r.campeonato_id = c.id
            JOIN partidas p ON p.rodada_id = r.id
                 AND i.id IN (p.dupla_a_j1, p.dupla_a_j2, p.dupla_b_j1, p.dupla_b_j2)
            WHERE {$onde}
            GROUP BY u.id, u.nome
            ORDER BY games DESC, u.nome ASC
        ";

        $busca = $pdo->prepare($sql);
        $busca->execute($valores);

        $linhas = $busca->fetchAll();
        foreach ($linhas as $indice => $linha) {
            // SUM() no MariaDB devolve DECIMAL, que o PDO traz como string
            // quando PDO::ATTR_EMULATE_PREPARES esta desligado (config/db.php
            // liga o driver nativo). Sem este cast, games e sofridos saem
            // como string ("82") enquanto jogador_id/eventos/jogadas/saldo ja
            // saem como int e media como float - um formato de linha
            // inconsistente que engana qualquer comparacao com === ou
            // qualquer serializacao para JSON de quem consumir este array.
            $games = (int) $linha['games'];
            $sofridos = (int) $linha['sofridos'];
            $eventos = (int) $linha['eventos'];

            $linhas[$indice]['games'] = $games;
            $linhas[$indice]['sofridos'] = $sofridos;
            $linhas[$indice]['saldo'] = $games - $sofridos;
            $linhas[$indice]['media'] = $eventos > 0 ? round($games / $eventos, 1) : 0.0;
        }

        return $linhas;
    }

    /**
     * Uma data e valida para filtrar quando bate exatamente com o formato
     * AAAA-MM-DD. Nao valida se o dia/mes formam uma data real (o MariaDB ja
     * rejeita isso na comparacao "c.data_evento >= ?"/"<= ?" do jeito comum,
     * sem truncar nem alargar a janela em silencio) - so garante que o valor
     * tem a FORMA certa antes de decidir se filtra ou nao, para nunca deixar
     * passar cru um valor de outro formato (por exemplo "01/03/2026" ou lixo
     * qualquer vindo de uma tela) que o MariaDB aceitaria com truncamento.
     */
    private static function dataValida(?string $data): bool
    {
        return $data !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) === 1;
    }
}
