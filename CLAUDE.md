# CLAUDE.md - Projeto Super 8 (Padel)

Arquivo de contexto e regras deste projeto. Ler antes de qualquer tarefa nesta pasta.

## O que e o projeto

Plataforma web para criar, gerenciar e ranquear torneios de padel no formato Super 8. Oito jogadores, 7 rodadas, todos jogam com e contra todos, pontuacao individual por games vencidos.

O documento de requisitos completo esta em `projeto-super8-padel.md`.

Etapa atual: primeira versao sem login com Google. A autenticacao via Google (Laravel Socialite) fica para a segunda etapa.

## Regras obrigatorias

### Textos

Todo texto produzido no projeto passa pela skill `/anthropic-skills:humanizar-ptbr`. Vale para documentacao, comentarios longos, textos de interface, e-mails e qualquer conteudo lido por pessoa. Sem travessao longo, sem aspas curvas, sem emoji em titulo, sem as muletas de IA listadas na skill.

### Exclusao de arquivos

Nao apagar arquivo nenhum. Quando um arquivo precisar sair do lugar, mover para `_LIXEIRA` na raiz do projeto. Isso inclui arquivo substituido, versao antiga, teste descartado e qualquer coisa que pareca lixo.

### Controle de versao

Todo software desenvolvido aqui tem repositorio no GitHub associado. Neste projeto: `github.com/manfredjr/super8`, privado.

Cada alteracao vira um commit com mensagem que explica o que mudou, e o push sai junto. Nao acumular trabalho local esperando um pedido: alteracao concluida e alteracao publicada.

O que conta como concluida: o codigo funciona, a suite passa inteira, e a arvore esta limpa. Nunca dar push com teste vermelho nem com trabalho pela metade. Se a revisao apontou algo, o push espera a correcao.

A autorizacao de push vive em `.claude/settings.local.json`, que nao entra no repositorio de proposito, para que ninguem que clone herde a permissao desta maquina.

### Os tres lugares, e o que e cada um

Esta e a regra que organiza o projeto inteiro. Sao tres lugares com papeis diferentes, e confundi-los estraga o fluxo.

**1. O projeto:** `C:\COWORK\CODE\SUPER8`

Aqui vivem os arquivos do projeto. E a fonte de verdade e o unico lugar onde se edita codigo. O repositorio git fica aqui, os documentos ficam aqui, os testes de linha de comando rodam daqui.

**2. O teste local:** `C:\xampp\htdocs\super8`

Copia da aplicacao rodando no XAMPP, para validar no navegador que ela funciona antes de mandar para o servidor publicado. Nao se edita nada aqui. Se algo precisa mudar, muda no projeto e sincroniza de novo. Recebe so o que a aplicacao precisa para rodar: `config`, `src`, `views`, `public` e `sql`. Nao recebe testes, nao recebe documentos, nao recebe git.

**3. O pacote de publicacao:** `C:\COWORK\CODE\SUPER8\_PUBLICAR\enviar`

Os arquivos que vao para o servidor publicado. Mesmo conteudo do teste local, montado a partir do codigo versionado. Nada sobe direto da pasta de desenvolvimento nem do XAMPP.

O caminho e sempre projeto, depois teste local, depois pacote de publicacao. Cada projeto novo ganha uma pasta com o nome dele dentro de `htdocs`.

Para sincronizar o teste local, ha um script no projeto:

```
powershell -ExecutionPolicy Bypass -File ferramentas\sincronizar-htdocs.ps1
```

E para montar o pacote de publicacao:

```
powershell -ExecutionPolicy Bypass -File ferramentas\montar-pacote.ps1
```

Os dois copiam a partir do projeto, nunca de uma copia para outra, para nao propagar edicao feita no lugar errado.

### Antes de codar

Software se projeta antes de executar. Isso significa: definir requisitos, modelar dados, desenhar as telas e o fluxo, e so entao escrever codigo. A cada projeto novo, avaliar duas frentes:

1. Seguranca - autenticacao, controle de acesso, injecao de SQL, XSS, CSRF, upload de arquivo, exposicao de dados no ambiente publico.
2. Leis aplicaveis - LGPD para dados pessoais (nome, e-mail, foto, telefone), base legal do tratamento, consentimento, politica de privacidade, prazo de retencao e direito de exclusao do titular. Verificar tambem uso de imagem quando houver fotos de jogadores.

O resultado dessa avaliacao entra em documento no projeto, nao so na conversa.

## Estrutura de pastas

```
C:\COWORK\CODE\SUPER8\                fonte de verdade, repositorio git
  CLAUDE.md                           este arquivo
  README.md                           como rodar e como esta organizado
  projeto-super8-padel.md             requisitos originais do sistema
  .gitignore  .gitattributes
  config\                             conexao, sessao, csrf, acesso
  src\                                as regras, sem uma linha de HTML
  views\                              as telas
  public\                             pontos de entrada
  sql\                                schema do banco
  testes\                             suite de linha de comando
  ferramentas\                        scripts de sincronizacao e empacotamento
  docs\
    superpowers\specs\                especificacao da etapa
    superpowers\plans\                plano de implementacao
  _LIXEIRA\                           destino de arquivos removidos, fora do git
  _PUBLICAR\enviar\                   pacote pronto para producao, fora do git

C:\xampp\htdocs\super8\               copia de teste no navegador, sem git
```

## Stack

PHP 8.2 e MariaDB, sem framework e sem Composer. O documento de requisitos original previa Laravel; a etapa 1 foi feita em PHP puro com PDO porque o sistema tem seis tabelas e oito telas, e o framework cobrava mais em preparo do que devolvia. A decisao esta registrada com o motivo em `docs/superpowers/specs`.

Teste local no XAMPP, producao em VPS proprio com Let's Encrypt.
