<?php

function normalize_nominal_input($value)
{
    $digits = preg_replace('/[^\d]/', '', (string) $value);

    return $digits ?? '';
}

function nominal_input_to_number($value)
{
    $normalized = normalize_nominal_input($value);

    if ($normalized === '') {
        return 0;
    }

    return (float) $normalized;
}
