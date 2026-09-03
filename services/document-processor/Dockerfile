FROM python:3.13-slim-bookworm

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PIP_NO_CACHE_DIR=1

WORKDIR /service

RUN apt-get -o Acquire::Retries=5 update \
    && apt-get -o Acquire::Retries=5 install -y --no-install-recommends \
        curl \
        tesseract-ocr \
        tesseract-ocr-eng \
        tesseract-ocr-rus \
        libreoffice-writer libreoffice-calc \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt ./
RUN pip install --no-cache-dir --requirement requirements.txt

COPY app ./app

RUN useradd --create-home --uid 10001 processor \
    && chown -R processor:processor /service

USER processor

EXPOSE 8000

CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000", "--workers", "2"]
