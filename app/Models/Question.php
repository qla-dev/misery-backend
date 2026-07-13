<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Question extends Model
{
    public const CATEGORIES = [
        'movies' => 'Movies',
        'sports' => 'Sports',
        'music' => 'Music',
        'geography' => 'Geography',
        'history' => 'History',
        'science' => 'Science',
        'gaming' => 'Gaming',
        'general' => 'General Knowledge',
    ];

    public const DIFFICULTIES = [
        1 => 'Easy',
        2 => 'Medium',
        3 => 'Hard',
        4 => 'Expert',
    ];

    protected $fillable = ['question', 'answer', 'category', 'difficulty', 'status', 'generated_by_ai'];

    protected $casts = [
        'difficulty' => 'integer',
        'status' => 'boolean',
        'generated_by_ai' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Question $question) {
            $question->normalized_hash = hash('sha256', self::normalize($question->question));
        });
    }

    public static function normalize(string $value): string
    {
        $withoutPunctuation = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', Str::lower($withoutPunctuation)) ?? '');
    }
}
