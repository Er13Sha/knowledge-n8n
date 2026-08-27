from pathlib import Path

import pymupdf
from fastapi.testclient import TestClient

from app.main import app
from app import processor
from app.processor import normalize_text, prepare_pdf, split_text


def test_normalizes_and_splits_text_with_overlap() -> None:
    text = "  Alpha   beta\n\nGamma delta epsilon zeta  "

    assert normalize_text(text) == "Alpha beta\nGamma delta epsilon zeta"
    assert split_text(text, 18, 5) == ["Alpha beta\nGamma", "Gamma delta", "delta epsilon zeta"]


def test_prepare_endpoint_requires_internal_token(monkeypatch) -> None:
    monkeypatch.setenv("DOCUMENT_PROCESSOR_TOKEN", "secret")

    response = TestClient(app).post(
        "/v1/documents/prepare",
        files={"document": ("document.pdf", b"%PDF-invalid", "application/pdf")},
    )

    assert response.status_code == 401


def test_prepare_endpoint_extracts_text_and_builds_chunks(monkeypatch, tmp_path: Path) -> None:
    monkeypatch.setenv("DOCUMENT_PROCESSOR_TOKEN", "secret")
    pdf_path = tmp_path / "document.pdf"
    pdf = pymupdf.open()
    page = pdf.new_page()
    page.insert_text((72, 72), "Knowledge base document with enough searchable text.")
    pdf.save(pdf_path)
    pdf.close()

    response = TestClient(app).post(
        "/v1/documents/prepare",
        headers={"X-Internal-Token": "secret"},
        files={"document": ("document.pdf", pdf_path.read_bytes(), "application/pdf")},
        data={
            "chunk_size": "200",
            "chunk_overlap": "20",
            "ocr_languages": "eng",
            "ocr_dpi": "200",
        },
    )

    assert response.status_code == 200
    payload = response.json()
    assert payload["pages"][0]["page"] == 1
    assert payload["pages"][0]["ocr_used"] is False
    assert "Knowledge base document" in payload["chunks"][0]["text"]


def test_prepare_pdf_uses_ocr_for_a_scanned_page(monkeypatch, tmp_path: Path) -> None:
    pdf_path = tmp_path / "scanned.pdf"
    pdf = pymupdf.open()
    pdf.new_page()
    pdf.save(pdf_path)
    pdf.close()
    monkeypatch.setattr(
        processor,
        "_ocr_page",
        lambda *args, **kwargs: "Recognized text from a scanned page.",
    )

    prepared = prepare_pdf(
        pdf_path,
        chunk_size=200,
        chunk_overlap=20,
        ocr_languages="eng",
        ocr_dpi=200,
        ocr_min_text_chars=40,
        max_pages=10,
    )

    assert prepared["pages"][0]["ocr_used"] is True
    assert prepared["chunks"][0]["text"] == "Recognized text from a scanned page."
