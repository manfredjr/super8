# Roteiro de teste de ponta a ponta

Roteiro manual, para rodar no navegador contra a aplicacao publicada em
`http://localhost/super8/public/`. Cobre login e bloqueio, o ciclo completo
de um campeonato (cadastro, sorteio, placar, classificacao, encerramento), o
vinculo de competidor a conta por e-mail (inscreverComEmail, tarefa 15) e o
isolamento entre organizadores.

1. Criar conta de organizador com senha de 8 caracteres ou mais.
2. Errar a senha 5 vezes seguidas e conferir a mensagem de bloqueio com horario.
3. Entrar com a senha certa e conferir que o bloqueio sumiu.
4. Criar um campeonato com nome, data e local.
5. Cadastrar um competidor com o campo de e-mail em branco. Conferir que ele entra como convidado (o aviso da
   tela explica que convidado nao acumula no ranking).
6. Criar, em outra aba ou sessao anterior, uma conta separada (o titular). Cadastrar um competidor no mesmo
   campeonato usando o e-mail dessa conta. Conferir que a inscricao e aceita.
7. Tentar cadastrar um competidor com um e-mail que nao corresponde a nenhuma conta. Conferir a recusa, com a
   mensagem explicando que o e-mail nao bate com conta ativa nenhuma, e que nada foi cadastrado.
8. Tentar cadastrar o MESMO titular do passo 6 de novo, com o mesmo e-mail, no mesmo campeonato. Conferir a
   recusa ("Este jogador ja esta inscrito neste campeonato").
9. Completar o campeonato ate 8 competidores (convidados, sem e-mail). Tentar cadastrar o nono e conferir a
   recusa.
10. Tentar cadastrar dois competidores com o mesmo nome de exibicao e conferir a recusa.
11. Sortear. Conferir que as posicoes de 1 a 8 ficaram preenchidas e que a semente aparece no chaveamento.
12. Conferir no chaveamento que cada rodada tem 2 partidas e que os 8 nomes aparecem uma vez por rodada.
13. Lancar os 14 placares. Conferir que os valores voltam ao recarregar.
14. Abrir a classificacao e conferir a mao a soma de games de dois jogadores.
15. Encerrar o campeonato.
16. Abrir o ranking (dentro do periodo "Tudo") e conferir que so o titular vinculado no passo 6 aparece -
    nenhum dos convidados sem conta entra na lista.
17. Sair. Entrar com uma SEGUNDA conta de organizador (uma conta diferente da que criou o campeonato). Abrir o
    ranking com essa segunda conta e conferir que o titular do passo 6 continua aparecendo: o ranking e
    acumulado entre jogadores, nao preso a quem organizou o evento.
18. Ainda com a segunda conta, tentar abrir `campeonato.php` usando o id do campeonato criado pela primeira
    conta, direto na URL. Esperado: 404.
19. Repetir o passo 18 com `chaveamento.php`, `classificacao.php` e `inscricoes.php`.
