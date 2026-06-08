<?php

/**
 * Score a fill-in-the-blank question with partial credit per blank.
 *
 * @param array<string, mixed> $question  Row with points and decoded settings
 * @param array<int, mixed>    $blanksIn  Student answers indexed by blank position
 */
function scoreFillBlankQuestion(array $question, array $blanksIn): float
{
    $settings = is_array($question['settings'] ?? null) ? $question['settings'] : [];
    $defs = $settings['blanks'] ?? [];
    $n = count($defs);
    if ($n === 0) {
        return 0.0;
    }

    $correctPairs = 0;
    for ($i = 0; $i < $n; $i++) {
        $given = isset($blanksIn[$i]) ? trim((string) $blanksIn[$i]) : '';
        $acceptable = $defs[$i]['answers'] ?? [];
        $caseInsensitive = (bool) ($defs[$i]['case_insensitive'] ?? true);
        $matched = false;
        foreach ((array) $acceptable as $acc) {
            $a = trim((string) $acc);
            if ($caseInsensitive) {
                if (strcasecmp($given, $a) === 0) {
                    $matched = true;
                    break;
                }
            } elseif ($given === $a) {
                $matched = true;
                break;
            }
        }
        if ($matched) {
            $correctPairs++;
        }
    }

    if ($correctPairs === 0) {
        return 0.0;
    }

    $scoringMode = $settings['scoring_mode'] ?? 'partial';
    if ($scoringMode === 'all_or_nothing') {
        return $correctPairs === $n ? round((float) $question['points'], 2) : 0.0;
    }

    return round((float) $question['points'] * ($correctPairs / $n), 2);
}

/**
 * Score a matching question with partial credit per correct pair.
 *
 * @param array<string, mixed>       $question Row with points and decoded settings
 * @param array<int|string, mixed>   $mapIn    Selected right-column index per left row
 */
function scoreMatchingQuestion(array $question, array $mapIn): float
{
    $settings = is_array($question['settings'] ?? null) ? $question['settings'] : [];
    $m = $settings['matching'] ?? null;
    if (!is_array($m) || empty($m['correct_map'])) {
        return 0.0;
    }

    $correctMap = $m['correct_map'];
    $n = count($correctMap);
    if ($n === 0) {
        return 0.0;
    }

    $correctPairs = 0;
    for ($i = 0; $i < $n; $i++) {
        $selected = isset($mapIn[$i]) ? (int) $mapIn[$i] : -1;
        $expected = (int) ($correctMap[$i] ?? -1);
        if ($selected === $expected) {
            $correctPairs++;
        }
    }

    if ($correctPairs === 0) {
        return 0.0;
    }

    return round((float) $question['points'] * ($correctPairs / $n), 2);
}
