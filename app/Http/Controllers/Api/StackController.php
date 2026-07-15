<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StackResource;
use App\Models\Stack;

class StackController extends Controller
{
    public function index()
    {
        return StackResource::collection(Stack::query()->orderBy('id')->get());
    }
}
