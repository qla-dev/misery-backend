<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StackResource;
use App\Models\Stack;

class StackController extends Controller
{
    public function index()
    {
        return StackResource::collection(
            Stack::query()
                ->withCount([
                    'cards as active_cards_count' => fn ($query) => $query->where('status', true),
                ])
                ->orderBy('id')
                ->get()
        );
    }
}
