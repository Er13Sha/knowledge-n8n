import type {
    HighlightedTextPart,
    KnowledgeDocumentStatus,
    SearchMatch,
} from '@/features/knowledge/types';

export function statusColor(status: KnowledgeDocumentStatus): string {
    return (
        {
            pending: 'warning',
            processing: 'info',
            indexed: 'success',
            failed: 'error',
        } satisfies Record<KnowledgeDocumentStatus, string>
    )[status];
}

export function statusIcon(status: KnowledgeDocumentStatus): string {
    return (
        {
            pending: 'mdi-clock-outline',
            processing: 'mdi-progress-clock',
            indexed: 'mdi-check-circle-outline',
            failed: 'mdi-alert-circle-outline',
        } satisfies Record<KnowledgeDocumentStatus, string>
    )[status];
}

export function highlightedExcerpt(match: SearchMatch): HighlightedTextPart[] {
    const terms = [...match.matched_terms]
        .sort((left, right) => right.length - left.length)
        .map((term) => term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));

    if (terms.length === 0) {
        return [{ text: match.excerpt, highlighted: false }];
    }

    const expression = new RegExp(`(${terms.join('|')})`, 'giu');
    const highlightExpression = new RegExp(`^(${terms.join('|')})$`, 'iu');

    return match.excerpt
        .split(expression)
        .filter(Boolean)
        .map((part) => ({
            text: part,
            highlighted: highlightExpression.test(part),
        }));
}
