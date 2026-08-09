<?php

/**
 * Validacoes puras, sem banco: cada metodo recebe um valor e devolve o
 * valor limpo, ou lanca InvalidArgumentException. Nenhum metodo aqui toca
 * PDO, $_POST ou $_SESSION, entao pode ser chamado tanto de outras classes
 * de src/ quanto de testes de linha de comando sem depender de conexao
 * nenhuma - a mesma ideia de Placar::classificarLinhas.
 */
final class Validador
{
    /**
     * Uma data e valida quando bate exatamente com o formato AAAA-MM-DD E o
     * dia/mes formam uma data real (checkdate). As duas checagens sao
     * necessarias: um valor como "2026-13-45" bate no formato mas nao existe
     * no calendario, e o MariaDB NAO rejeita isso numa comparacao de data -
     * ele trunca o valor com um aviso silencioso (1292, Truncated incorrect
     * datetime value) em vez de recusar. So garantir a FORMA (regex) nao
     * fecha esse buraco; garantir a forma E o calendario fecha.
     */
    public static function dataValida(?string $data): bool
    {
        if ($data === null || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $data, $partes) !== 1) {
            return false;
        }

        return checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1]);
    }

    /**
     * Faz trim, recusa vazio e recusa texto maior que o limite. Devolve o
     * valor limpo. Usa mb_strlen, nao strlen: o limite e de caracteres, nao
     * de bytes, senao um nome com acento seria recusado ou aceito pelo
     * motivo errado.
     */
    public static function textoObrigatorio(?string $valor, int $limite): string
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            throw new InvalidArgumentException('Preencha este campo.');
        }
        if (mb_strlen($valor) > $limite) {
            throw new InvalidArgumentException("Este campo pode ter no máximo {$limite} caracteres.");
        }

        return $valor;
    }

    /**
     * Mesma regra de textoObrigatorio, mas string vazia (ou nula) vira nulo
     * em vez de erro: o campo e opcional, e o limite de tamanho so vale
     * quando algo de fato foi informado.
     */
    public static function textoOpcional(?string $valor, int $limite): ?string
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }
        if (mb_strlen($valor) > $limite) {
            throw new InvalidArgumentException("Este campo pode ter no máximo {$limite} caracteres.");
        }

        return $valor;
    }
}
