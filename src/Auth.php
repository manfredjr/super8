<?php

final class Auth
{
    private const MAX_TENTATIVAS = 5;

    public static function cadastrar(PDO $pdo, string $nome, string $email, string $senha): int
    {
        $nome = trim($nome);
        $email = strtolower(trim($email));

        if ($nome === '') {
            throw new InvalidArgumentException('Informe o nome.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('E-mail invalido.');
        }
        if (strlen($senha) < 8) {
            throw new InvalidArgumentException('A senha precisa de pelo menos 8 caracteres.');
        }

        $busca = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $busca->execute([$email]);
        if ($busca->fetch() !== false) {
            throw new InvalidArgumentException('Ja existe conta com esse e-mail.');
        }

        $insere = $pdo->prepare(
            'INSERT INTO users (nome, email, senha_hash, e_organizador, ativo, criado_em)
             VALUES (?, ?, ?, 1, 1, NOW())'
        );
        $insere->execute([$nome, $email, password_hash($senha, PASSWORD_ARGON2ID)]);

        return (int) $pdo->lastInsertId();
    }

    public static function autenticar(PDO $pdo, string $email, string $senha): ?array
    {
        $email = strtolower(trim($email));

        $busca = $pdo->prepare('SELECT * FROM users WHERE email = ? AND ativo = 1');
        $busca->execute([$email]);
        $usuario = $busca->fetch();

        if ($usuario === false || $usuario['senha_hash'] === null) {
            return null;
        }
        if (!password_verify($senha, $usuario['senha_hash'])) {
            return null;
        }

        unset($usuario['senha_hash']);
        return $usuario;
    }

    public static function registrarFalha(PDO $pdo, string $email): void
    {
        $email = strtolower(trim($email));

        // Duas instrucoes de proposito. Num unico ON DUPLICATE KEY UPDATE, a segunda
        // atribuicao ja enxerga o valor novo da primeira, e o calculo do bloqueio sairia
        // adiantado em uma tentativa. Separado, cada passo le um estado sem ambiguidade.
        $incrementa = $pdo->prepare(
            'INSERT INTO tentativas_login (email, tentativas) VALUES (?, 1)
             ON DUPLICATE KEY UPDATE tentativas = tentativas + 1'
        );
        $incrementa->execute([$email]);

        // A espera comeca em 30 segundos na tentativa de numero MAX_TENTATIVAS
        // e dobra a cada falha seguinte, ate o teto de 15 minutos.
        $bloqueia = $pdo->prepare(
            'UPDATE tentativas_login
             SET bloqueado_ate = DATE_ADD(NOW(), INTERVAL LEAST(POW(2, tentativas - ?) * 30, 900) SECOND)
             WHERE email = ? AND tentativas >= ?'
        );
        $bloqueia->execute([self::MAX_TENTATIVAS, $email, self::MAX_TENTATIVAS]);
    }

    public static function limparFalhas(PDO $pdo, string $email): void
    {
        $comando = $pdo->prepare('DELETE FROM tentativas_login WHERE email = ?');
        $comando->execute([strtolower(trim($email))]);
    }

    /** Devolve a data e hora do fim do bloqueio, ou nulo se estiver liberado. */
    public static function bloqueadoAte(PDO $pdo, string $email): ?string
    {
        $busca = $pdo->prepare('SELECT bloqueado_ate FROM tentativas_login WHERE email = ? AND bloqueado_ate > NOW()');
        $busca->execute([strtolower(trim($email))]);
        $linha = $busca->fetch();

        return $linha === false ? null : $linha['bloqueado_ate'];
    }

}
