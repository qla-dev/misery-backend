@extends('layouts.cms')
@section('title','Questions')
@section('content')
<div class="toolbar">
    <div><h1>Questions</h1><p class="hint">Only active questions (status 1) are available to Bubanj.</p></div>
    <div class="actions"><a class="btn secondary" href="{{ route('cms.questions.generate-form') }}">Generate 10 with AI</a><a class="btn" href="{{ route('cms.questions.create') }}">+ New question</a></div>
</div>
<form class="panel form-grid" method="get" style="margin-bottom:18px">
    <div class="field"><label for="q">Search</label><input id="q" name="q" value="{{ request('q') }}" placeholder="Question or answer"></div>
    <div class="field"><label for="category">Category</label><select id="category" name="category"><option value="">All categories</option>@foreach($categories as $value => $label)<option value="{{ $value }}" @selected(request('category') === $value)>{{ $label }}</option>@endforeach</select></div>
    <div class="field"><label for="difficulty">Range</label><select id="difficulty" name="difficulty"><option value="">All ranges</option>@foreach($difficulties as $value => $label)<option value="{{ $value }}" @selected((string) request('difficulty') === (string) $value)>{{ $value }} — {{ $label }}</option>@endforeach</select></div>
    <div class="field"><label for="status">Status</label><select id="status" name="status"><option value="">All statuses</option><option value="1" @selected(request('status') === '1')>Active</option><option value="0" @selected(request('status') === '0')>Draft</option></select></div>
    <div class="actions"><button type="submit">Filter</button><a class="btn secondary" href="{{ route('cms.questions.index') }}">Clear</a></div>
</form>
<div class="panel table-wrap">
<table><thead><tr><th>Question</th><th>Answer</th><th>Category</th><th>Range</th><th>Status</th><th>Source</th><th></th></tr></thead><tbody>
@forelse($questions as $question)
<tr>
    <td style="min-width:280px">{{ $question->question }}</td><td>{{ $question->answer }}</td>
    <td>{{ $categories[$question->category] ?? $question->category }}</td><td>{{ $question->difficulty }} — {{ $difficulties[$question->difficulty] ?? '' }}</td>
    <td><span class="badge" style="background:{{ $question->status ? '#14532d' : '#3f3f46' }};color:{{ $question->status ? '#86efac' : '#d4d4d8' }}">{{ $question->status ? 'ACTIVE' : 'DRAFT' }}</span></td>
    <td>{{ $question->generated_by_ai ? 'AI' : 'Manual' }}</td>
    <td><div class="actions"><a class="btn secondary" href="{{ route('cms.questions.edit', $question) }}">Edit</a><form method="post" action="{{ route('cms.questions.destroy', $question) }}" onsubmit="return confirm('Delete this question?')">@csrf @method('DELETE')<button class="danger">Delete</button></form></div></td>
</tr>
@empty<tr><td colspan="7" class="hint">No questions found.</td></tr>@endforelse
</tbody></table>
</div>
{{ $questions->links('cms.pagination') }}
@endsection
