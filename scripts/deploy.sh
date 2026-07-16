#!/bin/sh
# deploy.sh — pull the latest main and redeploy only when something changed.
# Meant to run on a schedule (cron). See DEPLOY.md → "Auto-deploy on push".
set -e

cd "$(dirname "$0")/.."

git fetch origin main --quiet
if [ "$(git rev-parse HEAD)" = "$(git rev-parse origin/main)" ]; then
	echo "Up to date."
	exit 0
fi

echo "New commits — redeploying..."
git pull --ff-only origin main

# COMPOSE_PROFILES (e.g. "tunnel") in .env is picked up automatically.
docker compose up -d --build
docker image prune -f
echo "Done."
