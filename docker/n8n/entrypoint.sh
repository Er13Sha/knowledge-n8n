#!/bin/sh
set -eu

n8n import:workflow --input=/opt/knowledge-workflows.json
n8n publish:workflow --id=knowledgeIndex01
n8n publish:workflow --id=knowledgeSearch1
n8n publish:workflow --id=knowledgeDelete1

exec n8n "$@"
