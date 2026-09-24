<?php

function rs_text_width($fontFile, $fontSize, $text) {
    $box = imagettfbbox($fontSize, 0, $fontFile, $text);
    return abs($box[2] - $box[0]);
}

function rs_draw_centered_text($img, $text, $fontFile, $fontSize, $color, $centerX, $baselineY) {
    $box = imagettfbbox($fontSize, 0, $fontFile, $text);
    $textWidth = abs($box[2] - $box[0]);
    $x = (int) round($centerX - ($textWidth / 2));
    imagettftext($img, $fontSize, 0, $x, $baselineY, $color, $fontFile, $text);
}

function rs_wrap_name_lines($text, $fontFile, $fontSize, $maxWidth, $maxLines = 3) {
    $words = preg_split('/\s+/u', trim($text));
    $lines = [];
    $current = '';

    foreach ($words as $word) {
        $test = ($current === '') ? $word : ($current . ' ' . $word);

        if (rs_text_width($fontFile, $fontSize, $test) <= $maxWidth) {
            $current = $test;
        } else {
            if ($current !== '') {
                $lines[] = $current;
                $current = $word;
            } else {
                $lines[] = $word;
                $current = '';
            }

            if (count($lines) >= $maxLines - 1) {
                break;
            }
        }
    }

    $remainingWords = [];
    $alreadyUsed = implode(' ', $lines);
    $full = trim($text);

    if ($alreadyUsed !== '') {
        $usedCount = str_word_count($alreadyUsed, 0);
        $allWords = preg_split('/\s+/u', trim($full));
        $remainingWords = array_slice($allWords, $usedCount);
    } else {
        $remainingWords = $words;
    }

    $tail = trim(($current !== '' ? $current . ' ' : '') . implode(' ', $remainingWords));

    if ($tail !== '') {
        $lines[] = $tail;
    }

    // Limitar a máximo 3 líneas
    $lines = array_slice($lines, 0, $maxLines);

    return $lines;
}

function rs_draw_name_block($img, $name, $fontFile, $fontSize, $color, $centerX, $lineYs, $maxWidth) {
    $lines = rs_wrap_name_lines($name, $fontFile, $fontSize, $maxWidth, 3);
    $count = count($lines);

    // 3 renglones disponibles: [0]=arriba, [1]=centro, [2]=abajo
    if ($count <= 1) {
        $slots = [1];        // solo renglón central
    } elseif ($count == 2) {
        $slots = [1, 2];     // dos renglones inferiores
    } else {
        $slots = [0, 1, 2];  // los tres
    }

    foreach ($lines as $i => $line) {
        $slotIndex = $slots[$i];
        rs_draw_centered_text(
            $img,
            $line,
            $fontFile,
            $fontSize,
            $color,
            $centerX,
            $lineYs[$slotIndex]
        );
    }
}
