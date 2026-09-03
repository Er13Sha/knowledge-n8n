from __future__ import annotations

import os
import secrets
import tempfile
from pathlib import Path

from fastapi import FastAPI, File, Form, Header, HTTPException, UploadFile, status
from pydantic import BaseModel

from app.processor import DocumentPreparationError, extract_document, prepare_pdf


class PreparedPage(BaseModel):
    page: int
    text: str
    ocr_used: bool


class PreparedChunk(BaseModel):
    page: int
    chunk_index: int
    text: str


class PreparationResponse(BaseModel):
    pages: list[PreparedPage]
    chunks: list[PreparedChunk]


class ExtractionTable(BaseModel):
    sheet: str
    columns: list[str]
    rows: list[list[str]]
    truncated: bool


class ExtractionResponse(BaseModel):
    format: str
    mime_type: str
    text: str
    pages: int
    tables: list[ExtractionTable]
    metadata: dict[str, str]
    stats: dict[str, int | bool]


app = FastAPI(title="Knowledge document processor", version="1.1.0")


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/v1/documents/prepare", response_model=PreparationResponse)
async def prepare_document(
    document: UploadFile = File(...),
    chunk_size: int = Form(1400, ge=200, le=10000),
    chunk_overlap: int = Form(200, ge=0, le=5000),
    ocr_languages: str = Form("rus+eng", min_length=3, max_length=100),
    ocr_dpi: int = Form(200, ge=100, le=400),
    x_internal_token: str | None = Header(default=None),
) -> PreparationResponse:
    _authorize(x_internal_token)
    if chunk_overlap >= chunk_size:
        raise HTTPException(status_code=status.HTTP_422_UNPROCESSABLE_ENTITY, detail="chunk_overlap должен быть меньше chunk_size.")
    if document.content_type not in {"application/pdf", "application/octet-stream"}:
        raise HTTPException(status_code=status.HTTP_415_UNSUPPORTED_MEDIA_TYPE, detail="Поддерживаются только PDF-документы.")
    temporary_path = await _store_upload(document, "pdf", "DOCUMENT_PROCESSOR_MAX_FILE_MB")
    try:
        prepared = prepare_pdf(temporary_path, chunk_size=chunk_size, chunk_overlap=chunk_overlap, ocr_languages=ocr_languages, ocr_dpi=ocr_dpi, ocr_min_text_chars=_integer_env("DOCUMENT_PROCESSOR_OCR_MIN_TEXT_CHARS", 40), max_pages=_integer_env("DOCUMENT_PROCESSOR_MAX_PAGES", 2000))
        return PreparationResponse.model_validate(prepared)
    except DocumentPreparationError as exception:
        raise HTTPException(status_code=status.HTTP_422_UNPROCESSABLE_ENTITY, detail=str(exception)) from exception
    finally:
        temporary_path.unlink(missing_ok=True)


@app.post("/v1/documents/extract", response_model=ExtractionResponse)
async def extract_document_endpoint(
    document: UploadFile = File(...),
    max_text_chars: int = Form(200000, ge=1000, le=1000000),
    max_table_rows: int = Form(2000, ge=10, le=10000),
    x_internal_token: str | None = Header(default=None),
) -> ExtractionResponse:
    _authorize(x_internal_token)
    temporary_path = await _store_upload(document, Path(document.filename or "document").suffix or ".bin", "EXTRACTION_MAX_FILE_MB")
    try:
        extracted = extract_document(temporary_path, filename=document.filename or "document", content_type=document.content_type or "application/octet-stream", ocr_languages=os.getenv("RAG_OCR_LANGUAGES", "rus+eng"), ocr_dpi=_integer_env("RAG_OCR_DPI", 200), max_text_chars=max_text_chars, max_table_rows=max_table_rows, max_pages=_integer_env("EXTRACTION_MAX_PAGES", 2000))
        return ExtractionResponse.model_validate(extracted)
    except DocumentPreparationError as exception:
        raise HTTPException(status_code=status.HTTP_422_UNPROCESSABLE_ENTITY, detail=str(exception)) from exception
    finally:
        temporary_path.unlink(missing_ok=True)


def _authorize(provided_token: str | None) -> None:
    expected_token = os.getenv("DOCUMENT_PROCESSOR_TOKEN", "")
    if not expected_token or not provided_token or not secrets.compare_digest(expected_token, provided_token):
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Неверный внутренний токен обработчика документов.")


async def _store_upload(document: UploadFile, suffix: str, limit_env: str) -> Path:
    max_bytes = _integer_env(limit_env, 20 if limit_env == "EXTRACTION_MAX_FILE_MB" else 100) * 1024 * 1024
    total_bytes = 0
    temporary_suffix = suffix if suffix.startswith(".") else f".{suffix}"
    with tempfile.NamedTemporaryFile(prefix="knowledge-", suffix=temporary_suffix, delete=False) as target:
        temporary_path = Path(target.name)
        while upload_chunk := await document.read(1024 * 1024):
            total_bytes += len(upload_chunk)
            if total_bytes > max_bytes:
                temporary_path.unlink(missing_ok=True)
                raise HTTPException(status_code=status.HTTP_413_REQUEST_ENTITY_TOO_LARGE, detail="Файл превышает допустимый размер.")
            target.write(upload_chunk)
    if total_bytes == 0:
        temporary_path.unlink(missing_ok=True)
        raise HTTPException(status_code=status.HTTP_422_UNPROCESSABLE_ENTITY, detail="Загруженный файл пуст.")
    return temporary_path


def _integer_env(name: str, default: int) -> int:
    try:
        return int(os.getenv(name, str(default)))
    except ValueError:
        return default
