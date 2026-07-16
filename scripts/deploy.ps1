# deploy.ps1 — pull the latest main and redeploy only when something changed.
# Meant to run on a schedule (see DEPLOY.md → "Auto-deploy on push").
$ErrorActionPreference = 'Stop'

# Repo root (this script lives in scripts/).
Set-Location (Join-Path $PSScriptRoot '..')

git fetch origin main --quiet
$local = git rev-parse HEAD
$remote = git rev-parse origin/main

if ($local -eq $remote) {
    Write-Host "Up to date ($local)."
    exit 0
}

Write-Host "New commits $local -> $remote. Redeploying..."
git pull --ff-only origin main

# COMPOSE_PROFILES (e.g. "tunnel") in .env is picked up automatically.
docker compose up -d --build
docker image prune -f
Write-Host "Done."
