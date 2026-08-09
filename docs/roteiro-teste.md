# Roteiro de teste de ponta a ponta

Roteiro manual, para rodar no navegador contra a aplicacao publicada em
`http://localhost/super8/public/`. Cobre login e bloqueio, o ciclo completo
de um campeonato (cadastro, sorteio, placar, classificacao, encerramento), o
vinculo de competidor a conta por e-mail (inscreverComEmail, tarefa 15), o
isolamento entre organizadores e a exclusao de conta a pedido do titular
(`admin/anonimizar.php`, tarefa 16).

Antes de abrir a primeira URL: sincronizar a copia de teste
(`powershell -ExecutionPolicy Bypass -File ferramentas\sincronizar-htdocs.ps1`)
e carregar o schema num banco vazio (`sql/schema.sql`). O roteiro presume
as sete tabelas vazias no comeco.

1. Criar conta de organizador com senha de 8 caracteres ou mais.
2. Errar a senha 5 vezes seguidas e conferir a mensagem de bloqueio com horario.
3. Tentar entrar com a senha CERTA enquanto o bloqueio ainda vale (antes do horario mostrado no passo 2). O
   login confere o bloqueio ANTES de conferir a senha, entao a mensagem de bloqueio continua aparecendo mesmo
   com a senha certa - isso e esperado, nao e falha.
4. Esperar o horario do bloqueio passar e so entao entrar com a senha certa. Conferir que agora loga e a
   mensagem de bloqueio sumiu.
5. Criar um campeonato com nome, data e local.
6. Cadastrar um competidor com o campo de e-mail em branco. Conferir que ele entra como convidado (o aviso da
   tela explica que convidado nao acumula no ranking).
7. Criar, em outra aba ou sessao anterior, uma conta separada (o titular). Cadastrar um competidor no mesmo
   campeonato usando o e-mail dessa conta. Conferir que a inscricao e aceita.
8. Tentar cadastrar um competidor com um e-mail que nao corresponde a nenhuma conta. Conferir a recusa, com a
   mensagem explicando que o e-mail nao bate com conta ativa nenhuma, e que nada foi cadastrado.
9. Tentar cadastrar o MESMO titular do passo 7 de novo, com o mesmo e-mail, no mesmo campeonato. Conferir a
   recusa ("Este jogador ja esta inscrito neste campeonato").
10. Com o campeonato ainda com MENOS de 8 competidores, tentar cadastrar dois com o mesmo nome de exibicao e
    conferir a recusa. Precisa acontecer antes do proximo passo: com 8 competidores completos a checagem de
    limite dispara antes da checagem de nome duplicado, e esconde este caso.
11. Completar o campeonato ate 8 competidores (convidados, sem e-mail). Conferir que a tela troca o formulario
    de cadastro pelo botao de sortear - com 8 completos nao ha mais como tentar um nono pela propria tela, e
    essa ausencia do formulario E a guarda funcionando. Para confirmar que o motor tambem recusa por baixo
    (nao so a tela escondendo o botao), forjar um nono envio direto: a mensagem esperada e
    "O campeonato ja tem 8 competidores.".
12. Sortear. Conferir que as posicoes de 1 a 8 ficaram preenchidas e que a semente aparece no chaveamento.
13. Conferir no chaveamento que cada rodada tem 2 partidas e que os 8 nomes aparecem uma vez por rodada.
14. Lancar os 14 placares. Conferir que os valores voltam ao recarregar.
15. Abrir a classificacao e conferir a mao a soma de games de dois jogadores.
16. Encerrar o campeonato.
17. Abrir o ranking (dentro do periodo "Tudo") e conferir que so o titular vinculado no passo 7 aparece -
    nenhum dos convidados sem conta entra na lista.
18. Sair. Entrar com uma SEGUNDA conta de organizador (uma conta diferente da que criou o campeonato). Abrir o
    ranking com essa segunda conta e conferir que o titular do passo 7 continua aparecendo: o ranking e
    acumulado entre jogadores, nao preso a quem organizou o evento.
19. Ainda com a segunda conta, tentar abrir `campeonato.php` usando o id do campeonato criado pela primeira
    conta, direto na URL. Esperado: 404.
20. Repetir o passo 19 com `chaveamento.php`, `classificacao.php` e `inscricoes.php`.
21. Rodar a rotina de exclusao a pedido do titular sobre o e-mail usado no passo 7:
    `C:\xampp\php\php.exe admin/anonimizar.php <e-mail do titular>`. Depois, conferir:
    - O nome do titular na classificacao do campeonato virou um identificador anonimo ("Jogador removido N"),
      no lugar do nome de exibicao original.
    - Os games do evento na classificacao continuam exatamente os mesmos de antes da exclusao.
    - O titular sumiu do ranking acumulado (o mesmo que os passos 17/18 mostravam).
    - Tentar entrar com o e-mail e a senha originais do titular falha: a conta foi desativada e a senha
      apagada.
