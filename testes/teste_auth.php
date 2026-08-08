<?php

require __DIR__ . '/asserta.php';
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../src/Auth.php';

echo "Auth\n";

$pdo = db();
$pdo->beginTransaction();

// Usada pelo teste de corrida de cadastro, mais abaixo: essa conexao roda
// fora da transacao principal (autocommit), entao o rollBack do fim do
// teste nao alcanca o que ela grava. Guardamos aqui para limpar por conta
// propria, depois que a transacao principal ja tiver liberado qualquer
// travamento que a corrida deixe para tras.
$pdo2 = null;
$emailCorrida = null;

try {
    $email = 'teste' . uniqid() . '@exemplo.com';

    $id = Auth::cadastrar($pdo, 'Organizador Teste', $email, 'senhaforte123');
    Teste::verdade($id > 0, 'cadastrar devolve o id do usuario');

    $busca = $pdo->prepare('SELECT senha_hash, e_organizador FROM users WHERE id = ?');
    $busca->execute([$id]);
    $linha = $busca->fetch();
    Teste::verdade($linha['senha_hash'] !== 'senhaforte123', 'a senha nao fica em texto no banco');
    Teste::verdade(password_verify('senhaforte123', $linha['senha_hash']), 'o hash confere com a senha');
    Teste::igual(1, (int) $linha['e_organizador'], 'quem se cadastra vira organizador');

    $infoHash = password_get_info($linha['senha_hash']);
    Teste::igual('argon2id', $infoHash['algoName'], 'o hash usa Argon2id, nao outro algoritmo');

    $usuario = Auth::autenticar($pdo, $email, 'senhaforte123');
    Teste::verdade($usuario !== null, 'autentica com a senha certa');
    Teste::igual($id, (int) $usuario['id'], 'devolve o usuario correto');
    Teste::verdade(!array_key_exists('senha_hash', $usuario), 'o retorno de autenticar nao tem a chave senha_hash (nem nula)');

    Teste::igual(null, Auth::autenticar($pdo, $email, 'senhaerrada'), 'recusa a senha errada');
    Teste::igual(null, Auth::autenticar($pdo, 'naoexiste@exemplo.com', 'qualquer'), 'recusa e-mail inexistente');

    $erro = null;
    try {
        Auth::cadastrar($pdo, 'Curta', 'curta' . $email, '1234567');
    } catch (InvalidArgumentException $excecao) {
        $erro = $excecao->getMessage();
    }
    Teste::verdade($erro !== null, 'recusa senha com menos de 8 caracteres');

    // I1 (rodada de revisao): e-mail duplicado e estado do banco que recusa
    // a operacao (duplicidade), nao valor mal formado - pela regra do topo
    // de Auth.php, a excecao e RuntimeException, nao InvalidArgumentException.
    $erro = null;
    try {
        Auth::cadastrar($pdo, 'Repetido', $email, 'senhaforte123');
    } catch (RuntimeException $excecao) {
        $erro = $excecao->getMessage();
    }
    Teste::verdade($erro !== null, 'recusa e-mail ja cadastrado');

    $erro = null;
    try {
        Auth::cadastrar($pdo, 'Invalido', 'nao-e-email', 'senhaforte123');
    } catch (InvalidArgumentException $excecao) {
        $erro = $excecao->getMessage();
    }
    Teste::verdade($erro !== null, 'recusa e-mail malformado');

    // A5: nome com mais de 120 caracteres.
    $erro = null;
    try {
        Auth::cadastrar($pdo, str_repeat('a', 121), 'nomelongo' . uniqid() . '@exemplo.com', 'senhaforte123');
    } catch (InvalidArgumentException $excecao) {
        $erro = $excecao->getMessage();
    }
    Teste::verdade($erro !== null, 'recusa nome com mais de 120 caracteres');

    // A5: e-mail com mais de 160 caracteres, mas que FILTER_VALIDATE_EMAIL
    // aceita (local de 64 caracteres, dominio valido). Sem a checagem de
    // tamanho, a coluna VARCHAR(160) corta o valor em silencio, porque o
    // servidor nao roda em modo estrito.
    $emailLongo = str_repeat('a', 64) . '@' . str_repeat('b', 60) . str_repeat('.c', 40) . '.com';
    Teste::verdade((bool) filter_var($emailLongo, FILTER_VALIDATE_EMAIL), 'checagem do proprio teste: o e-mail longo usado aqui e valido pelo filtro do PHP');
    Teste::verdade(strlen($emailLongo) > 160, 'checagem do proprio teste: o e-mail longo usado aqui passa de 160 caracteres');
    $erro = null;
    try {
        Auth::cadastrar($pdo, 'Nome Normal', $emailLongo, 'senhaforte123');
    } catch (InvalidArgumentException $excecao) {
        $erro = $excecao->getMessage();
    }
    Teste::verdade($erro !== null, 'recusa e-mail com mais de 160 caracteres');

    // A6: senha de 7 caracteres multibyte (14 bytes). strlen() diria que tem
    // 14 e deixaria passar; a regra e de caracteres, entao mb_strlen precisa
    // contar 7 e recusar.
    $senhaMultibyte = 'áéíóúãõ';
    Teste::igual(7, mb_strlen($senhaMultibyte), 'checagem do proprio teste: a senha multibyte usada aqui tem 7 caracteres');
    Teste::verdade(strlen($senhaMultibyte) >= 8, 'checagem do proprio teste: a mesma senha ocupa 8 bytes ou mais');
    $erro = null;
    try {
        Auth::cadastrar($pdo, 'Multibyte', 'multibyte' . uniqid() . '@exemplo.com', $senhaMultibyte);
    } catch (InvalidArgumentException $excecao) {
        $erro = $excecao->getMessage();
    }
    Teste::verdade($erro !== null, 'recusa senha com 7 caracteres multibyte, mesmo tendo 8 bytes ou mais (conta caracteres, nao bytes)');

    // A7: corrida de cadastro. Uma segunda conexao (fora da transacao
    // principal) insere e comita o mesmo e-mail depois que a transacao
    // principal ja tirou sua foto de leitura repeatable-read. O SELECT de
    // pre-checagem de cadastrar() nao enxerga esse commit alheio (a foto e
    // mais antiga), entao ele segue para o INSERT, que esbarra na restricao
    // UNIQUE de verdade. O catch em cadastrar() precisa converter isso na
    // mesma InvalidArgumentException do caminho normal.
    $emailCorrida = 'corrida' . uniqid() . '@exemplo.com';
    $dsnSegunda = 'mysql:host=' . DB_HOST . ';port=' . DB_PORTA . ';dbname=' . DB_NOME . ';charset=utf8mb4';
    $pdo2 = new PDO($dsnSegunda, DB_USER, DB_SENHA, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo2->prepare(
        'INSERT INTO users (nome, email, senha_hash, e_organizador, ativo, criado_em)
         VALUES (?, ?, ?, 1, 1, NOW())'
    )->execute(['Corrida', $emailCorrida, password_hash('outrasenha123', PASSWORD_ARGON2ID)]);

    $buscaCorrida = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $buscaCorrida->execute([$emailCorrida]);
    Teste::igual(false, $buscaCorrida->fetch(), 'checagem do proprio teste: a pre-checagem da transacao principal nao enxerga o commit da segunda conexao (isolamento repeatable-read)');

    $erro = null;
    try {
        Auth::cadastrar($pdo, 'Perdeu a corrida', $emailCorrida, 'senhaforte123');
    } catch (RuntimeException $excecao) {
        $erro = $excecao->getMessage();
    }
    Teste::igual('Ja existe conta com esse e-mail.', $erro, 'corrida de cadastro: quem perde recebe a mesma mensagem de e-mail duplicado, nao um erro de banco cru');

    // Usuario com senha_hash nulo (por exemplo, cadastrado via login social)
    // nao pode autenticar por senha. password_verify(x, null) tambem devolve
    // false (PHP converte o nulo em string vazia), entao so conferir o
    // retorno nao prova que a checagem de nulo existe: se ela sumir, o
    // resultado final continua sendo nulo, so que passando por dentro do
    // password_verify e disparando um aviso de depreciacao do PHP por
    // receber nulo onde so se aceita string. Um manipulador de erro proprio
    // enxerga esse aviso mesmo com error_reporting do jeito que esta hoje
    // (que nao mostra E_DEPRECATED por padrao), entao a ausencia de avisos
    // e um primeiro sinal de que a checagem explicita continua no lugar.
    //
    // Mas o aviso de depreciacao e so um efeito colateral do PHP 8: vira
    // TypeError no PHP 9, e sumiria de vez se alguem trocar a guarda por
    // "?? ''" em vez de checar null explicitamente. A propriedade que
    // realmente importa e a da A4: sem a guarda, este caminho nunca chama
    // password_verify() contra o HASH_FALSO, entao perde o tempo do Argon2id
    // e responde quase instantaneo, revelando por timing que este e-mail nao
    // tem senha utilizavel. Por isso comparamos a duracao contra a de uma
    // rejeicao por senha errada numa conta de verdade (que tambem paga o
    // custo cheio do Argon2id), em vez de um numero fixo de milissegundos,
    // para nao ficar fragil em maquinas mais rapidas ou mais lentas.
    $emailSemSenha = 'semsenha' . uniqid() . '@exemplo.com';
    $pdo->prepare(
        'INSERT INTO users (nome, email, senha_hash, e_organizador, ativo, criado_em)
         VALUES (?, ?, NULL, 1, 1, NOW())'
    )->execute(['Sem Senha', $emailSemSenha]);

    $inicioSenhaErrada = microtime(true);
    Auth::autenticar($pdo, $email, 'outrasenhaerrada');
    $duracaoSenhaErrada = microtime(true) - $inicioSenhaErrada;

    $avisosSenhaNula = [];
    set_error_handler(function (int $nivel, string $mensagem) use (&$avisosSenhaNula): bool {
        $avisosSenhaNula[] = $mensagem;
        return true;
    });
    $inicioSenhaNula = microtime(true);
    $resultadoSenhaNula = Auth::autenticar($pdo, $emailSemSenha, 'qualquercoisa');
    $duracaoSenhaNula = microtime(true) - $inicioSenhaNula;
    restore_error_handler();

    Teste::igual(null, $resultadoSenhaNula, 'usuario com senha_hash nulo nao consegue autenticar');
    Teste::igual([], $avisosSenhaNula, 'senha_hash nulo e barrado antes de chegar em password_verify, sem avisos do PHP por passar nulo onde se espera string');
    Teste::verdade(
        $duracaoSenhaNula >= $duracaoSenhaErrada * 0.3,
        'a rejeicao por senha_hash nulo demora perto do mesmo tempo que uma rejeicao por senha errada de uma conta de verdade '
            . '(nula: ' . round($duracaoSenhaNula * 1000, 3) . ' ms, senha errada: ' . round($duracaoSenhaErrada * 1000, 3) . ' ms)'
    );

    // Usuario desativado nao pode autenticar, mesmo com a senha certa.
    $emailInativo = 'inativo' . uniqid() . '@exemplo.com';
    $idInativo = Auth::cadastrar($pdo, 'Inativo', $emailInativo, 'senhaforte123');
    $pdo->prepare('UPDATE users SET ativo = 0 WHERE id = ?')->execute([$idInativo]);
    Teste::igual(null, Auth::autenticar($pdo, $emailInativo, 'senhaforte123'), 'usuario inativo nao consegue autenticar mesmo com a senha certa');

    // autenticar precisa normalizar o e-mail que recebe (maiusculas e
    // espacos), assim como cadastrar ja normaliza o que grava.
    $emailNormalizacao = 'normaliza' . uniqid() . '@exemplo.com';
    Auth::cadastrar($pdo, 'Normalizacao', $emailNormalizacao, 'senhaforte123');
    $logadoComVariacao = Auth::autenticar($pdo, '  ' . strtoupper($emailNormalizacao) . '  ', 'senhaforte123');
    Teste::verdade($logadoComVariacao !== null, 'autentica mesmo enviando o e-mail com espacos e maiusculas (autenticar normaliza antes de buscar)');

    // registrarFalha tambem precisa normalizar: chamadas com variacoes de
    // caixa e espaco do mesmo e-mail devem cair na mesma linha.
    $emailFalhasMistas = 'mistura' . uniqid() . '@exemplo.com';
    for ($i = 0; $i < 5; $i++) {
        $formaUsada = $i % 2 === 0 ? ('  ' . strtoupper($emailFalhasMistas) . '  ') : $emailFalhasMistas;
        Auth::registrarFalha($pdo, $formaUsada);
    }
    Teste::verdade(Auth::bloqueadoAte($pdo, $emailFalhasMistas) !== null, 'registrarFalha normaliza o e-mail: chamadas com maiusculas e espacos incrementam a mesma linha ate bloquear');

    Teste::igual(null, Auth::bloqueadoAte($pdo, $email), 'comeca sem bloqueio');

    // O limite e 5. Quatro falhas ainda deixam entrar; a quinta bloqueia.
    // Sem essas duas asserticoes juntas, um erro de uma unidade a mais ou a menos passaria batido.
    for ($i = 0; $i < 4; $i++) {
        Auth::registrarFalha($pdo, $email);
    }
    Teste::igual(null, Auth::bloqueadoAte($pdo, $email), 'quatro falhas ainda nao bloqueiam');

    // Pendencia da revisao: bloqueado_ate nao pode ficar nulo abaixo do
    // limite, senao a linha nunca casa com o filtro da limpeza diaria
    // (nenhuma comparacao casa com nulo) e a tabela cresce sem limite por
    // insercao anonima (a tela de login chama registrarFalha a cada falha,
    // sem exigir conta). bloqueado_ate precisa estar preenchido, mas ja
    // expirado, para nao bloquear ninguem e ainda assim ser alcancado pela
    // limpeza.
    $linhaFalhasAbaixoDoLimite = $pdo->prepare('SELECT bloqueado_ate FROM tentativas_login WHERE email = ?');
    $linhaFalhasAbaixoDoLimite->execute([$email]);
    $bloqueadoAteBruto = $linhaFalhasAbaixoDoLimite->fetch()['bloqueado_ate'];
    Teste::verdade($bloqueadoAteBruto !== null, 'abaixo do limite, bloqueado_ate fica preenchido (nao nulo), para a limpeza diaria conseguir alcancar a linha depois de um dia');
    // Compara contra o relogio do proprio MySQL, nao o do PHP (mesmo cuidado
    // de mais abaixo, na curva de escalonamento): os dois podem rodar em
    // fusos diferentes.
    $agoraMysqlFalhasAbaixo = $pdo->query('SELECT NOW() AS agora')->fetch()['agora'];
    Teste::verdade(strtotime($bloqueadoAteBruto) <= strtotime($agoraMysqlFalhasAbaixo), 'o bloqueado_ate gravado abaixo do limite ja esta expirado (no passado ou agora, pelo relogio do MySQL), entao nao bloqueia ninguem');

    // Le o relogio do proprio MySQL, e nao o do PHP: o container/servico do
    // MySQL pode rodar num fuso diferente do PHP (foi o caso aqui, 5 horas
    // de diferenca), e bloqueado_ate vem de NOW() do banco. Comparar contra
    // time() do PHP mistura dois relogios e da um "quase 30 segundos" falso.
    $antesQuinta = $pdo->query('SELECT NOW() AS agora')->fetch()['agora'];
    Auth::registrarFalha($pdo, $email);
    $bloqueado5 = Auth::bloqueadoAte($pdo, $email);
    Teste::verdade($bloqueado5 !== null, 'a quinta falha bloqueia');

    // Curva de escalonamento: a quinta falha bloqueia por perto de 30
    // segundos (tolerancia para o tempo de execucao do teste), e a sexta
    // empurra o bloqueio bem mais para frente que a quinta, sem depender de
    // sleep().
    $segundosNaQuinta = strtotime($bloqueado5) - strtotime($antesQuinta);
    Teste::verdade($segundosNaQuinta >= 20 && $segundosNaQuinta <= 40, 'a quinta falha bloqueia por perto de 30 segundos');

    Auth::registrarFalha($pdo, $email);
    $bloqueado6 = Auth::bloqueadoAte($pdo, $email);
    Teste::verdade(strtotime($bloqueado6) > strtotime($bloqueado5) + 15, 'a sexta falha empurra o bloqueio bem mais para frente que a quinta');

    // Bloqueio expirado e uma linha apagada sao coisas diferentes: aqui o
    // bloqueio ja passou (voltamos bloqueado_ate para o passado direto no
    // banco), mas a linha continua na tabela, so nao bloqueia mais.
    $pdo->prepare('UPDATE tentativas_login SET bloqueado_ate = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE email = ?')
        ->execute([$email]);
    Teste::igual(null, Auth::bloqueadoAte($pdo, $email), 'bloqueio que ja passou nao bloqueia mais');
    $contaLinha = $pdo->prepare('SELECT COUNT(*) AS c FROM tentativas_login WHERE email = ?');
    $contaLinha->execute([$email]);
    Teste::igual(1, (int) $contaLinha->fetch()['c'], 'mas a linha continua existindo, so o bloqueio expirou (nao foi apagada)');

    Auth::limparFalhas($pdo, $email);
    Teste::igual(null, Auth::bloqueadoAte($pdo, $email), 'login certo limpa o bloqueio');

    // ========================================================================
    // I4 (Importante, rodada de revisao): registrarAceite/versaoAceita, e a
    // prova de que cadastrar() + registrarAceite() comitam juntos quando quem
    // chama envolve os dois na mesma transacao.
    // ========================================================================
    $emailAceite = 'aceite' . uniqid() . '@exemplo.com';
    $idAceite = Auth::cadastrar($pdo, 'Aceite Termo', $emailAceite, 'senhaforte123');
    Teste::igual(null, Auth::versaoAceita($pdo, $idAceite), 'usuario sem aceite devolve nulo');

    Auth::registrarAceite($pdo, $idAceite, '1.0', '203.0.113.10');
    Teste::igual('1.0', Auth::versaoAceita($pdo, $idAceite), 'gravar aceite e ler de volta devolve a versao gravada');

    // Aceitar a mesma versao duas vezes nao e erro e nao duplica linha: a
    // UNIQUE KEY uk_user_versao existe para tornar isso idempotente.
    Auth::registrarAceite($pdo, $idAceite, '1.0', '203.0.113.10');
    $contaAceites = $pdo->prepare('SELECT COUNT(*) AS c FROM aceites_termo WHERE user_id = ? AND versao = ?');
    $contaAceites->execute([$idAceite, '1.0']);
    Teste::igual(1, (int) $contaAceites->fetch()['c'], 'aceitar a mesma versao duas vezes nao lanca e nao duplica linha');

    // Uma versao nova passa a ser a mais recente.
    Auth::registrarAceite($pdo, $idAceite, '2.0', '203.0.113.10');
    Teste::igual('2.0', Auth::versaoAceita($pdo, $idAceite), 'aceitar uma versao nova passa a ser a versao aceita mais recente');

    // Atomicidade: cadastrar() nao gerencia transacao nenhuma (ver o docblock
    // da funcao), entao quem chama precisa envolve-la numa transacao propria
    // para a conta e o aceite comitarem juntos. Usa um SAVEPOINT na MESMA
    // conexao/transacao principal deste arquivo, e nao uma segunda conexao:
    // uma segunda conexao com sua propria transacao aberta ficaria travada
    // esperando a transacao principal (que so fecha no finally, ao final do
    // script) soltar o gap lock da UNIQUE KEY de users.email sob REPEATABLE
    // READ - o mesmo motivo pelo qual sortear() usa SAVEPOINT quando ja
    // existe uma transacao em andamento. ROLLBACK TO SAVEPOINT prova a
    // mesma atomicidade que um rollback de transacao inteira provaria.
    $emailAtomicidade = 'atomico' . uniqid() . '@exemplo.com';
    $pdo->exec('SAVEPOINT teste_atomicidade_aceite');
    $idAtomicidade = Auth::cadastrar($pdo, 'Atomico', $emailAtomicidade, 'senhaforte123');
    Auth::registrarAceite($pdo, $idAtomicidade, '1.0', '127.0.0.1');
    $pdo->exec('ROLLBACK TO SAVEPOINT teste_atomicidade_aceite');

    $confereContaAtomicidade = $pdo->prepare('SELECT COUNT(*) AS c FROM users WHERE email = ?');
    $confereContaAtomicidade->execute([$emailAtomicidade]);
    Teste::igual(0, (int) $confereContaAtomicidade->fetch()['c'], 'I4: apos rollback, nem a conta sobra (cadastrar e registrarAceite comitam juntos)');

    $confereAceiteAtomicidade = $pdo->prepare('SELECT COUNT(*) AS c FROM aceites_termo WHERE user_id = ?');
    $confereAceiteAtomicidade->execute([$idAtomicidade]);
    Teste::igual(0, (int) $confereAceiteAtomicidade->fetch()['c'], 'I4: apos rollback, o aceite tambem nao sobra');
} finally {
    // As duas linhas de limpeza precisam estar no mesmo finally, e nesta
    // ordem: o rollBack() da transacao principal roda primeiro e libera
    // qualquer travamento que o INSERT que falhou (corrida do A7) tenha
    // deixado sobre o indice unico. So depois disso a segunda conexao
    // (fora da transacao principal, e por isso fora do alcance do
    // rollBack) consegue apagar a propria linha sem travar esperando o
    // mesmo lock. Estar dentro do finally, e nao depois dele, garante que
    // essa limpeza roda mesmo se algo no meio do try lancar uma excecao
    // inesperada; sem isso a linha corrida...@exemplo.com ficaria gravada
    // para sempre, ja que foi escrita fora da transacao que o rollBack
    // desfaz.
    $pdo->rollBack();
    if ($pdo2 !== null && $emailCorrida !== null) {
        $pdo2->prepare('DELETE FROM users WHERE email = ?')->execute([$emailCorrida]);
    }
}

exit(Teste::resumo());
