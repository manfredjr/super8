<?php

/**
 * Regra de excecao deste projeto, valida aqui e em Campeonato: quando quem
 * chama mandou um valor que ele mesmo poderia ter validado antes (formato,
 * tamanho, faixa), a excecao e InvalidArgumentException. Quando e o estado
 * ja guardado no banco que recusa a operacao (duplicidade, limite atingido,
 * transicao invalida), a excecao e RuntimeException.
 */
final class Auth
{
    private const MAX_TENTATIVAS = 5;

    // Hash Argon2id fixo, gerado uma unica vez, para igualar o tempo de resposta
    // quando o e-mail nao existe ou nao tem senha. Nunca corresponde a senha
    // nenhuma, so serve para pagar o mesmo custo computacional do caminho feliz.
    private const HASH_FALSO = '$argon2id$v=19$m=65536,t=4,p=1$Vno5a1Y5dHZtRUdvWHFlbQ$3jgaGSh1ZkeHgn9P8t826Hy1OrSmIZG+SHIpcAxnAVo';

    /**
     * Nao gerencia transacao nenhuma: esta funcao nao abre nem fecha
     * transacao propria, diferente de registrarFalha/Campeonato::inscrever/
     * Campeonato::sortear/Placar::gravar. Quem chama pode envolve-la numa
     * transacao propria quando precisar, e no cadastro com aceite de termo
     * quem chama DEVE envolver: a linha de users e a linha de aceites_termo
     * (registrarAceite) precisam comitar juntas, ou a base legal em que o
     * modelo de negocio se apoia pode faltar para uma conta que existe de
     * verdade no banco.
     */
    public static function cadastrar(PDO $pdo, string $nome, string $email, string $senha): int
    {
        $nome = trim($nome);
        $email = strtolower(trim($email));

        if ($nome === '') {
            throw new InvalidArgumentException('Informe o nome.');
        }
        if (mb_strlen($nome) > 120) {
            throw new InvalidArgumentException('O nome pode ter no maximo 120 caracteres.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('E-mail invalido.');
        }
        // A coluna users.email e VARCHAR(160). Sem essa checagem o banco corta o
        // endereco em silencio (o servidor nao roda em modo estrito), o cadastro
        // parece dar certo e o usuario nunca mais consegue entrar com o e-mail
        // que digitou.
        if (mb_strlen($email) > 160) {
            throw new InvalidArgumentException('O e-mail pode ter no maximo 160 caracteres.');
        }
        if (mb_strlen($senha) < 8) {
            throw new InvalidArgumentException('A senha precisa de pelo menos 8 caracteres.');
        }

        $busca = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $busca->execute([$email]);
        if ($busca->fetch() !== false) {
            // Duplicidade e estado do banco que recusa a operacao, nao valor
            // mal formado que quem chama poderia ter validado sozinho: pela
            // regra do topo do arquivo, e RuntimeException.
            throw new RuntimeException('Ja existe conta com esse e-mail.');
        }

        $insere = $pdo->prepare(
            'INSERT INTO users (nome, email, senha_hash, e_organizador, ativo, criado_em)
             VALUES (?, ?, ?, 1, 1, NOW())'
        );
        try {
            $insere->execute([$nome, $email, password_hash($senha, PASSWORD_ARGON2ID)]);
        } catch (PDOException $excecao) {
            // A checagem acima nao fecha a corrida entre dois cadastros
            // simultaneos com o mesmo e-mail: os dois podem passar pelo SELECT
            // antes de qualquer um inserir. Quem perder a corrida esbarra na
            // restricao UNIQUE da coluna e cai aqui. O indice SQLSTATE 23000 e
            // violacao de integridade, e a unica causa possivel deste INSERT e
            // o e-mail duplicado, entao a mensagem continua a mesma do caminho
            // normal.
            if ($excecao->getCode() === '23000') {
                throw new RuntimeException('Ja existe conta com esse e-mail.');
            }
            throw $excecao;
        }

        return (int) $pdo->lastInsertId();
    }

    /**
     * Contrato para quem chama esta funcao: confira bloqueadoAte antes de
     * chamar autenticar; se o retorno for nulo, chame registrarFalha; se vier
     * o usuario, chame limparFalhas. Nada dentro desta funcao impoe isso.
     */
    public static function autenticar(PDO $pdo, string $email, string $senha): ?array
    {
        $email = strtolower(trim($email));

        // Lista explicita de colunas, e nao SELECT *: o retorno desta funcao
        // vai direto para $_SESSION, entao uma coluna nova em users nunca deve
        // aparecer aqui sem decisao explicita de codigo.
        $busca = $pdo->prepare(
            'SELECT id, google_id, nome, email, senha_hash, foto_url, e_organizador, ativo, criado_em
             FROM users WHERE email = ? AND ativo = 1'
        );
        $busca->execute([$email]);
        $usuario = $busca->fetch();

        if ($usuario === false || $usuario['senha_hash'] === null) {
            // Verifica contra o hash falso mesmo sem usuario para gastar o
            // mesmo tempo do caminho em que a senha existe e e conferida de
            // verdade. Sem isso, um e-mail inexistente responde em fracao de
            // milissegundo e um e-mail cadastrado paga o custo do Argon2id,
            // uma diferenca facil de medir e que revela quais enderecos tem
            // conta.
            password_verify($senha, self::HASH_FALSO);
            return null;
        }
        if (!password_verify($senha, $usuario['senha_hash'])) {
            return null;
        }

        // senha_hash precisa estar na lista de colunas la em cima porque
        // password_verify() usa ele duas linhas acima. Este unset() e a UNICA
        // coisa que tira o hash do array antes dele seguir para $_SESSION: nao
        // e uma camada redundante, e o unico lugar que impede o vazamento. Se
        // esta linha sumir, o teste que confere array_key_exists('senha_hash', ...)
        // === false quebra, e a senha com hash e tudo passa a viver na sessao.
        unset($usuario['senha_hash']);
        return $usuario;
    }

    public static function registrarFalha(PDO $pdo, string $email): void
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Sem essa guarda, qualquer texto vira uma linha em
            // tentativas_login, e a propria pagina de login, que chama esta
            // funcao a cada falha, vira um jeito anonimo de encher a tabela.
            return;
        }

        // Esta funcao pode ser chamada dentro de uma transacao que quem chamou
        // ja abriu (o teste faz isso). So abrimos e fechamos transacao propria
        // quando nao ha nenhuma em andamento, para nunca aninhar.
        $transacaoPropria = !$pdo->inTransaction();
        if ($transacaoPropria) {
            $pdo->beginTransaction();
        }

        try {
            // Duas instrucoes de proposito. Nao e para evitar que a segunda
            // atribuicao de um UPDATE com dois SET enxergue o valor novo da
            // primeira: se o servidor avalia essa segunda atribuicao contra o
            // valor antigo ou o novo de tentativas muda entre MySQL e MariaDB,
            // entao um unico comando com as duas atribuicoes teria resultado
            // dependente do motor. Separado, cada passo le um estado sem
            // ambiguidade, com o mesmo resultado nos dois bancos.
            // bloqueado_ate comeca ja expirado (NOW()) em toda falha, nunca
            // nulo: bloqueadoAte() so considera bloqueado o que for MAIOR
            // que NOW(), entao um valor ja expirado nao bloqueia ninguem -
            // mas deixa de ser nulo, e a limpeza (mais abaixo, que filtra
            // por bloqueado_ate) passa a alcancar esta linha depois de um
            // dia, em vez de crescer para sempre com uma linha por e-mail
            // que nunca chegou a MAX_TENTATIVAS. A tentativa que realmente
            // bloqueia (a instrucao seguinte) sobrescreve este valor com o
            // horario real do bloqueio.
            $incrementa = $pdo->prepare(
                'INSERT INTO tentativas_login (email, tentativas, bloqueado_ate) VALUES (?, 1, NOW())
                 ON DUPLICATE KEY UPDATE tentativas = tentativas + 1, bloqueado_ate = NOW()'
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

            // Limpeza: linha cujo bloqueio expirou ha mais de um dia nao serve
            // mais para nada, e nada mais neste sistema a apaga.
            $limpa = $pdo->prepare(
                'DELETE FROM tentativas_login WHERE bloqueado_ate < DATE_SUB(NOW(), INTERVAL 1 DAY)'
            );
            $limpa->execute();

            if ($transacaoPropria) {
                $pdo->commit();
            }
        } catch (Throwable $excecao) {
            // inTransaction() evita chamar rollBack() numa conexao que ja
            // perdeu a transacao sozinha (por exemplo, conexao caida no meio
            // do caminho): rollBack() nessa hora lancaria por conta propria e
            // esconderia o erro original atras de um erro sobre transacao.
            if ($transacaoPropria && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $excecao;
        }
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

    /**
     * Grava o aceite do termo de uso. Aceitar a mesma versao duas vezes nao
     * e erro: a UNIQUE KEY uk_user_versao existe justamente para tornar isso
     * idempotente, entao um 1062 aqui vira sucesso silencioso, do mesmo
     * jeito que outros pontos do projeto tratam SQLSTATE 23000 esperado em
     * vez de deixa-lo subir cru.
     */
    public static function registrarAceite(PDO $pdo, int $userId, string $versao, ?string $ip): void
    {
        $comando = $pdo->prepare(
            'INSERT INTO aceites_termo (user_id, versao, aceito_em, ip) VALUES (?, ?, NOW(), ?)'
        );
        try {
            $comando->execute([$userId, $versao, $ip]);
        } catch (PDOException $excecao) {
            if ($excecao->getCode() !== '23000') {
                throw $excecao;
            }
        }
    }

    /** Devolve a versao aceita mais recente por este usuario, ou nulo se nenhuma. */
    public static function versaoAceita(PDO $pdo, int $userId): ?string
    {
        $busca = $pdo->prepare(
            'SELECT versao FROM aceites_termo WHERE user_id = ? ORDER BY aceito_em DESC, id DESC LIMIT 1'
        );
        $busca->execute([$userId]);
        $linha = $busca->fetch();

        return $linha === false ? null : $linha['versao'];
    }
}
