<?php

final class Ranking
{
    /**
     * Soma o desempenho de cada jogador com conta entre campeonatos encerrados.
     * As datas vem no formato AAAA-MM-DD ou nulas para nao filtrar.
     */
    public static function acumulado(PDO $pdo, ?string $de, ?string $ate): array
    {
        $condicoes = ["c.status = 'encerrado'", 'i.jogador_id IS NOT NULL', 'p.encerrada = 1'];
        $valores = [];

        if ($de !== null && $de !== '') {
            $condicoes[] = 'c.data_evento >= ?';
            $valores[] = $de;
        }
        if ($ate !== null && $ate !== '') {
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
            $eventos = (int) $linha['eventos'];
            $linhas[$indice]['saldo'] = (int) $linha['games'] - (int) $linha['sofridos'];
            $linhas[$indice]['media'] = $eventos > 0 ? round((int) $linha['games'] / $eventos, 1) : 0.0;
        }

        return $linhas;
    }
}
