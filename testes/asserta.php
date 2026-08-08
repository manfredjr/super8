<?php

final class Teste
{
    private static int $passou = 0;
    private static int $falhou = 0;

    public static function igual(mixed $esperado, mixed $obtido, string $descricao): void
    {
        if ($esperado === $obtido) {
            self::$passou++;
            echo "  ok    {$descricao}\n";
            return;
        }
        self::$falhou++;
        echo "  FALHA {$descricao}\n";
        echo "        esperado: " . var_export($esperado, true) . "\n";
        echo "        obtido:   " . var_export($obtido, true) . "\n";
    }

    public static function verdade(bool $condicao, string $descricao): void
    {
        self::igual(true, $condicao, $descricao);
    }

    public static function resumo(): int
    {
        echo "\n" . self::$passou . " passaram, " . self::$falhou . " falharam\n";
        return self::$falhou === 0 ? 0 : 1;
    }
}
