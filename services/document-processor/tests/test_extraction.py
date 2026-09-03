from pathlib import Path

import pytest

from app.processor import (
    DocumentPreparationError,
    _decode_bytes_with_encoding,
    _extract_csv,
    detect_format,
)


def test_detects_pdf_from_signature(tmp_path: Path) -> None:
    path = tmp_path / "unknown.bin"
    path.write_bytes(b"%PDF-1.7\n")

    assert detect_format(path, "unknown.bin", "application/octet-stream") == "pdf"


def test_detects_csv_from_content(tmp_path: Path) -> None:
    path = tmp_path / "data.bin"
    path.write_text("name;value\nAlice;10\n", encoding="utf-8")

    assert detect_format(path, "data.bin", "application/octet-stream") == "csv"


def test_detects_supported_extension_as_fallback(tmp_path: Path) -> None:
    path = tmp_path / "report.docx"
    path.write_bytes(b"not-a-real-docx")

    assert detect_format(path, "report.docx", "application/octet-stream") == "docx"


def test_decodes_russian_csv_in_cp1251(tmp_path: Path) -> None:
    path = tmp_path / "data.csv"
    path.write_bytes("Имя;Город\nАлиса;Москва\n".encode("cp1251"))

    result = _extract_csv(path, max_chars=10000, max_table_rows=100)

    assert result["encoding"] == "cp1251"
    assert "Москва" in result["text"]
    assert result["metadata"] == {"encoding": "cp1251"}


def test_decodes_excel_utf16_csv_without_bom_in_first_column(tmp_path: Path) -> None:
    path = tmp_path / "data.csv"
    path.write_bytes("Имя;Город\nАлиса;Астана\n".encode("utf-16"))

    text, encoding = _decode_bytes_with_encoding(path.read_bytes())

    assert encoding == "utf-16-le"
    assert text.startswith("Имя;Город")
    assert "\ufeff" not in text


def test_rejects_csv_with_already_lost_characters(tmp_path: Path) -> None:
    path = tmp_path / "damaged.csv"
    path.write_text("Имя;Город\nАлиса;����\n", encoding="utf-8")

    with pytest.raises(DocumentPreparationError, match="повреждённые символы кодировки"):
        _extract_csv(path, max_chars=10000, max_table_rows=100)
