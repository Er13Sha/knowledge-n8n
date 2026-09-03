# Knowledge System / DocFlow AI — документация проекта

Версия документации: 1 сентября 2026 года.

## Содержание

1. [Назначение проекта](#назначение-проекта)
2. [Архитектура](#архитектура)
3. [Структура репозитория](#структура-репозитория)
4. [Быстрый запуск](#быстрый-запуск)
5. [Переменные окружения](#переменные-окружения)
6. [Основные модули приложения](#основные-модули-приложения)
7. [Вкладка «Извлечение данных»](#вкладка-извлечение-данных)
8. [Python-сервис обработки документов](#python-сервис-обработки-документов)
9. [База знаний и RAG-поиск](#база-знаний-и-rag-поиск)
10. [API](#api)
11. [Права доступа](#права-доступа)
12. [Frontend](#frontend)
13. [Очереди и фоновые задачи](#очереди-и-фоновые-задачи)
14. [Тестирование и качество кода](#тестирование-и-качество-кода)
15. [Диагностика проблем](#диагностика-проблем)
16. [Безопасность и эксплуатация](#безопасность-и-эксплуатация)
17. [Как расширять проект](#как-расширять-проект)
18. [План изучения Python на этом проекте](#план-изучения-python-на-этом-проекте)

## Назначение проекта

Knowledge System — внутренняя B2B-платформа для работы с документами:

- загрузка документов в базу знаний;
- асинхронная индексация содержимого;
- полнотекстовый и семантический поиск;
- ответы на вопросы по документам через локальную LLM;
- управление пользователями, ролями и подразделениями;
- отдельная вкладка «Извлечение данных» для OCR и структурированного разбора файлов;
- REST API и Swagger-интерфейс для администраторов.

Стек проекта:

- Laravel и PHP 8.4 — основное web-приложение;
- Vue 3, TypeScript, Vuetify и Vite — интерфейс;
- Python 3.13, FastAPI — обработка документов;
- PostgreSQL — основная база данных;
- RabbitMQ — очередь фоновых задач;
- PyMuPDF — чтение PDF;
- Tesseract OCR — распознавание сканов и изображений;
- Pillow — работа с изображениями;
- python-docx — DOCX;
- openpyxl — XLSX;
- LibreOffice — конвертация старых DOC/XLS;
- Ollama — локальная языковая модель и embeddings;
- Qdrant — векторное хранилище;
- n8n — интеграционные workflow;
- Docker Compose — запуск всей системы.

## Архитектура

### Сервисы Docker Compose

| Сервис | Назначение | Порт на хосте по умолчанию |
|---|---|---:|
| `app` | Laravel, FrankenPHP, HTTP API и SPA | `8080` |
| `queue` | Laravel queue worker | нет внешнего порта |
| `postgres` | основная база данных | `5432` |
| `rabbitmq` | брокер очередей | `5672`, управление `15672` |
| `document-processor` | Python API для PDF/OCR/Office/CSV/TXT | `8001` |
| `ollama` | локальная LLM и модель embeddings | `11434` |
| `n8n` | workflow индексации, поиска и удаления | `5678` |
| `qdrant` | векторная база данных | `6333` |

Сервисы общаются внутри Docker-сети по именам сервисов. Например, Laravel обращается к Python по адресу `http://document-processor:8000`, а не через `localhost`.

### Поток извлечения данных

```text
Vue
  │ POST /api/extractions
  ▼
Laravel DocumentExtractionController
  │ сохраняет исходный файл и запись в PostgreSQL
  │ отправляет ProcessDocumentExtraction в RabbitMQ
  ▼
Laravel queue worker
  │ вызывает Python POST /v1/documents/extract
  ▼
Python FastAPI
  │ определяет фактический формат
  │ извлекает текст, OCR, таблицы и метаданные
  ▼
Laravel DocumentExtractionAnalyzer
  │ определяет тип документа и универсальные поля
  ▼
PostgreSQL
  │ сохраняет JSON-результат и статус completed
  ▼
Vue polling
  │ обновляет карточку результата
```

### Поток базы знаний

```text
Пользователь загружает PDF
        ↓
Laravel сохраняет файл и ставит IndexKnowledgeDocument в RabbitMQ
        ↓
Python /v1/documents/prepare извлекает текст страниц и делает OCR
        ↓
Ollama создаёт embedding-векторы
        ↓
Qdrant сохраняет векторы и метаданные чанков
        ↓
При поиске вопрос превращается в embedding
        ↓
Qdrant возвращает похожие фрагменты
        ↓
Ollama формирует ответ по найденным источникам
```

## Структура репозитория

```text
knowledge-system/
├── apps/
│   └── web/                         # Laravel + Vue
│       ├── app/
│       │   ├── Http/                # контроллеры, requests, resources
│       │   ├── Jobs/                # фоновые задачи Laravel
│       │   ├── Models/              # Eloquent-модели
│       │   ├── Services/            # бизнес-логика и интеграции
│       │   ├── Enums/               # перечисления статусов
│       │   └── Policies/            # проверки доступа
│       ├── database/
│       │   ├── migrations/
│       │   ├── seeders/
│       │   └── factories/
│       ├── resources/js/
│       │   ├── app/                 # layout, router, navigation
│       │   ├── modules/              # frontend-модули по предметным областям
│       │   └── shared/               # API-клиент и общие типы
│       ├── routes/                  # web.php и api.php
│       ├── tests/                   # Pest-тесты
│       ├── Dockerfile
│       └── composer.json
├── services/
│   └── document-processor/          # Python FastAPI
│       ├── app/main.py              # HTTP endpoints
│       ├── app/processor.py         # обработка файлов
│       ├── tests/                   # pytest-тесты
│       ├── Dockerfile
│       └── requirements.txt
├── automation/n8n/                  # Dockerfile и workflow n8n
├── infrastructure/ollama/           # entrypoint загрузки моделей
├── compose.yaml                     # весь Docker Compose stack
├── docker-start.sh                  # запуск и проверка healthcheck
├── .env.example                     # шаблон переменных окружения
└── docs/PROJECT_DOCUMENTATION.md    # этот документ
```

## Быстрый запуск

### Требования

Необходимы:

- Docker Desktop с работающим Docker Compose v2;
- Git;
- минимум 8–16 ГБ свободной оперативной памяти для полного stack;
- свободные порты `8080`, `8001`, `5432`, `5672`, `6333`, `11434`, `15672`, `5678` либо собственные значения в `.env`.

### Первый запуск

В корне проекта:

```bash
cp .env.example .env
```

В Windows PowerShell аналогичная команда:

```powershell
Copy-Item .env.example .env
```

Сгенерируйте `APP_KEY` одним из способов:

```bash
docker compose -f compose.yaml run --rm app php artisan key:generate --show
```

Полученное значение вставьте в `.env`:

```dotenv
APP_KEY=base64:...
```

Для рабочего окружения замените пароли и внутренние токены, особенно:

- `DB_PASSWORD`;
- `RABBITMQ_PASSWORD`;
- `DOCUMENT_PROCESSOR_TOKEN`;
- `RAG_INTERNAL_TOKEN`;
- `N8N_ENCRYPTION_KEY`;
- `QDRANT_API_KEY`.

Запустите stack:

```bash
./docker-start.sh
```

Скрипт:

1. проверяет Docker;
2. проверяет `APP_KEY`;
3. собирает образы;
4. запускает контейнеры;
5. применяет миграции;
6. ждёт healthcheck всех обязательных сервисов;
7. выводит адреса приложений.

Основные варианты:

```bash
./docker-start.sh --no-build       # запуск без пересборки образов
./docker-start.sh --no-migrate     # запуск без миграций
./docker-start.sh --pull           # сначала скачать базовые образы
./docker-start.sh --logs           # после запуска показать логи
./docker-start.sh --restart        # перезапустить stack
./docker-start.sh --down           # остановить и удалить контейнеры
```

После успешного запуска:

- приложение: <http://localhost:8080>;
- вкладка извлечения: <http://localhost:8080/extraction>;
- база знаний: <http://localhost:8080/knowledge>;
- n8n: <http://localhost:5678>;
- RabbitMQ Management: <http://localhost:15672>;
- Python healthcheck: <http://localhost:8001/health>;
- Qdrant dashboard: <http://localhost:6333/dashboard>.

### Учётная запись администратора

При запуске seeders создаётся или обновляется администратор из переменных:

```dotenv
ADMIN_NAME=Admin
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=password
```

Пароль `password` предназначен только для локальной разработки и должен быть заменён.

### Локальная разработка без Docker

Laravel находится в `apps/web`:

```bash
cd apps/web
composer install
npm ci
php artisan migrate
npm run build
```

Для разработки можно запустить frontend и Laravel-команды через Composer:

```bash
composer run dev
```

Для Python-сервиса потребуются Python 3.13, системный Tesseract с языками `rus` и `eng`, а также LibreOffice для DOC/XLS:

```bash
cd services/document-processor
python -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
uvicorn app.main:app --reload --host 0.0.0.0 --port 8000
```

В Windows PowerShell:

```powershell
.venv\Scripts\Activate.ps1
uvicorn app.main:app --reload --host 0.0.0.0 --port 8000
```

## Переменные окружения

Основной шаблон находится в [.env.example](/mnt/c/it/projects/knowledge-system/.env.example:1). Для Docker Compose используется `.env` в корне проекта.

### Laravel и HTTP

| Переменная | Назначение | Значение по умолчанию |
|---|---|---|
| `APP_KEY` | ключ шифрования Laravel | обязательна |
| `APP_ENV` | окружение | `local` |
| `APP_DEBUG` | подробные ошибки | `true` в шаблоне; в production `false` |
| `APP_URL` | базовый URL | `http://localhost:8000` |
| `HTTP_PORT` | порт приложения на хосте | `8080` в Compose |
| `LOG_LEVEL` | уровень логирования | `debug` в шаблоне |

### PostgreSQL

| Переменная | Назначение |
|---|---|
| `DB_HOST`, `DB_PORT` | адрес и порт PostgreSQL |
| `DB_DATABASE` | имя базы |
| `DB_USERNAME`, `DB_PASSWORD` | пользователь и пароль |
| `POSTGRES_PORT` | проброс порта на хост |

Внутри Compose `DB_HOST` автоматически переопределяется на `postgres`.

### RabbitMQ и очередь

| Переменная | Назначение | По умолчанию |
|---|---|---:|
| `QUEUE_CONNECTION` | драйвер очереди | `rabbitmq` |
| `RABBITMQ_HOST` | RabbitMQ внутри сети | `rabbitmq` в Docker |
| `RABBITMQ_PORT` | порт брокера | `5672` |
| `RABBITMQ_USER` | пользователь | `knowledge_system` |
| `RABBITMQ_PASSWORD` | пароль | заменить |
| `RABBITMQ_QUEUE` | имя очереди | `knowledge-documents` |
| `DB_QUEUE_RETRY_AFTER` | время повторной попытки | `960` секунд |

### Python document processor

| Переменная | Назначение | По умолчанию |
|---|---|---:|
| `DOCUMENT_PROCESSOR_URL` | URL Python-сервиса для Laravel | `http://localhost:8001` вне Docker |
| `DOCUMENT_PROCESSOR_TOKEN` | общий внутренний токен | обязательна для вызова |
| `DOCUMENT_PROCESSOR_TIMEOUT` | таймаут HTTP-запроса Laravel | `600` секунд |
| `DOCUMENT_PROCESSOR_PORT` | порт Python на хосте | `8001` |
| `DOCUMENT_PROCESSOR_MAX_FILE_MB` | лимит старого prepare API | `100` МБ |
| `EXTRACTION_MAX_FILE_MB` | лимит Python extraction endpoint | `20` МБ |
| `DOCUMENT_PROCESSOR_MAX_PAGES` | лимит страниц prepare API | `2000` |
| `EXTRACTION_MAX_PAGES` | лимит страниц extraction API | `2000` |
| `DOCUMENT_PROCESSOR_OCR_MIN_TEXT_CHARS` | порог запуска OCR для prepare | `40` символов |
| `RAG_OCR_LANGUAGES` | языки Tesseract | `rus+eng` |
| `RAG_OCR_DPI` | разрешение рендеринга PDF перед OCR | `200` |

Laravel дополнительно поддерживает:

```dotenv
EXTRACTION_MAX_TEXT_CHARS=200000
EXTRACTION_MAX_TABLE_ROWS=2000
```

Это ограничения результата текста и количества строк таблицы. Если значения переопределяются через Docker Compose, их необходимо передать и сервису `app`, и сервису `queue`.

### Ollama, n8n и Qdrant

| Переменная | Назначение |
|---|---|
| `OLLAMA_URL` | URL Ollama |
| `OLLAMA_MODEL` | модель ответов |
| `OLLAMA_EMBED_MODEL` | модель embeddings |
| `OLLAMA_TIMEOUT` | таймаут обращения к Ollama |
| `N8N_INDEX_WEBHOOK_URL` | workflow индексации |
| `N8N_SEARCH_WEBHOOK_URL` | workflow поиска |
| `N8N_DELETE_WEBHOOK_URL` | workflow удаления |
| `RAG_INTERNAL_TOKEN` | защита внутренних вызовов RAG и n8n |
| `QDRANT_URL` | URL векторной базы |
| `QDRANT_API_KEY` | ключ Qdrant |
| `QDRANT_COLLECTION` | имя коллекции |
| `RAG_CHUNK_SIZE` | размер текстового чанка |
| `RAG_CHUNK_OVERLAP` | перекрытие чанков |
| `RAG_TOP_K` | количество результатов семантического поиска |
| `RAG_SCORE_THRESHOLD` | минимальная оценка совпадения |

## Основные модули приложения

### Laravel

- `app/Http/Controllers/` — принимает HTTP-запросы и возвращает JSON/страницы.
- `app/Http/Requests/` — авторизация и валидация входных данных.
- `app/Http/Resources/` — единый формат JSON-ответов.
- `app/Models/` — Eloquent-модели и связи с базой.
- `app/Services/` — бизнес-логика, которую не следует помещать в контроллеры.
- `app/Jobs/` — фоновые задачи RabbitMQ.
- `app/Policies/` и `AccessManager` — права пользователей.
- `database/migrations/` — структура базы данных.
- `database/seeders/` — роли, permissions и администратор.

### Сущность DocumentExtraction

Таблица `document_extractions` хранит:

- владельца файла;
- исходное имя;
- диск и путь хранения;
- MIME-тип;
- фактически определённый формат;
- размер;
- статус;
- прогресс;
- JSON-результат;
- сообщение ошибки;
- время завершения.

Статусы:

| Статус | Значение |
|---|---|
| `pending` | задача создана и ждёт worker |
| `processing` | worker обрабатывает файл |
| `completed` | результат готов |
| `failed` | обработка завершилась ошибкой |

### Laravel-класс `ProcessDocumentExtraction`

[ProcessDocumentExtraction.php](/mnt/c/it/projects/knowledge-system/apps/web/app/Jobs/ProcessDocumentExtraction.php:1) выполняет следующие шаги:

1. загружает запись `DocumentExtraction`;
2. переводит её в `processing`, прогресс `10`;
3. отправляет исходный файл Python-сервису;
4. сохраняет фактический формат и MIME;
5. передаёт ответ анализатору Laravel;
6. сохраняет результат, ставит `completed` и прогресс `100`;
7. при исключении сохраняет статус `failed` и текст ошибки.

### Laravel-анализатор

[DocumentExtractionAnalyzer.php](/mnt/c/it/projects/knowledge-system/apps/web/app/Services/Extraction/DocumentExtractionAnalyzer.php:1) не читает бинарный файл. Он получает уже извлечённый Python текст и добавляет универсальные данные:

- тип документа: резюме, счёт, договор, письмо, отчёт и т. д.;
- заголовок;
- язык;
- e-mail, телефоны и ссылки;
- даты и суммы;
- заполненные поля: ФИО, ИИН, БИН, СНИЛС, паспорт, адрес, организация, диагноз;
- ключевые слова;
- данные резюме;
- статистику и метаданные.

## Вкладка «Извлечение данных»

Страница доступна по адресу `/extraction` и реализована в [DataExtractionPage.vue](/mnt/c/it/projects/knowledge-system/apps/web/resources/js/modules/extraction/DataExtractionPage.vue:1).

### Поддерживаемые форматы

- PDF, включая сканированные PDF;
- JPG, JPEG, PNG, TIFF, BMP;
- DOC и DOCX;
- XLS и XLSX;
- CSV;
- TXT.

### Сценарий пользователя

1. Открыть «Извлечение данных» в sidebar.
2. Нажать область загрузки или перетащить файл.
3. Проверить имя и размер выбранного файла.
4. Нажать «Извлечь данные».
5. В истории выбрать созданную задачу.
6. Дождаться статуса «Готово».
7. Просмотреть заполненные поля, таблицы, текст и метаданные.
8. При необходимости скачать исходник или JSON.

Frontend опрашивает API каждые 5 секунд, пока существуют задачи `pending` или `processing`.

### Формат результата

Результат содержит примерно такую структуру:

```json
{
  "document_type": "other",
  "document_type_label": "Документ",
  "format": "image",
  "title": "Справка",
  "language": "ru",
  "snippet": "краткий фрагмент текста",
  "fields": [
    {"label": "ФИО", "value": "Иван Иванов"},
    {"label": "Телефон", "value": "+7 777 123-45-67"}
  ],
  "emails": [],
  "phones": ["+7 777 123-45-67"],
  "urls": [],
  "links": {},
  "dates": ["01.09.2026"],
  "amounts": [],
  "key_values": [],
  "keywords": ["справка", "медицинский"],
  "metadata": {},
  "tables": [],
  "stats": {
    "pages": 1,
    "words": 25,
    "characters": 180,
    "ocr_used": true,
    "text_extracted": true
  },
  "resume": null,
  "text": "полный распознанный текст",
  "text_truncated": false
}
```

Поле `fields` выводится только если найдены непустые значения. Подписи нормализуются, а значения выводятся безопасно через Vue-интерполяцию.

### CSV и кодировки

CSV читается с попыткой определить:

- UTF-8;
- UTF-8 с BOM;
- Windows-1251;
- CP866;
- KOI8-R;
- UTF-16 LE/BE;
- UTF-32 LE/BE.

Разделитель определяется по содержимому: запятая, `;` или табуляция.

Первая непустая строка используется как заголовок таблицы, остальные строки — как данные. Если CSV уже содержит символы `�`, сервис останавливает обработку и сообщает, что исходный файл нужно пересохранить: повреждённые символы невозможно восстановить автоматически.

## Python-сервис обработки документов

Подробная точка входа находится в [main.py](/mnt/c/it/projects/knowledge-system/services/document-processor/app/main.py:1), а алгоритмы — в [processor.py](/mnt/c/it/projects/knowledge-system/services/document-processor/app/processor.py:1).

### Endpoint `/health`

```http
GET /health
```

Ответ:

```json
{"status": "ok"}
```

### Endpoint `/v1/documents/extract`

```http
POST /v1/documents/extract
```

Заголовок:

```http
X-Internal-Token: <DOCUMENT_PROCESSOR_TOKEN>
```

Multipart-поле:

```text
document=<file>
```

Дополнительные поля:

- `max_text_chars` — максимум символов текста;
- `max_table_rows` — максимум строк в таблице.

Endpoint возвращает извлечённый текст, таблицы, MIME, формат, метаданные и статистику.

### Endpoint `/v1/documents/prepare`

```http
POST /v1/documents/prepare
```

Используется для индексации PDF в базе знаний. Дополнительные параметры:

- `chunk_size` — размер чанка;
- `chunk_overlap` — размер перекрытия;
- `ocr_languages` — языки OCR;
- `ocr_dpi` — разрешение OCR.

Ответ содержит страницы и чанки:

```json
{
  "pages": [
    {"page": 1, "text": "...", "ocr_used": false}
  ],
  "chunks": [
    {"page": 1, "chunk_index": 0, "text": "..."}
  ]
}
```

### Определение типа

`detect_format` проверяет файл в таком порядке:

1. сигнатура `%PDF-` для PDF;
2. бинарная сигнатура старых Office-файлов;
3. ZIP-содержимое DOCX/XLSX;
4. валидность изображения через Pillow;
5. расширение файла;
6. содержимое как CSV или TXT.

Это лучше, чем доверять только расширению: пользователь может переименовать файл или загрузить файл с неправильным MIME.

### Обработка PDF

1. PyMuPDF открывает PDF.
2. Для каждой страницы вызывается `page.get_text`.
3. Если текста меньше порога, страница рендерится в изображение.
4. Tesseract распознаёт изображение.
5. Выбирается более длинный результат.
6. Все страницы объединяются в один текст.

### Обработка изображения

1. Pillow открывает и проверяет изображение.
2. Tesseract получает изображение.
3. OCR работает с языками из `RAG_OCR_LANGUAGES`.
4. Текст нормализуется и возвращается.

### Обработка DOC/DOCX

DOCX читается через `python-docx`: извлекаются абзацы и таблицы. Старый DOC конвертируется LibreOffice в DOCX, после чего обрабатывается тем же кодом.

### Обработка XLS/XLSX

XLSX читается через `openpyxl` в режиме `read_only=True`. Каждый лист становится отдельной таблицей. Старый XLS конвертируется LibreOffice в XLSX.

### Обработка CSV/TXT

Файл сначала читается как байты. Это важно для определения кодировки. После декодирования CSV разбирается стандартным модулем `csv`, а TXT просто нормализуется.

## База знаний и RAG-поиск

Вкладка «База знаний» отличается от «Извлечение данных».

### Извлечение данных

Нужно получить читаемый результат конкретного файла:

- поля;
- текст;
- таблицы;
- даты;
- суммы;
- контакты.

Результат сохраняется в `document_extractions` и не добавляется автоматически в базу знаний.

### База знаний

Нужно индексировать документы для последующего поиска и вопросов:

- PDF разбивается на чанки;
- чанки преобразуются в embeddings;
- embeddings сохраняются в Qdrant;
- при вопросе ищутся похожие фрагменты;
- Ollama составляет ответ по источникам.

### Два режима поиска

- `rag` — семантический поиск по embeddings и ответ LLM;
- `fulltext` — точный лексический поиск по словам.

Основная логика находится в:

- `app/Services/Rag/KnowledgeIndexer.php`;
- `app/Services/Rag/KnowledgeSearchEngine.php`;
- `app/Services/Rag/OllamaClient.php`;
- `app/Services/Rag/QdrantVectorStore.php`;
- `automation/n8n/workflows/knowledge-workflows.json`.

## API

Все пользовательские API-маршруты используют Laravel session authentication и CSRF-заголовок. Frontend отправляет cookies через `credentials: 'same-origin'`.

### Аутентификация

```http
POST /api/auth/login
GET  /api/auth/user
POST /api/auth/logout
```

Пример входа:

```json
{
  "email": "admin@example.com",
  "password": "password",
  "remember": true
}
```

### Извлечение данных

```http
GET    /api/extractions
POST   /api/extractions
GET    /api/extractions/{id}
POST   /api/extractions/{id}/retry
DELETE /api/extractions/{id}
GET    /api/extractions/{id}/download
GET    /api/extractions/{id}/download-json
```

Загрузка:

```bash
curl -X POST http://localhost:8080/api/extractions \
  -H "Accept: application/json" \
  -H "X-CSRF-TOKEN: <csrf-token>" \
  -b cookies.txt \
  -F "document=@./document.pdf"
```

Успешная загрузка возвращает HTTP `202 Accepted`:

```json
{
  "data": {
    "id": 12,
    "original_name": "document.pdf",
    "status": "pending",
    "progress": 0
  },
  "message": "Файл загружен и отправлен на извлечение данных."
}
```

Повторить можно только задачу со статусом `failed`. Удаление удаляет и запись, и исходный файл из Laravel storage.

### База знаний

```http
GET    /api/knowledge/documents
POST   /api/knowledge/documents
GET    /api/knowledge/documents/{id}
PATCH  /api/knowledge/documents/{id}
POST   /api/knowledge/documents/{id}/retry-indexing
DELETE /api/knowledge/documents/{id}
POST   /api/knowledge/search
```

Загрузка документа базы знаний требует подразделение, название, тип, дату утверждения и PDF-файл.

### Административные API

```http
GET    /api/admin/departments
POST   /api/admin/departments
PATCH  /api/admin/departments/{id}
DELETE /api/admin/departments/{id}
GET    /api/admin/employees
POST   /api/admin/employees
PATCH  /api/admin/employees/{id}
POST   /api/admin/roles
PUT    /api/admin/roles/{id}
DELETE /api/admin/roles/{id}
```

Эти маршруты доступны только super-admin или штатному администратору согласно middleware.

### OpenAPI и Swagger

```http
GET /docs/api
GET /docs/openapi.json
```

Интерфейс доступен администраторам после входа в систему.

## Права доступа

Permission `extraction.use` выдаётся ролям `employee` и `admin` миграцией и `AccessControlSeeder`.

Проверка выполняется в `StoreDocumentExtractionRequest` и контроллере.

Правила истории извлечений:

- обычный пользователь видит только собственные записи;
- другой сотрудник не может получить чужой результат по ID;
- администратор видит все записи;
- исходный файл и JSON доступны с теми же ограничениями;
- удаление также ограничено видимостью записи.

Системный super-admin получает административный доступ независимо от обычных permission-проверок.

## Frontend

Frontend запускается из `apps/web`.

### Главные файлы

- `resources/js/app.ts` — точка входа;
- `resources/js/app/App.vue` — корневой компонент;
- `resources/js/app/router.ts` — SPA-маршруты;
- `resources/js/app/AppNavigation.vue` — sidebar и верхняя панель;
- `resources/js/modules/extraction/DataExtractionPage.vue` — страница извлечения;
- `resources/js/modules/extraction/api.ts` — вызовы extraction API;
- `resources/js/modules/extraction/useDocumentExtraction.ts` — состояние, загрузка и polling;
- `resources/js/modules/extraction/types.ts` — TypeScript-модели;
- `resources/js/app/styles.css` — общие стили.

### Роутинг

Laravel отдаёт один SPA-шаблон для `/dashboard`, `/knowledge`, `/extraction` и настроек. Vue читает `window.location.pathname` и выбирает модуль через `resolveAppRoute`.

### Polling

`useDocumentExtraction` каждые 5 секунд:

- повторно загружает список, если есть `pending` или `processing`;
- обновляет выбранную запись, когда активных задач нет.

### Безопасный вывод

Текст результата выводится через стандартную Vue-интерполяцию:

```vue
{{ result.text }}
```

Необработанный HTML не вставляется через `v-html`. Это снижает риск XSS при обработке пользовательских документов.

## Очереди и фоновые задачи

Очередь используется, чтобы загрузка не ждала OCR и тяжёлую обработку.

Worker запускается Compose-командой:

```text
php artisan queue:work rabbitmq
  --queue=knowledge-documents
  --sleep=1
  --tries=3
  --timeout=900
  --memory=512
```

Основные задачи:

- `ProcessDocumentExtraction` — извлечение данных;
- `IndexKnowledgeDocument` — подготовка PDF, embeddings и запись в Qdrant;
- `DeleteKnowledgeDocumentIndex` — удаление индекса из Qdrant.

Если worker выключен, записи будут оставаться в `pending`.

Проверка worker:

```bash
docker compose ps queue
docker compose logs --tail=200 queue
```

## Тестирование и качество кода

### Laravel

Команды выполняются из `apps/web`:

```bash
composer install
php artisan test --compact
vendor/bin/pint
phpstan analyse
```

Основные тесты извлечения:

- `tests/Feature/DocumentExtractionApiTest.php`;
- `tests/Unit/DocumentExtractionAnalyzerTest.php`.

### Python

Из каталога `services/document-processor`:

```bash
pip install -r requirements.txt
pip install -r requirements-dev.txt
pytest -q
```

Тесты покрывают:

- определение PDF по сигнатуре;
- CSV по содержимому;
- fallback по расширению;
- CP1251 и UTF-16;
- повреждённые символы CSV.

### Frontend

Из `apps/web`:

```bash
npm ci
npm run types:check
npm run lint:check
npm run build
```

Production build создаёт файлы в `apps/web/public/build`.

## Диагностика проблем

### Кнопка «Извлечение данных» открывает Server Error

Проверить:

```bash
docker compose ps app

docker compose logs --tail=200 app
```

Если ошибка PHP — проверить файл и стек Laravel. После изменения PHP очистить кеш:

```bash
docker compose exec app php artisan optimize:clear
```

### Ошибка `Unparenthesized a ? b : c ?: d`

В PHP 8 запрещено смешивать тернарный оператор и `?:` без скобок. Нужно явно расставить скобки или переписать условие через `if`.

### Задача постоянно `pending`

Проверить queue worker и RabbitMQ:

```bash
docker compose ps queue rabbitmq
docker compose logs --tail=200 queue rabbitmq
```

### Задача `failed`

Смотреть ошибку в интерфейсе или журнале:

```bash
docker compose logs --tail=200 queue document-processor
```

Частые причины:

- Python недоступен;
- неверный `DOCUMENT_PROCESSOR_TOKEN`;
- отсутствует Tesseract-язык;
- отсутствует LibreOffice для DOC/XLS;
- файл повреждён;
- превышен лимит размера или страниц;
- CSV уже содержит потерянные символы `�`.

### Python отвечает `401`

Значения `DOCUMENT_PROCESSOR_TOKEN` в сервисах `app`, `queue` и `document-processor` должны совпадать.

### OCR не распознаёт русский текст

Проверить наличие языкового пакета внутри контейнера:

```bash
docker compose exec document-processor tesseract --list-langs
```

В списке должны быть `rus` и `eng`.

### Docker не может скачать образ

Ошибка разрешения образа Docker Hub не относится к коду приложения. Проверить:

1. запущен ли Docker Desktop;
2. есть ли доступ к Docker Hub;
3. не блокирует ли сеть proxy/VPN;
4. выполнить `docker login` при необходимости;
5. повторить `./docker-start.sh --pull`.

### Frontend показывает старую версию

Пересобрать frontend:

```bash
cd apps/web
npm run build
```

Для Docker пересобрать образ приложения:

```bash
docker compose build app queue
docker compose up -d app queue
```

В браузере выполнить `Ctrl+F5`.

### CSV отображается кракозябрами

Файл нужно пересохранить в UTF-8 или Windows-1251. В Excel обычно используется пункт «Сохранить как» → «CSV UTF-8».

Если исходный CSV уже содержит `����` или `�`, восстановить исходные буквы программно невозможно.

## Безопасность и эксплуатация

### Файлы

Исходные документы хранятся на приватном Laravel-диске, а не в публичной web-папке. Скачивание проходит через контроллер с проверкой авторизации.

### Внутренние сервисы

Python и RAG endpoint защищены внутренними токенами. Не публикуйте их наружу без reverse proxy и дополнительной авторизации.

### Персональные данные

Документы могут содержать ФИО, ИИН, телефоны, медицинскую и финансовую информацию. Рекомендуется:

- использовать сильные пароли;
- не включать `APP_DEBUG` в production;
- ограничить доступ к Docker-портам firewall;
- регулярно делать backup PostgreSQL и Docker volumes;
- не передавать production-токены в Git;
- ограничить срок хранения документов организационной политикой.

### Хранилища Docker

Состояние сервисов сохраняется в volumes:

- `postgres-data`;
- `rabbitmq-data`;
- `qdrant-data`;
- `ollama-data`;
- `n8n-data`;
- `app-storage`;
- `caddy-data`;
- `caddy-config`.

Удаление volumes может привести к потере данных. Не используйте `docker compose down -v` без подтверждённого backup.

## Как расширять проект

### Добавить новый формат в Python

1. Добавить библиотеку в `services/document-processor/requirements.txt`.
2. Установить её в `Dockerfile`, если нужны системные зависимости.
3. Добавить определение в `detect_format`.
4. Создать функцию `_extract_<format>`.
5. Вернуть единую структуру: `format`, `mime_type`, `text`, `pages`, `tables`, `metadata`, `stats`.
6. Добавить маршрут вызова в `extract_document`.
7. Добавить тесты в `tests/test_extraction.py`.
8. Пересобрать image `document-processor`.

### Добавить поле в результат

1. Добавить извлечение поля в `DocumentExtractionAnalyzer`.
2. Добавить тип в `resources/js/modules/extraction/types.ts`.
3. Добавить безопасный вывод в `DataExtractionPage.vue`.
4. При необходимости добавить тест анализатора.
5. Запустить PHP и frontend-проверки.

### Добавить permission

1. Добавить ключ в `AccessManager`.
2. Добавить permission в `AccessControlSeeder`.
3. Назначить permission штатным ролям.
4. Добавить проверку в FormRequest, controller или policy.
5. Добавить тест доступа.

### Изменить лимиты

Проверить все уровни:

1. Laravel FormRequest — пользовательский лимит загрузки.
2. `config/services.php` — лимиты текста и таблиц.
3. Compose environment — лимиты Python-контейнера.
4. Python endpoint — ограничения Form-параметров.
5. Queue timeout — максимальная длительность фоновой задачи.

## План изучения Python на этом проекте

Рекомендуемый порядок:

1. изучить типы `str`, `list`, `dict`, `tuple`;
2. понять `if`, `for`, `try/except`, функции и `return`;
3. прочитать `normalize_text`;
4. прочитать `_cell` и `_limit`;
5. изучить `detect_format`;
6. изучить `extract_document` как маршрутизатор;
7. прочитать `_extract_csv`;
8. прочитать `_extract_image` и OCR;
9. прочитать `_extract_pdf`;
10. перейти к FastAPI-декораторам в `main.py`;
11. запускать тесты после каждого изменения.

Полезные упражнения:

- добавить обработку JSON;
- добавить поддержку казахского OCR через `kaz`;
- вывести количество пустых строк CSV;
- добавить номер страницы в текст PDF;
- добавить поле с количеством распознанных символов;
- написать тест для нового формата.

Главный принцип текущего Python-кода: он не пытается сам распознать всё одним алгоритмом. Сначала определяется формат, затем вызывается специализированная библиотека, после чего результат приводится к единой JSON-структуре для Laravel.
