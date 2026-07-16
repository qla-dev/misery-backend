<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Stack;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StackController extends Controller
{
    public function index() { return view('cms.stacks.index', ['stacks' => Stack::withCount('cards')->orderBy('name')->get()]); }
    public function store(Request $request) { $data=$this->validated($request); $data['slug']=Str::slug($data['name']); Stack::updateOrCreate(['slug'=>$data['slug']],$data); return back()->with('success','Stack saved.'); }
    public function update(Request $request, Stack $stack) { $stack->update($this->validated($request)); return back()->with('success','Stack updated.'); }
    public function destroy(Stack $stack) { abort_if($stack->cards()->exists(), 422, 'Move cards out of this stack first.'); $stack->delete(); return back()->with('success','Stack deleted.'); }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon_key' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'description' => ['required', 'string', 'max:255'],
            'description_bs' => ['required', 'string', 'max:255'],
        ]);
    }
}
