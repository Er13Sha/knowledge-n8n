# DocFlow AI / Knowledge System

Внутренняя платформа для обработки документов, базы знаний и поиска по содержимому.

## Быстрый старт

```bash
cp .env.example .env
./docker-start.sh
```

После запуска приложение доступно по адресу <http://localhost:8080>.

- [Полная документация проекта](docs/PROJECT_DOCUMENTATION.md)
- [Извлечение данных](http://localhost:8080/extraction)
- [База знаний](http://localhost:8080/knowledge)
- [Swagger API](http://localhost:8080/docs/api)

Основные технологии: Laravel, Vue 3, TypeScript, Python/FastAPI, PostgreSQL, RabbitMQ, Docker, Tesseract OCR, Ollama, Qdrant и n8n.
