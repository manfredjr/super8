# Sincroniza a copia de teste no XAMPP a partir do projeto.
#
# O projeto e a fonte de verdade. Esta copia existe so para validar no navegador
# que a aplicacao funciona antes de mandar para o servidor publicado, e nao se
# edita nada dentro dela. Se algo precisa mudar, muda no projeto e roda isto de novo.
#
# Uso: powershell -ExecutionPolicy Bypass -File ferramentas\sincronizar-htdocs.ps1

$ErrorActionPreference = 'Stop'

$projeto = Split-Path -Parent $PSScriptRoot
$destino = 'C:\xampp\htdocs\super8'

# Somente o que a aplicacao precisa para rodar. Testes, documentos e git ficam fora,
# porque a copia tem que parecer com o servidor publicado, nao com a maquina de trabalho.
$pastas = @('config', 'src', 'views', 'public', 'sql')

Write-Host "Projeto:  $projeto"
Write-Host "Destino:  $destino"
Write-Host ''

if (-not (Test-Path $destino)) {
    New-Item -ItemType Directory -Force -Path $destino | Out-Null
    Write-Host "Pasta de destino criada."
}

foreach ($pasta in $pastas) {
    $origem = Join-Path $projeto $pasta
    if (-not (Test-Path $origem)) {
        Write-Host ("  {0,-10} nao existe no projeto, pulando" -f $pasta)
        continue
    }

    $alvo = Join-Path $destino $pasta

    # /MIR espelha, entao arquivo apagado no projeto tambem sai da copia.
    # /NFL /NDL /NJH /NJS deixam a saida limpa; so o resumo interessa.
    $saida = robocopy $origem $alvo /MIR /NFL /NDL /NJH /NJS /NP 2>&1
    $codigo = $LASTEXITCODE

    # Robocopy usa 0 para nada a fazer e 1 a 7 para sucesso com copias.
    # De 8 para cima e erro de verdade.
    if ($codigo -ge 8) {
        Write-Host ("  {0,-10} ERRO (robocopy {1})" -f $pasta, $codigo) -ForegroundColor Red
        Write-Host $saida
        exit 1
    }

    $n = (Get-ChildItem -Path $alvo -Recurse -File -ErrorAction SilentlyContinue | Measure-Object).Count
    Write-Host ("  {0,-10} ok, {1} arquivos" -f $pasta, $n)
}

Write-Host ''

# O config.php carrega as credenciais e nao esta no git. Se o destino ainda nao tem,
# o robocopy tambem nao levou, e a aplicacao vai falhar na conexao sem aviso claro.
$configDestino = Join-Path $destino 'config\config.php'
if (Test-Path $configDestino) {
    Write-Host 'config.php presente na copia de teste.'
} else {
    Write-Host 'ATENCAO: falta config/config.php na copia de teste.' -ForegroundColor Yellow
    Write-Host 'Copie config/config.exemplo.php para config/config.php e ajuste as credenciais.'
}

Write-Host ''
Write-Host 'Pronto. Abra http://localhost/super8/public/ para validar.'
