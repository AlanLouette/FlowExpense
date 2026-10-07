<?php
function markdown_to_html(string $markdown): string
{
    $markdown = str_replace(["\r\n", "\r"], "\n", trim($markdown));
    if ($markdown === '') {
        return '';
    }

    $blocks = preg_split("/\n{2,}/", $markdown);
    $html = [];
    foreach ($blocks as $block) {
        $lines = explode("\n", $block);
        $first = $lines[0] ?? '';
        if (preg_match('/^(#{1,6})\s+(.+)/', $first, $headingMatch)) {
            $level = min(6, strlen($headingMatch[1]));
            $content = $headingMatch[2];
            $html[] = sprintf('<h%d>%s</h%d>', $level, parse_inline_markdown($content), $level);
            continue;
        }

        $isBulletList = true;
        foreach ($lines as $line) {
            if (!preg_match('/^\s*[-*+]\s+.+/', $line)) {
                $isBulletList = false;
                break;
            }
        }
        if ($isBulletList) {
            $items = array_map(function ($line) {
                $content = preg_replace('/^\s*[-*+]\s+/', '', $line);
                return '<li>' . parse_inline_markdown($content) . '</li>';
            }, $lines);
            $html[] = '<ul>' . implode('', $items) . '</ul>';
            continue;
        }

        $isNumberedList = true;
        foreach ($lines as $line) {
            if (!preg_match('/^\s*\d+\.\s+.+/', $line)) {
                $isNumberedList = false;
                break;
            }
        }
        if ($isNumberedList) {
            $items = array_map(function ($line) {
                $content = preg_replace('/^\s*\d+\.\s+/', '', $line);
                return '<li>' . parse_inline_markdown($content) . '</li>';
            }, $lines);
            $html[] = '<ol>' . implode('', $items) . '</ol>';
            continue;
        }

        $paragraph = implode("\n", $lines);
        $html[] = '<p>' . parse_inline_markdown($paragraph, true) . '</p>';
    }

    return implode("\n", $html);
}

function parse_inline_markdown(string $text, bool $preserveLineBreaks = false): string
{
    $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $escaped = preg_replace_callback('/`([^`]+)`/', function ($matches) {
        return '<code>' . htmlspecialchars($matches[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code>';
    }, $escaped);

    $escaped = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $escaped);
    $escaped = preg_replace('/__(.+?)__/s', '<strong>$1</strong>', $escaped);
    $escaped = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/s', '<em>$1</em>', $escaped);
    $escaped = preg_replace('/_(.+?)_/s', '<em>$1</em>', $escaped);

    $escaped = preg_replace_callback('/\[(.+?)\]\((https?:[^\s)]+)\)/', function ($matches) {
        $label = htmlspecialchars($matches[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $url = htmlspecialchars($matches[2], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<a href="' . $url . '" target="_blank" rel="noopener">' . $label . '</a>';
    }, $escaped);

    if ($preserveLineBreaks) {
        $escaped = nl2br($escaped);
    }

    return $escaped;
}
