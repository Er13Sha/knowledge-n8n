export function formatFileSize(bytes: number): string {
    if (bytes === 0) {
        return '0 Б';
    }

    const units = ['Б', 'КБ', 'МБ', 'ГБ'];
    const unitIndex = Math.min(
        Math.floor(Math.log(bytes) / Math.log(1024)),
        units.length - 1,
    );
    const value = bytes / 1024 ** unitIndex;

    return `${value.toLocaleString('ru-RU', {
        maximumFractionDigits: unitIndex === 0 ? 0 : 1,
    })} ${units[unitIndex]}`;
}

export function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}
