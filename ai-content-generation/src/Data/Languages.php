<?php

namespace WPWand\Data;

/**
 * Shared language list (the value stored in wpwand_language is the name).
 *
 * Self-contained — the complete list lives here (no dependency on the legacy
 * wpwand_language_array()).
 */
final class Languages
{
    /**
     * @var string[]
     */
    private const NAMES = [
        'English', 'Afrikaans', 'Arabic', 'Armenian', 'Bosnian', 'Bulgarian', 'Chinese',
        'Croatian', 'Czech', 'Danish', 'Dutch', 'Estonian', 'Filipino', 'Finnish',
        'French', 'German', 'Greek', 'Hebrew', 'Hindi', 'Hungarian', 'Indonesian',
        'Italian', 'Japanese', 'Korean', 'Latvian', 'Lithuanian', 'Malay', 'Norwegian',
        'Persian', 'Polish', 'Portuguese', 'Romanian', 'Russian', 'Serbian', 'Slovak',
        'Slovenian', 'Spanish', 'Swedish', 'Thai', 'Turkish', 'Ukrainian', 'Urdu',
        'Vietnamese',
    ];

    /**
     * @return string[]
     */
    public static function all(): array
    {
        return self::NAMES;
    }
}
