#!/usr/bin/env bash
set -Eeuo pipefail
cd "$(dirname "$(readlink -f "$0")")"
if ! command -v docker >/dev/null 2>&1; then echo "错误：请先在宝塔安装 Docker 和 Docker Compose"; exit 1; fi
if ! docker compose version >/dev/null 2>&1; then echo "错误：需要 Docker Compose v2 (docker compose)"; exit 1; fi
if [[ ! -f .env ]]; then cp .env.example .env; fi
mkdir -p data/Volume
docker compose pull xhs
docker compose up -d --build
echo "启动完成。网站本机地址：http://127.0.0.1:$(sed -n 's/^WEB_PORT=//p' .env | tail -1)"
echo "检查状态：docker compose ps"
echo "查看日志：docker compose logs -f"
