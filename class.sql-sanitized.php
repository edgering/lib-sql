<?php

/**
 *  MyQuerySanitized
 *
 *  Stateless sanitizer utility used by MyQuery::sanitizeValues().
 *  It does not connect to SQL and only transforms input based on
 *  provided column metadata (SHOW COLUMNS result rows).
 *
 *  Methods overview:
 *  - normalizeSqlDateValue($value, $fieldType)
 *      Controlled types: DATE, DATETIME, TIMESTAMP
 *  - normalizeSqlBooleanValue($value)
 *      Controlled types: BOOLEAN-like fields (BOO, TINYINT(1))
 *  - normalizeSqlNumberValue($value)
 *      Controlled types: INT, DECIMAL, FLOAT, DOUBLE
 *  - normalizeSqlNullKeywordValue($value, $nullable)
 *      Controlled types: nullable columns (maps null-like keywords to NULL)
 *  - sanitizeTypedFieldValue($value, $row)
 *      Controlled types: dispatches by row type to date/boolean/number rules
 *  - applyEmptyValueDefault($value, $row)
 *      Controlled types: empty values with NULL/default/numeric fallback handling
 *  - sanitizeValues($values, $columns, $setEmptyDefault)
 *      Controlled types: orchestrates sanitization for all provided columns
 */

class MyQuerySanitized
{
    function normalizeSqlDateValue($value, $fieldType)
    {
        if (!is_string($value)) {
            return $value;
        }

        $value = trim($value);

        if ($value === '') {
            return '';
        }

        // Common zero-date placeholders from forms should be treated as empty.
        if (
            preg_match('/^0{1,2}[\.\/-]0{1,2}[\.\/-]0{2,4}(?:\s+0{1,2}:0{2}(?::0{2})?)?$/', $value)
            || preg_match('/^0{4}-0{2}-0{2}(?:\s+0{2}:0{2}(?::0{2})?)?$/', $value)
        ) {
            return '';
        }

        $hasTime = preg_match('/STAMP|DATETIME/i', $fieldType) === 1;

        // Numeric day-first formats like 6.7.2026, 06/07/26, 6-7-2026 14:30.
        if (preg_match('/^(\d{1,2})[\.\/-](\d{1,2})[\.\/-](\d{2,4})(?:\s+(\d{1,2}):(\d{2})(?::(\d{2}))?)?$/', $value, $m)) {
            $day = (int)$m[1];
            $month = (int)$m[2];
            $year = (int)$m[3];

            if ($year < 100) {
                $year += ($year >= 70) ? 1900 : 2000;
            }

            if (checkdate($month, $day, $year)) {
                $date = sprintf('%04d-%02d-%02d', $year, $month, $day);

                if (!$hasTime) {
                    return $date;
                }

                $hour = isset($m[4]) ? (int)$m[4] : 0;
                $minute = isset($m[5]) ? (int)$m[5] : 0;
                $second = isset($m[6]) ? (int)$m[6] : 0;

                return sprintf('%s %02d:%02d:%02d', $date, $hour, $minute, $second);
            }
        }

        // Textual formats like "6 July 2026", "July 6, 2026", etc.
        $timestamp = strtotime($value);

        if ($timestamp === FALSE) {
            return $value;
        }

        return date($hasTime ? 'Y-m-d H:i:s' : 'Y-m-d', $timestamp);
    }

    function normalizeSqlBooleanValue($value)
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_int($value) || is_float($value)) {
            return ((float)$value) != 0.0 ? 1 : 0;
        }

        if (!is_string($value)) {
            return $value;
        }

        $value = strtolower(trim($value));

        if ($value === '') {
            return 0;
        }

        if (in_array($value, array('1', 'true', 'yes', 'on', 'y', 'ano'), true)) {
            return 1;
        }

        if (in_array($value, array('0', 'false', 'no', 'off', 'n', 'ne'), true)) {
            return 0;
        }

        return $value;
    }

    function normalizeSqlNumberValue($value)
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (!is_string($value)) {
            return 0;
        }

        $value = trim($value);

        if ($value === '') {
            return 0;
        }

        // Accounting style negatives: (123,45) => -123,45
        if (preg_match('/^\((.*)\)$/', $value, $m)) {
            $value = '-' . trim($m[1]);
        }

        $value = str_replace(' ', '', $value);

        $hasComma = strpos($value, ',') !== FALSE;
        $hasDot = strpos($value, '.') !== FALSE;

        if ($hasComma && $hasDot) {
            // Last separator is treated as decimal separator, other as thousand separators.
            $lastComma = strrpos($value, ',');
            $lastDot = strrpos($value, '.');

            if ($lastComma > $lastDot) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        } else if ($hasComma) {
            $value = str_replace(',', '.', $value);
        }

        $value = preg_replace('/[^0-9\.\-]/', '', $value);

        if ($value === '' || $value === '-' || $value === '.' || $value === '-.') {
            return 0;
        }

        return $value;
    }

    function normalizeSqlNullKeywordValue($value, $nullable)
    {
        if (!$nullable || !is_string($value)) {
            return $value;
        }

        $normalized = strtolower(trim($value));

        if (in_array($normalized, array('null', 'none', 'n/a', 'na', '-'), true)) {
            return NULL;
        }

        return $value;
    }

    function sanitizeTypedFieldValue($value, $row)
    {
        $value = $this->normalizeSqlNullKeywordValue($value, $row->{"Null"} === 'YES');

        if ($value === NULL) {
            return NULL;
        }

        if (preg_match('/(DATE|STAMP)/i', $row->Type)) {
            return $this->normalizeSqlDateValue($value, $row->Type);
        }

        if (preg_match('/BOO|TINYINT\(1\)/i', $row->Type)) {
            return $this->normalizeSqlBooleanValue($value);
        }

        if (preg_match('/INT|DEC|FLO|DOU/i', $row->Type)) {
            return $this->normalizeSqlNumberValue($value);
        }

        return $value;
    }

    function applyEmptyValueDefault($value, $row)
    {
        if ($value !== '') {
            return $value;
        }

        if ($row->{"Null"} === 'YES') {
            return NULL;
        }

        if ($row->{"Default"} !== NULL && $row->{"Default"} !== '') {
            return sprintf('DEFAULT(`%s`)', $row->Field);
        }

        if (preg_match('/INT|BOO|DEC|FLO|DOU/i', $row->Type)) {
            return 0;
        }

        return $value;
    }

    function sanitizeValues($values = array(), $columns = array(), $setEmptyDefault = FALSE)
    {
        if (!is_array($columns) || !count($columns)) {
            return $values;
        }

        foreach ($columns as $row) {

            if (!is_object($row) || !isset($row->Field)) {
                continue;
            }

            // -- non existing value, skip

            if (!isset($values[$row->Field])) {
                continue;
            }

            // -- catch function as value

            if (preg_match('/\(\)$/', $values[$row->Field])) {
                continue;
            }

            $sanitized = $this->sanitizeTypedFieldValue($values[$row->Field], $row);

            if ($sanitized !== NULL && preg_match('/DEC|FLO|DOU/i', $row->Type)) {
                $sanitized = (float)$sanitized;
            } else if ($sanitized !== NULL && preg_match('/INT|BOO|TINYINT\(1\)/i', $row->Type)) {
                $sanitized = (int)$sanitized;
            }

            $values[$row->Field] = $this->applyEmptyValueDefault($sanitized, $row);
        }

        return $values;
    }
}
