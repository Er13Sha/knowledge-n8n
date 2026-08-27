from __future__ import annotations

import re
from pathlib import Path

import pymupdf
import pytesseract
from PIL import Image


class DocumentPreparationError(RuntimeError):
    pass


def normalize_text(value: str) -> str:
    normalized_lines = []

    for line in value.splitlines():
        normalized_line = re.sub(r"[ \t]+", " ", line).strip()

        if normalized_line:
            normalized_lines.append(normalized_line)

    return "\n".join(normalized_lines).strip()


def split_text(value: str, chunk_size: int, overlap: int) -> list[str]:
    if chunk_size <= 0:
        raise ValueError("chunk_size must be positive")

    if overlap < 0 or overlap >= chunk_size:
        raise ValueError("overlap must be between zero and chunk_size")

    text = normalize_text(value)

    if not text:
        return []

    chunks: list[str] = []
    start = 0

    while start < len(text):
        end = min(start + chunk_size, len(text))

        if end < len(text):
            candidate = text[start:end]
            boundary = max(candidate.rfind("\n"), candidate.rfind(" "))

            if boundary > chunk_size // 2:
                end = start + boundary

        chunk = text[start:end].strip()

        if chunk:
            chunks.append(chunk)

        if end >= len(text):
            break

        start = max(end - overlap, start + 1)

    return chunks


def prepare_pdf(
    path: Path,
    *,
    chunk_size: int,
    chunk_overlap: int,
    ocr_languages: str,
    ocr_dpi: int,
    ocr_min_text_chars: int,
    max_pages: int,
) -> dict[str, list[dict[str, object]]]:
    pages: list[dict[str, object]] = []
    chunks: list[dict[str, object]] = []

    try:
        document = pymupdf.open(path)
    except Exception as exception:
        raise DocumentPreparationError(f"Не удалось открыть PDF: {exception}") from exception

    try:
        if document.page_count == 0:
            raise DocumentPreparationError("PDF не содержит страниц.")

        if document.page_count > max_pages:
            raise DocumentPreparationError(
                f"PDF содержит {document.page_count} страниц; разрешено не более {max_pages}."
            )

        for page_index, page in enumerate(document):
            text = normalize_text(page.get_text("text", sort=True))
            ocr_used = False

            if len(text) < ocr_min_text_chars:
                ocr_text = _ocr_page(
                    page,
                    ocr_languages=ocr_languages,
                    ocr_dpi=ocr_dpi,
                )

                if len(ocr_text) > len(text):
                    text = ocr_text
                    ocr_used = True

            page_number = page_index + 1
            pages.append({"page": page_number, "text": text, "ocr_used": ocr_used})

            for page_chunk in split_text(text, chunk_size, chunk_overlap):
                chunks.append(
                    {
                        "page": page_number,
                        "chunk_index": len(chunks),
                        "text": page_chunk,
                    }
                )
    finally:
        document.close()

    if not chunks:
        raise DocumentPreparationError(
            "В PDF нет текста, который удалось извлечь или распознать."
        )

    return {"pages": pages, "chunks": chunks}


def _ocr_page(page: pymupdf.Page, *, ocr_languages: str, ocr_dpi: int) -> str:
    try:
        pixmap = page.get_pixmap(dpi=ocr_dpi, alpha=False)
        image = Image.frombytes("RGB", (pixmap.width, pixmap.height), pixmap.samples)
        return normalize_text(pytesseract.image_to_string(image, lang=ocr_languages))
    except Exception as exception:
        raise DocumentPreparationError(
            f"Не удалось распознать страницу {page.number + 1}: {exception}"
        ) from exception
