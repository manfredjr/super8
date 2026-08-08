<?php

/**
 * Tabela fixa do rodizio Super 8.
 *
 * Cada rodada tem 2 partidas, uma por quadra. Cada partida tem duas duplas,
 * identificadas pela posicao do jogador no sorteio (1 a 8).
 * Ao longo das 7 rodadas cada posicao e parceira de cada outra exatamente uma vez.
 */
final class Rodizio
{
    public const RODADAS = [
        1 => [[[1, 8], [2, 7]], [[3, 6], [4, 5]]],
        2 => [[[2, 8], [1, 3]], [[4, 7], [5, 6]]],
        3 => [[[3, 8], [2, 4]], [[1, 5], [6, 7]]],
        4 => [[[4, 8], [3, 5]], [[2, 6], [1, 7]]],
        5 => [[[5, 8], [4, 6]], [[3, 7], [1, 2]]],
        6 => [[[6, 8], [5, 7]], [[1, 4], [2, 3]]],
        7 => [[[7, 8], [1, 6]], [[2, 5], [3, 4]]],
    ];

    /** @return string[] as 28 duplas no formato "menor-maior" */
    public static function todasAsDuplas(): array
    {
        $duplas = [];
        foreach (self::RODADAS as $partidas) {
            foreach ($partidas as $partida) {
                foreach ($partida as $dupla) {
                    $par = $dupla;
                    sort($par);
                    $duplas[] = $par[0] . '-' . $par[1];
                }
            }
        }
        return $duplas;
    }

    /** @return int[] as 8 posicoes que jogam na rodada, em ordem crescente */
    public static function jogadoresDaRodada(int $numero): array
    {
        $jogadores = [];
        foreach (self::RODADAS[$numero] as $partida) {
            foreach ($partida as $dupla) {
                $jogadores = array_merge($jogadores, $dupla);
            }
        }
        sort($jogadores);
        return $jogadores;
    }
}
