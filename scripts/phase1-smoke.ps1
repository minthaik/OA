param(
  [string]$WpCommand = "wp",
  [string]$From = "",
  [string]$To = "",
  [ValidateSet("table","json")]
  [string]$Format = "table",
  [switch]$Strict
)

$now = Get-Date
if ([string]::IsNullOrWhiteSpace($To)) {
  $To = $now.ToString("yyyy-MM-dd")
}
if ([string]::IsNullOrWhiteSpace($From)) {
  $From = $now.AddDays(-29).ToString("yyyy-MM-dd")
}

$args = @(
  "ordelix",
  "regression-smoke",
  "--from=$From",
  "--to=$To",
  "--format=$Format"
)
if ($Strict) { $args += "--strict" }

Write-Host "Running: $WpCommand $($args -join ' ')" -ForegroundColor Cyan
& $WpCommand @args
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
