<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Normalizes free-text names into a comparison key so near-identical
 * entries — differing only in case, spacing, or singular/plural wording —
 * are recognized as the same thing, without altering the name actually
 * shown to users.
 *
 * "Meal Expense", "MEAL EXPENSE", "Meals Expense", and "meal expenses" all
 * normalize to "meal expense". "Meal Expense" and "Food Expense" do not —
 * this only collapses casing/spacing/pluralization, never whole words, so
 * meaningfully different names always stay different.
 */
class NameNormalizer
{
    public static function normalize(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        // Collapse any run of whitespace (double spaces from copy-paste,
        // tabs, etc.) down to a single space, then lowercase.
        $value = preg_replace('/\s+/u', ' ', $value);
        $value = mb_strtolower($value, 'UTF-8');

        // Singularize each word on its own — via Laravel's linguistically
        // aware inflector, not naive suffix-stripping — so "Expenses" and
        // "Expense" compare equal. Non-alphabetic tokens (numbers, "&", the
        // "-" in "Accounts Payable - Trade") are left untouched.
        $words = array_map(
            fn (string $word) => preg_match('/^\p{L}+$/u', $word) ? Str::singular($word) : $word,
            explode(' ', $value)
        );

        return implode(' ', $words);
    }
}
