# Monta o pacote de publicacao em _PUBLICAR\enviar a partir do projeto.
#
# Copia so o que roda no servidor. Testes, documentos, ferramentas e git ficam fora.
# O config.php nao vai de proposito: no servidor ele e criado a partir do exemplo,
# com as credenciais de producao e COOKIE_SEGURO em true.
#
# Uso: powershell -ExecutionPolicy Bypass -File ferramentas\montar-pacote.ps1

$ErrorActionPreference = 'Stop'

$projeto = Split-Path -Parent $PSScriptRoot
$destino = Join-Path $projeto '_PUBLICAR\enviar'

$pastas = @('config', 'src', 'views', 'public', 'sql')

Write-Host "Projeto:  $projeto"
Write-Host "Destino:  $destino"
Write-Host ''

# Antes de montar, confirma que os testes passam. Pacote com teste vermelho
# nao deveria existir, e descobrir isso depois do FTP custa muito mais.
$php = 'C:\xampp\php\php.exe'
if (Test-Path $php) {
    Write-Host 'Rodando a suite de testes antes de montar...'
    $saidaTestes = & $php (Join-Path $projeto 'testes\executar.php') 2>&1
    $codigoTestes = $LASTEXITCODE
    if ($codigoTestes -ne 0) {
        Write-Host ''
        Write-Host 'ABORTADO: a suite de testes falhou. O pacote nao foi montado.' -ForegroundColor Red
        Write-Host ($saidaTestes | Select-Object -Last 20)
        exit 1
    }
    Write-Host 'Suite verde.'
    Write-Host ''
} else {
    Write-Host "ATENCAO: $php nao encontrado, seguindo sem rodar os testes." -ForegroundColor Yellow
    Write-Host ''
}

if (-not (Test-Path $destino)) {
    New-Item -ItemType Directory -Force -Path $destino | Out-Null
}

foreach ($pasta in $pastas) {
    $origem = Join-Path $projeto $pasta
    if (-not (Test-Path $origem)) {
        Write-Host ("  {0,-10} nao existe no projeto, pulando" -f $pasta)
        continue
    }

    $alvo = Join-Path $destino $pasta
    $saida = robocopy $origem $alvo /MIR /NFL /NDL /NJH /NJS /NP 2>&1
    if ($LASTEXITCODE -ge 8) {
        Write-Host ("  {0,-10} ERRO (robocopy {1})" -f $pasta, $LASTEXITCODE) -ForegroundColor Red
        Write-Host $saida
        exit 1
    }

    $n = (Get-ChildItem -Path $alvo -Recurse -File -ErrorAction SilentlyContinue | Measure-Object).Count
    Write-Host ("  {0,-10} ok, {1} arquivos" -f $pasta, $n)
}

# O config.php local carrega credenciais de desenvolvimento. Se ele entrou no
# pacote, sai daqui: subir isso para o servidor entregaria as credenciais erradas
# e, pior, sobrescreveria as de producao.
$configPacote = Join-Path $destino 'config\config.php'
if (Test-Path $configPacote) {
    $lixeira = Join-Path $projeto ('_LIXEIRA\config-php-removido-do-pacote-' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
    New-Item -ItemType Directory -Force -Path $lixeira | Out-Null
    Move-Item -Path $configPacote -Destination (Join-Path $lixeira 'config.php') -Force
    Write-Host ''
    Write-Host 'config.php retirado do pacote e guardado em _LIXEIRA.'
}

# sql/seed_demo.sql tem senha de demonstracao escrita dentro (mesmo sendo so
# de teste local). Se o DocumentRoot ou o alias do servidor apontar para a
# raiz do pacote em vez de apontar direto para public/ - confirmado contra o
# Apache deste projeto, que serve sql\*.sql cru - esse arquivo fica legivel
# por qualquer um que adivinhe a URL. Sai do pacote pelo mesmo motivo e do
# mesmo jeito que o config.php: schema.sql fica (precisa rodar em producao),
# seed_demo.sql nao tem uso nenhum fora da maquina de desenvolvimento.
$seedPacote = Join-Path $destino 'sql\seed_demo.sql'
if (Test-Path $seedPacote) {
    $lixeiraSeed = Join-Path $projeto ('_LIXEIRA\seed-demo-sql-removido-do-pacote-' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
    New-Item -ItemType Directory -Force -Path $lixeiraSeed | Out-Null
    Move-Item -Path $seedPacote -Destination (Join-Path $lixeiraSeed 'seed_demo.sql') -Force
    Write-Host 'seed_demo.sql retirado do pacote e guardado em _LIXEIRA (tem senha de demonstracao escrita dentro).'
}

Write-Host ''
Write-Host 'Pacote montado. No servidor, faltam tres passos:'
Write-Host '  1. Criar config/config.php a partir do exemplo, com as credenciais de producao'
Write-Host '     e COOKIE_SEGURO = true.'
Write-Host '  2. Rodar sql/schema.sql no banco de producao.'
Write-Host '  3. Apontar o DocumentRoot (ou o alias, se for o caso) direto para a pasta'
Write-Host '     public - nunca para a raiz do pacote. Apontar para a raiz serve os'
Write-Host '     arquivos de config/ e sql/ como texto cru para quem souber a URL.'
