$logPath = "$env:TEMP\cf_tunnel.log"
if (Test-Path $logPath) { Remove-Item $logPath }

Write-Host "Starting Cloudflare Tunnel..." -ForegroundColor Cyan
$proc = Start-Process -FilePath "$env:USERPROFILE\cloudflared.exe" -ArgumentList "tunnel --url http://localhost:9001" -RedirectStandardOutput $logPath -PassThru -WindowStyle Hidden

Write-Host "Waiting for tunnel URL..." -ForegroundColor Yellow
$url = $null
for ($i=0; $i -lt 15; $i++) {
    Start-Sleep -Seconds 2
    if (Test-Path $logPath) {
        $content = Get-Content $logPath -Raw
        if ($content -match 'https://([a-zA-Z0-9-]+\.trycloudflare\.com)') {
            $url = $Matches[1]
            break
        }
    }
}

if ($url) {
    Write-Host "Tunnel URL found: $url" -ForegroundColor Green
    
    # Kirim URL ke Wasmer Backend
    $body = @{ secret = 'tukir-secret-update-99'; host = $url } | ConvertTo-Json
    try {
        $response = Invoke-RestMethod -Uri "https://tukir-backend.wasmer.app/api/internal/tunnel-update" -Method Post -Body $body -ContentType "application/json"
        Write-Host "Successfully updated Wasmer Backend! Response: $($response | ConvertTo-Json -Compress)" -ForegroundColor Green
    } catch {
        Write-Host "Failed to update Wasmer Backend: $_" -ForegroundColor Red
    }
} else {
    Write-Host "Could not retrieve tunnel URL." -ForegroundColor Red
}

Write-Host "Tunnel is running in background. Press any key to stop..."
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
Stop-Process -Id $proc.Id -Force
