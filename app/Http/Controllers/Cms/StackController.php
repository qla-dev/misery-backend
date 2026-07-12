<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Stack;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StackController extends Controller
{
    public function index() { return view('cms.stacks.index', ['stacks' => Stack::withCount('cards')->orderBy('name')->get()]); }
    public function store(Request $request) { $data=$request->validate(['name'=>'required|string|max:100']); Stack::firstOrCreate(['slug'=>Str::slug($data['name'])],['name'=>$data['name']]); return back()->with('success','Stack saved.'); }
    public function destroy(Stack $stack) { abort_if($stack->cards()->exists(), 422, 'Move cards out of this stack first.'); $stack->delete(); return back()->with('success','Stack deleted.'); }
}
