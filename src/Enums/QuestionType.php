<?php

declare(strict_types=1);

namespace VeliraPay\Enums;

/**
 * The kind of field a payment link's checkout question is shown as.
 */
enum QuestionType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Email = 'email';
    case Phone = 'phone';
    case Url = 'url';
    case Date = 'date';
    case Select = 'select';
    case Checkbox = 'checkbox';
    case Country = 'country';
}
