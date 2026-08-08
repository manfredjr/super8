<?php

/**
 * Embaralhamento reproduzivel.
 *
 * O sorteio grava a semente no campeonato. Rodando de novo com a mesma semente,
 * a ordem sai identica, o que torna o chaveamento auditavel.
 * O algoritmo e Fisher-Yates escrito na mao, e nao a funcao shuffle,
 * para que o resultado nao dependa de detalhe interno de versao do PHP.
 */
final class Sorteio
{
    public static function gerarSemente(): int
    {
        return random_int(1, 2147483647);
    }

    /**
     * @param int[] $ids
     * @return int[] os mesmos ids em ordem sorteada
     */
    public static function ordenar(array $ids, int $semente): array
    {
        $ids = array_values($ids);
        mt_srand($semente, MT_RAND_MT19937);

        for ($i = count($ids) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            $guarda = $ids[$i];
            $ids[$i] = $ids[$j];
            $ids[$j] = $guarda;
        }

        return $ids;
    }
}
