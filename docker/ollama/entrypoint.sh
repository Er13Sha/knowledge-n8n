#!/bin/sh
set -eu

server_pid=""

stop_server() {
    if [ -n "$server_pid" ] && kill -0 "$server_pid" 2>/dev/null; then
        kill -TERM "$server_pid" 2>/dev/null || true
        wait "$server_pid" 2>/dev/null || true
    fi
}

pull_if_missing() {
    model="$1"

    if OLLAMA_HOST=127.0.0.1:11434 ollama show "$model" >/dev/null 2>&1; then
        echo "Ollama model is ready: $model"
        return
    fi

    echo "Pulling Ollama model: $model"
    OLLAMA_HOST=127.0.0.1:11434 ollama pull "$model"
}

trap stop_server EXIT INT TERM

ollama serve &
server_pid="$!"

attempt=0
until OLLAMA_HOST=127.0.0.1:11434 ollama list >/dev/null 2>&1; do
    if ! kill -0 "$server_pid" 2>/dev/null; then
        echo "Ollama server stopped before becoming ready." >&2
        wait "$server_pid"
    fi

    attempt=$((attempt + 1))

    if [ "$attempt" -ge 120 ]; then
        echo "Ollama server did not become ready within 120 seconds." >&2
        exit 1
    fi

    sleep 1
done

pull_if_missing "${OLLAMA_MODEL:?OLLAMA_MODEL is required}"
pull_if_missing "${OLLAMA_EMBED_MODEL:?OLLAMA_EMBED_MODEL is required}"

echo "Ollama server and required models are ready."
wait "$server_pid"
