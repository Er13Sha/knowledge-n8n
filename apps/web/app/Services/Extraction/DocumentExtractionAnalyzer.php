<?php

namespace App\Services\Extraction;

use Illuminate\Support\Str;

class DocumentExtractionAnalyzer
{
    /** @param array<string, mixed> $document */
    public function analyze(array $document): array
    {
        $text = $this->normalize((string) ($document['text'] ?? ''));
        $lines = $text === '' ? [] : (preg_split('/\R/u', $text) ?: []);
        $type = $this->detectType($text);
        $metadata = is_array($document['metadata'] ?? null) ? $document['metadata'] : [];
        $tables = is_array($document['tables'] ?? null) ? $document['tables'] : [];
        $stats = is_array($document['stats'] ?? null) ? $document['stats'] : [];
        $emails = $this->emails($text);
        $phones = $this->phones($text);
        $keyValues = $this->keyValues($lines);
        $fields = $this->fields($text, $emails, $phones, $keyValues);

        return [
            'document_type' => $type,
            'document_type_label' => $this->typeLabel($type),
            'format' => (string) ($document['format'] ?? 'other'),
            'title' => $this->title($lines, $metadata),
            'language' => $this->language($text),
            'snippet' => $text === '' ? null : mb_substr(preg_replace('/\s+/u', ' ', $text) ?: $text, 0, 240).(mb_strlen($text) > 240 ? '…' : ''),
            'emails' => $emails,
            'phones' => $phones,
            'urls' => $this->urls($text),
            'links' => $this->links($text),
            'dates' => $this->matches($text, [
                '/\b\d{1,2}[.\/]\d{1,2}[.\/]\d{2,4}\b/u',
                '/\b\d{4}-\d{2}-\d{2}\b/u',
                '/\b\d{1,2}\s+(?:янв(?:аря)?|фев(?:раля)?|марта?|апр(?:еля)?|ма[йя]|июн(?:я|ь)?|июл(?:я|ь)?|авг(?:уста)?|сент(?:ября)?|окт(?:ября)?|ноя(?:бря)?|дек(?:абря)?)\s+\d{4}/iu',
            ]),
            'amounts' => $this->matches($text, [
                '/(?:₽|\$|€|£)\s?\d[\d\s.,]*\d/u',
                '/\d[\d\s.,]*\d\s?(?:₽|руб\.?|р\.?|\$|€|£|RUB|USD|EUR)/iu',
            ]),
            'fields' => $fields,
            'key_values' => $keyValues,
            'keywords' => $this->keywords($text),
            'metadata' => $metadata,
            'tables' => $tables,
            'stats' => [
                'pages' => (int) ($document['pages'] ?? $stats['pages'] ?? 0),
                'sheets' => (int) ($stats['sheets'] ?? count($tables)),
                'rows' => (int) ($stats['rows'] ?? array_sum(array_map(static fn (mixed $table): int => is_array($table) && is_array($table['rows'] ?? null) ? count($table['rows']) : 0, $tables))),
                'words' => $text === '' ? 0 : count((preg_split('/\s+/u', trim($text)) ?: [])),
                'characters' => mb_strlen($text),
                'ocr_used' => (bool) ($stats['ocr_used'] ?? false),
                'text_extracted' => $text !== '',
            ],
            'resume' => $type === 'resume' ? $this->resume($text, $lines) : null,
            'text' => mb_substr($text, 0, (int) config('services.extraction.max_text_chars', 200_000)),
            'text_truncated' => (bool) ($stats['text_truncated'] ?? false) || mb_strlen($text) > (int) config('services.extraction.max_text_chars', 200_000),
        ];
    }

    private function normalize(string $text): string
    {
        $text = Str::of($text)->replace("\0", '')->toString();
        $lines = preg_split('/\R/u', $text) ?: [];
        $lines = array_map(static fn (string $line): string => trim(preg_replace('/[ \t]+/u', ' ', $line) ?: $line), $lines);

        return trim(implode("\n", array_values(array_filter($lines, static fn (string $line): bool => $line !== ''))));
    }

    private function detectType(string $text): string
    {
        $categories = [
            'resume' => ['резюме', 'опыт работы', 'навыки', 'образование', 'resume', 'work experience', 'curriculum vitae'],
            'invoice' => ['счёт', 'счет', 'счёт-фактура', 'invoice', 'инвойс', 'итого', 'к оплате', 'ндс', 'vat', 'накладная', 'инн', 'кпп'],
            'contract' => ['договор', 'контракт', 'соглашение', 'agreement', 'предмет договора', 'обязательства', 'подписи сторон'],
            'letter' => ['уважаемый', 'уважаемая', 'с уважением', 'sincerely', 'настоящим письмом'],
            'report' => ['отчёт', 'отчет', 'доклад', 'report', 'результаты', 'выводы', 'за период'],
            'article' => ['аннотация', 'abstract', 'список литературы', 'библиография', 'references', 'doi'],
            'financial' => ['бухгалтерский баланс', 'balance sheet', 'income statement', 'выручка', 'revenue', 'прибыль', 'активы'],
            'presentation' => ['слайд', 'презентация', 'agenda'],
        ];
        $haystack = mb_strtolower($text);
        $scores = [];
        foreach ($categories as $type => $keywords) {
            $scores[$type] = count(array_filter($keywords, static fn (string $keyword): bool => str_contains($haystack, $keyword)));
        }
        arsort($scores);
        $type = array_key_first($scores);

        return $type !== null && ($scores[$type] ?? 0) >= 2 ? $type : 'other';
    }

    private function typeLabel(string $type): string
    {
        return [
            'resume' => 'Резюме', 'invoice' => 'Счёт / инвойс', 'contract' => 'Договор',
            'letter' => 'Письмо', 'report' => 'Отчёт', 'article' => 'Статья',
            'financial' => 'Финансовый документ', 'presentation' => 'Презентация', 'other' => 'Документ',
        ][$type] ?? 'Документ';
    }

    /** @param list<string> $lines @param array<string, mixed> $metadata */
    private function title(array $lines, array $metadata): ?string
    {
        $metadataTitle = $metadata['title'] ?? null;
        if (is_string($metadataTitle) && trim($metadataTitle) !== '') {
            return mb_substr(trim($metadataTitle), 0, 160);
        }
        foreach (array_slice($lines, 0, 6) as $line) {
            if (mb_strlen($line) >= 3 && mb_strlen($line) <= 160 && ! preg_match('/@|https?:\/\//i', $line)) {
                return $line;
            }
        }

        return $lines[0] ?? null;
    }

    private function language(string $text): string
    {
        $cyrillic = preg_match_all('/\p{Cyrillic}/u', $text) ?: 0;
        $latin = preg_match_all('/[A-Za-z]/', $text) ?: 0;
        $total = $cyrillic + $latin;
        if ($total < 5) return 'unknown';
        if ($cyrillic / $total > .6) return 'ru';
        if ($latin / $total > .6) return 'en';
        return 'mixed';
    }

    /** @return list<string> */
    private function emails(string $text): array
    {
        preg_match_all('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', $text, $matches);
        return array_slice(array_values(array_unique($matches[0] ?? [])), 0, 20);
    }

    /** @return list<string> */
    private function phones(string $text): array
    {
        preg_match_all('/(?<!\d)(\+?\d[\d\s().\-]{7,16}\d)(?!\d)/', $text, $matches);
        $result = [];
        foreach ($matches[1] ?? [] as $candidate) {
            $digits = preg_replace('/\D/', '', $candidate) ?: '';
            if (strlen($digits) >= 9 && strlen($digits) <= 15 && preg_match('/[\s().+\-]/', $candidate)) {
                $result[$digits] = trim($candidate);
            }
        }
        return array_slice(array_values($result), 0, 10);
    }

    /** @return list<string> */
    private function urls(string $text): array
    {
        preg_match_all('#https?://[^\s<>"\')\]]+|www\.[^\s<>"\')\]]+#i', $text, $matches);
        return array_slice(array_values(array_unique(array_map(static fn (string $url): string => rtrim($url, '.,;:)]'), $matches[0] ?? []))), 0, 20);
    }

    /** @return array<string, string> */
    private function links(string $text): array
    {
        $links = [];
        foreach (['linkedin', 'github'] as $site) {
            if (preg_match('#(?:https?://)?(?:www\.)?'.preg_quote($site, '#').'\.com/[^\s)\]]+#i', $text, $match)) {
                $links[$site] = str_starts_with($match[0], 'http') ? $match[0] : 'https://'.$match[0];
            }
        }
        return $links;
    }

    /** @param list<string> $patterns @return list<string> */
    private function matches(string $text, array $patterns): array
    {
        $values = [];
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $text, $matches);
            foreach ($matches[0] ?? [] as $value) $values[mb_strtolower($value)] = trim($value);
        }
        return array_slice(array_values($values), 0, 30);
    }

    /** @param list<string> $lines @return list<array{label: string, value: string}> */
    private function keyValues(array $lines): array
    {
        $values = [];
        foreach ($lines as $line) {
            if (preg_match('/^\s*([\p{L}][\p{L}\d\s.№#\/\-]{1,38}?)\s*[:：]\s*(\S.{0,160})$/u', $line, $match)) {
                $key = mb_strtolower(trim($match[1]));
                if (! isset($values[$key]) && count(preg_split('/\s+/u', $key) ?: []) <= 6) {
                    $values[$key] = ['label' => trim($match[1]), 'value' => trim($match[2])];
                }
            }
        }
        return array_slice(array_values($values), 0, 30);
    }

    /**
     * @param list<string> $emails
     * @param list<string> $phones
     * @param list<array{label: string, value: string}> $keyValues
     * @return list<array{label: string, value: string}>
     */
    private function fields(string $text, array $emails, array $phones, array $keyValues): array
    {
        $fields = [];
        $patterns = [
            'ИИН' => '/(?:\bИИН\b|И\.И\.Н\.|идентификационн(?:ый|ого)\s+номер)\s*[:№-]?\s*((?:\d[\s-]*){12})/iu',
            'БИН' => '/(?:\bБИН\b|Б\.И\.Н\.)\s*[:№-]?\s*((?:\d[\s-]*){12})/iu',
            'ФИО' => '/(?:\bФИО\b|ф\.?\s*и\.?\s*о\.?|полное\s+имя|фамилия\s*,?\s*имя\s*,?\s*отчество)\s*[:№-]?\s*([^\r\n;]{3,120})/iu',
            'Дата рождения' => '/(?:дата\s+рождения|родил(?:ся|ась))\s*[:№-]?\s*([^\r\n;]{3,80})/iu',
            'СНИЛС' => '/\bСНИЛС\b\s*[:№-]?\s*((?:\d[\s-]*){11})/iu',
            'Паспорт' => '/(?:паспорт|удостоверение\s+личности)\s*[:№-]?\s*([^\r\n;]{5,100})/iu',
            'Пол' => '/\bпол\b\s*[:№-]?\s*(мужской|женский|м|ж)/iu',
            'Адрес' => '/(?:адрес|место\s+жительства)\s*[:№-]?\s*([^\r\n;]{3,200})/iu',
            'Организация' => '/(?:организация|компания|место\s+работы)\s*[:№-]?\s*([^\r\n;]{2,160})/iu',
            'Диагноз' => '/\bдиагноз\b\s*[:№-]?\s*([^\r\n;]{3,200})/iu',
            'Дата выдачи' => '/(?:дата\s+выдачи|выдан)\s*[:№-]?\s*([^\r\n;]{3,80})/iu',
        ];

        foreach ($patterns as $label => $pattern) {
            if (preg_match($pattern, $text, $match) === 1) {
                $value = (string) ($match[1] ?? '');

                if (in_array($label, ['ИИН', 'БИН'], true)) {
                    $value = preg_replace('/\D/u', '', $value) ?: '';
                }

                $this->addField($fields, $label, $value);
            }
        }

        foreach ($emails as $email) {
            $this->addField($fields, 'E-mail', $email);
        }

        foreach ($phones as $phone) {
            $this->addField($fields, 'Телефон', $phone);
        }

        foreach ($keyValues as $field) {
            $this->addField($fields, $field['label'], $field['value']);
        }

        return array_slice(array_values($fields), 0, 40);
    }

    /** @param array<string, array{label: string, value: string}> $fields */
    private function addField(array &$fields, string $label, string $value): void
    {
        $label = $this->normalizeFieldLabel($label);
        $value = trim($value, " \t\n\r\0\x0B:;,.'\"");

        if ($label === '' || $value === '') {
            return;
        }

        $key = mb_strtolower($label);

        if (! isset($fields[$key])) {
            $fields[$key] = ['label' => $label, 'value' => mb_substr($value, 0, 240)];
        }
    }

    private function normalizeFieldLabel(string $label): string
    {
        $label = trim(preg_replace('/[._-]+/u', ' ', $label) ?: $label);
        $normalized = mb_strtolower(preg_replace('/\s+/u', ' ', $label) ?: $label);

        return [
            'иин' => 'ИИН',
            'и и н' => 'ИИН',
            'бин' => 'БИН',
            'б и н' => 'БИН',
            'фио' => 'ФИО',
            'ф и о' => 'ФИО',
            'телефон' => 'Телефон',
            'тел' => 'Телефон',
            'email' => 'E-mail',
            'e mail' => 'E-mail',
            'электронная почта' => 'E-mail',
            'снилс' => 'СНИЛС',
            'паспорт' => 'Паспорт',
            'удостоверение личности' => 'Паспорт',
            'пол' => 'Пол',
            'диагноз' => 'Диагноз',
            'дата выдачи' => 'Дата выдачи',
        ][$normalized] ?? $label;
    }

    /** @return list<string> */
    private function keywords(string $text): array
    {
        preg_match_all('/\p{L}{4,}/u', mb_strtolower($text), $matches);
        $stopwords = array_flip(['который', 'которые', 'также', 'более', 'менее', 'этот', 'этого', 'данные', 'работы', 'навыки', 'образование', 'the', 'this', 'that', 'with', 'from', 'your', 'have', 'will', 'which', 'their']);
        $frequency = [];
        foreach ($matches[0] ?? [] as $word) if (! isset($stopwords[$word])) $frequency[$word] = ($frequency[$word] ?? 0) + 1;
        arsort($frequency);
        return array_slice(array_keys(array_filter($frequency, static fn (int $count): bool => $count >= 2)), 0, 12);
    }

    /** @param list<string> $lines @return array<string, mixed> */
    private function resume(string $text, array $lines): array
    {
        $skills = [];
        foreach (['PHP', 'Laravel', 'Python', 'JavaScript', 'TypeScript', 'Vue.js', 'React', 'SQL', 'Excel', 'Docker', 'Git', 'Figma', 'С++', 'Java'] as $skill) {
            if (stripos($text, $skill) !== false) $skills[] = $skill;
        }
        $education = array_values(array_filter($lines, static fn (string $line): bool => preg_match('/университет|институт|колледж|бакалавр|магистр|university|college|bachelor|master/i', $line) === 1));
        $years = null;
        if (preg_match('/(\d{1,2})\s*\+?\s*(?:лет|года|год|years?)\s*(?:опыта|стажа|experience|exp)?/iu', $text, $match)) $years = (int) $match[1];
        $role = null;
        $profiles = ['Frontend-разработчик' => ['javascript', 'typescript', 'vue', 'react'], 'Backend-разработчик' => ['php', 'laravel', 'python', 'sql'], 'Аналитик данных' => ['excel', 'sql', 'power bi', 'pandas']];
        $scores = [];
        foreach ($profiles as $candidate => $keywords) $scores[$candidate] = count(array_filter($keywords, static fn (string $keyword): bool => stripos($text, $keyword) !== false));
        arsort($scores);
        if (($scores[array_key_first($scores)] ?? 0) > 0) $role = array_key_first($scores);
        return ['name' => $lines[0] ?? null, 'skills' => $skills, 'education' => array_slice($education, 0, 12), 'years_of_experience' => $years, 'suggested_role' => $role, 'role_confidence' => $role === null ? 0 : min(1, ($scores[$role] ?? 0) / 4), 'role_matched_skills' => $role === null ? [] : $skills];
    }
}
