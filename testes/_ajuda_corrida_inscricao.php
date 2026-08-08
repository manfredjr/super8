<?php

// Ajudante de teste, nao um arquivo de teste em si: o nome nao comeca com
// "teste_" de proposito, para testes/executar.php nao tentar rodar isto
// sozinho.
//
// Simula uma segunda conexao que ja segura a trava do campeonato (o mesmo
// SELECT ... FOR UPDATE que Campeonato::inscrever usa) e demora para
// terminar. O SLEEP() roda no SERVIDOR, com a transacao aberta e a linha
// travada: isso cria uma janela de concorrencia real, sem precisar mexer no
// codigo fonte nem depender de timing entre processos do lado de fora.
//
// Uso: php _ajuda_corrida_inscricao.php <idCampeonato> <segundosDeSleep>
// Escreve "TRAVA_OK\n" em stdout assim que a trava e adquirida (antes do
// SLEEP), para quem chamou saber exatamente quando pode tentar a propria
// insercao concorrente.

require __DIR__ . '/../config/db.php';

$campeonatoId = (int) ($argv[1] ?? 0);
$segundos = (int) ($argv[2] ?? 2);

$terminouDireito = false;
register_shutdown_function(function () use (&$terminouDireito) {
    if (!$terminouDireito) {
        exit(9);
    }
});

$pdo = db();
$pdo->beginTransaction();
$pdo->prepare('SELECT id FROM campeonatos WHERE id = ? FOR UPDATE')->execute([$campeonatoId]);

fwrite(STDOUT, "TRAVA_OK\n");
fflush(STDOUT);

$pdo->prepare('SELECT SLEEP(?)')->execute([$segundos]);

$pdo->prepare('INSERT INTO inscricoes (campeonato_id, jogador_id, nome_exibicao) VALUES (?, NULL, ?)')
    ->execute([$campeonatoId, 'Concorrente lento']);
$pdo->commit();

$terminouDireito = true;
fwrite(STDOUT, "COMMITOU\n");
