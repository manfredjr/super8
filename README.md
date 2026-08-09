# Super 8 Padel

Produto da **MT - Manfred Tecnologia**.

Sistema de torneios de padel no formato Super 8. Oito jogadores, 7 rodadas, todos jogam com e contra todos, pontuacao individual por games vencidos.

Etapa atual: motor do torneio e ranking acumulado, com login por e-mail e senha. O login com Google fica para a etapa 2, e a modelagem ja nasceu preparada para ele.

## Modelo

Gratuito para o competidor e para quem cria a competicao. Nao ha plano pago nem cobranca por evento.

Em troca do uso gratuito, a MT figura como apoiadora e patrocinadora de cada campeonato criado na plataforma, com marca visivel na pagina do evento, no chaveamento, na classificacao e no ranking. Quem cria um campeonato aceita isso no cadastro, por termo de uso registrado.

Codigo fechado. Repositorio privado, publicacao na infraestrutura da MT.

## Os tres lugares

O projeto trabalha em tres pastas com papeis diferentes, e vale entender isso antes de mexer em qualquer coisa.

| Pasta | Papel |
|---|---|
| `C:\COWORK\CODE\SUPER8` | O projeto. Fonte de verdade, repositorio git, unico lugar onde se edita codigo |
| `C:\xampp\htdocs\super8` | Copia de teste no XAMPP, para validar no navegador. Nao se edita nada aqui |
| `C:\COWORK\CODE\SUPER8\_PUBLICAR\enviar` | Pacote que vai para o servidor publicado |

O caminho e sempre nessa ordem. Editar direto na copia do XAMPP e a forma mais facil de perder trabalho, porque a proxima sincronizacao sobrescreve.

## Preparar

1. Ligar Apache e MySQL no painel do XAMPP.
2. Criar o banco: `C:\xampp\mysql\bin\mysql.exe -u root < sql/schema.sql`
3. Copiar `config/config.exemplo.php` para `config/config.php` e ajustar as credenciais.

O `config/config.php` guarda as credenciais e fica fora do controle de versao de proposito. Somente o exemplo e versionado.

## Rodar os testes

```
C:\xampp\php\php.exe testes\executar.php
```

Roda a partir do projeto, nao da copia do XAMPP. Sao 13 arquivos de teste que rodam por linha de comando, sem navegador e sem servidor web. Alguns precisam do MySQL no ar, porque exercitam transacao e concorrencia de verdade, com dois processos disputando a mesma trava.

O runner julga cada arquivo pelo codigo de saida. Vale saber de uma armadilha do PHP que aparece varias vezes neste projeto: `exit('mensagem')` devolve codigo de saida zero, entao um teste que morre no meio passaria por aprovado. Os testes que podem alcancar um `exit` usam `register_shutdown_function` para forcar codigo diferente de zero.

## Validar no navegador

```
powershell -ExecutionPolicy Bypass -File ferramentas\sincronizar-htdocs.ps1
```

Copia `config`, `src`, `views`, `public` e `sql` para `C:\xampp\htdocs\super8`, espelhando, entao arquivo apagado no projeto tambem sai da copia. Depois abra `http://localhost/super8/public/`.

## Montar o pacote de publicacao

```
powershell -ExecutionPolicy Bypass -File ferramentas\montar-pacote.ps1
```

Roda a suite primeiro e aborta se algum teste falhar, porque descobrir isso depois do FTP custa muito mais. Monta `_PUBLICAR\enviar` e retira o `config.php` local do pacote, para que as credenciais de desenvolvimento nao subam nem sobrescrevam as de producao.

No servidor faltam tres passos, que o script lembra ao terminar: criar o `config/config.php` a partir do exemplo com `COOKIE_SEGURO = true`, rodar o `sql/schema.sql` no banco de producao, e apontar o DocumentRoot para a pasta `public`.

## Como o codigo esta organizado

- `src/` guarda as regras. Nenhuma linha de HTML, nenhuma chamada de sessao ou de cabecalho. Cada arquivo recebe a conexao e os valores por parametro, o que e o que torna os testes de linha de comando possiveis.
- `config/` guarda conexao, sessao, token CSRF, escape de saida e controle de acesso. E aqui que mora o codigo que legitimamente toca sessao e cabecalho.
- `views/` guarda as telas, `public/` os pontos de entrada.
- `sql/schema.sql` cria as sete tabelas.
- `testes/` roda tudo. Arquivos comecando com `_ajuda_` sao auxiliares chamados por subprocesso, e nao testes; o nome e proposital, porque o runner so recolhe `teste_*.php`.
- `ferramentas/` guarda os scripts de sincronizacao e empacotamento.

## Documentacao

- `docs/analise/padelsuper8-analise.md` e o documento de analise: contexto, objetivos, regras de negocio
  numeradas, requisitos funcionais e nao funcionais, exigencias legais e criterios de aceite. E por onde
  comeca quem nunca viu o projeto.
- `projeto-super8-padel.md` traz os requisitos originais do sistema.
- `docs/especificacao/` traz o desenho da etapa: modelagem, decisoes de arquitetura com o motivo de cada uma, analise de seguranca, analise de LGPD e a lista dos limites aceitos.
- `docs/plano/` traz o plano de implementacao tarefa por tarefa.
