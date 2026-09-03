#!/usr/bin/env bash
set -Eeuo pipefail

usage() {
    cat <<'USAGE'
Usage:
  ./docker-start.sh [options]

Options:
  --no-build     Skip image build before starting.
  --no-cache     Build images without Docker layer cache.
  --no-migrate   Skip Laravel migrations on app startup.
  --pull         Pull service images before starting.
  --logs         Follow app logs after starting.
  --down         Stop and remove containers.
  --restart      Restart containers through this script.
  -h, --help     Show this help.

Default:
  ./docker-start.sh builds images, runs migrations, starts containers,
  waits for the app healthcheck, and prints docker compose ps.
USAGE
}

build=true
no_cache=false
migrate=true
pull=false
logs=false
down=false
restart=false

while [[ $# -gt 0 ]]; do
    case "$1" in
        --no-build)
            build=false
            ;;
        --no-cache)
            no_cache=true
            ;;
        --no-migrate)
            migrate=false
            ;;
        --pull)
            pull=true
            ;;
        --logs)
            logs=true
            ;;
        --down)
            down=true
            ;;
        --restart)
            restart=true
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo "Unknown option: $1" >&2
            usage >&2
            exit 1
            ;;
    esac

    shift
done

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$project_root"

if ! command -v docker >/dev/null 2>&1; then
    echo "Docker is not installed or not available in PATH." >&2
    exit 1
fi

if ! docker compose version >/dev/null 2>&1; then
    echo "Docker Compose v2 is not available. Install Docker Compose plugin." >&2
    exit 1
fi

if [[ ! -f compose.yaml ]]; then
    echo "Run this script from the project root containing compose.yaml." >&2
    exit 1
fi

if ! docker info >/dev/null 2>&1; then
    echo "Docker daemon is not running or is not reachable." >&2
    echo "Start Docker Desktop, then run ./docker-start.sh again." >&2
    exit 1
fi

read_env_value() {
    local key="$1"
    local value=""

    if [[ -f .env ]]; then
        value="$(
            awk -F= -v key="$key" '
                $0 !~ /^[[:space:]]*#/ && $1 == key {
                    sub(/^[^=]*=/, "")
                    print
                    exit
                }
            ' .env
        )"
        value="${value%\"}"
        value="${value#\"}"
        value="${value%\'}"
        value="${value#\'}"
    fi

    printf '%s' "$value"
}

APP_KEY="${APP_KEY:-$(read_env_value APP_KEY)}"
DB_PASSWORD="${DB_PASSWORD:-$(read_env_value DB_PASSWORD)}"

if [[ -z "${APP_KEY:-}" ]]; then
    echo "APP_KEY is required. Set it in .env or export APP_KEY before running." >&2
    exit 1
fi

if [[ "${DB_PASSWORD:-change-me}" == "change-me" ]]; then
    echo "Warning: DB_PASSWORD is using the default value 'change-me'." >&2
fi

export APP_KEY

if [[ -n "${DB_PASSWORD:-}" ]]; then
    export DB_PASSWORD
fi

if [[ "$down" == true ]]; then
    docker compose down --remove-orphans
    exit 0
fi

if [[ "$restart" == true ]]; then
    docker compose down --remove-orphans
fi

if [[ "$pull" == true ]]; then
    docker compose pull postgres rabbitmq qdrant ollama
fi

if [[ "$build" == true ]]; then
    if [[ "$no_cache" == true ]]; then
        docker compose build --no-cache
    else
        docker compose build
    fi
fi

if [[ "$migrate" == true ]]; then
    APP_KEY="$APP_KEY" RUN_MIGRATIONS=true docker compose up -d --remove-orphans
else
    APP_KEY="$APP_KEY" docker compose up -d --remove-orphans
fi

echo "Waiting for Laravel, Ollama, document processor, RabbitMQ, and n8n healthchecks..."
healthy=false
for attempt in {1..450}; do
    app_status="$(docker compose ps app --format json 2>/dev/null || true)"
    n8n_status="$(docker compose ps n8n --format json 2>/dev/null || true)"
    rabbitmq_status="$(docker compose ps rabbitmq --format json 2>/dev/null || true)"
    document_processor_status="$(docker compose ps document-processor --format json 2>/dev/null || true)"
    ollama_status="$(docker compose ps ollama --format json 2>/dev/null || true)"

    if echo "$app_status" | grep -q '"Health":"healthy"' \
        && echo "$n8n_status" | grep -q '"Health":"healthy"' \
        && echo "$rabbitmq_status" | grep -q '"Health":"healthy"' \
        && echo "$document_processor_status" | grep -q '"Health":"healthy"' \
        && echo "$ollama_status" | grep -q '"Health":"healthy"'; then
        healthy=true
        break
    fi

    if echo "$app_status$n8n_status$rabbitmq_status$document_processor_status$ollama_status" | grep -q '"State":"exited"'; then
        echo "A required container exited during startup." >&2
        docker compose logs --tail=120 app queue document-processor rabbitmq n8n ollama qdrant >&2
        exit 1
    fi

    sleep 2
done

if [[ "$healthy" != true ]]; then
    echo "The application stack did not become healthy within 900 seconds." >&2
    docker compose logs --tail=120 app queue document-processor rabbitmq n8n ollama qdrant >&2
    exit 1
fi

docker compose ps

echo "Application: http://localhost:${HTTP_PORT:-8080}"
echo "n8n editor: http://localhost:${N8N_PORT:-5678}"
echo "RabbitMQ management: http://localhost:${RABBITMQ_MANAGEMENT_PORT:-15672}"
echo "Document processor: http://localhost:${DOCUMENT_PROCESSOR_PORT:-8001}/health"
echo "Qdrant dashboard: http://localhost:${QDRANT_PORT:-6333}/dashboard"

if [[ "$logs" == true ]]; then
    docker compose logs -f app queue document-processor rabbitmq ollama
fi
