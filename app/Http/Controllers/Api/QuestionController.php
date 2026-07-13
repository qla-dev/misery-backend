<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\QuestionResource;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QuestionController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'category' => ['nullable', Rule::in(array_keys(Question::CATEGORIES))],
            'difficulty' => ['nullable', 'integer', Rule::in(array_keys(Question::DIFFICULTIES))],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $questions = Question::query()
            ->where('status', true)
            ->when($data['category'] ?? null, fn ($query, $category) => $query->where('category', $category))
            ->when($data['difficulty'] ?? null, fn ($query, $difficulty) => $query->where('difficulty', $difficulty))
            ->inRandomOrder()
            ->limit($data['limit'] ?? 50)
            ->get();

        return QuestionResource::collection($questions);
    }
}
