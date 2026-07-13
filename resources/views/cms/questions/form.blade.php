@extends('layouts.cms')
@section('title', $question->exists ? 'Edit question' : 'New question')
@section('content')
<div class="toolbar"><div><h1>{{ $question->exists ? 'Edit question' : 'New question' }}</h1><p class="hint">Draft questions stay hidden from the app until activated.</p></div><a class="btn secondary" href="{{ route('cms.questions.index') }}">Back</a></div>
<form class="panel" method="post" action="{{ $question->exists ? route('cms.questions.update', $question) : route('cms.questions.store') }}">
    @csrf @if($question->exists) @method('PUT') @endif
    <div class="field"><label for="question">Question</label><textarea id="question" name="question" rows="4" required maxlength="1000">{{ old('question', $question->question) }}</textarea></div>
    <div class="field"><label for="answer">Answer</label><input id="answer" name="answer" value="{{ old('answer', $question->answer) }}" required maxlength="1000"></div>
    <div class="form-grid">
        <div class="field"><label for="category">Category</label><select id="category" name="category" required>@foreach($categories as $value => $label)<option value="{{ $value }}" @selected(old('category', $question->category) === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="field"><label for="difficulty">Range</label><select id="difficulty" name="difficulty" required>@foreach($difficulties as $value => $label)<option value="{{ $value }}" @selected((string) old('difficulty', $question->difficulty ?: 1) === (string) $value)>{{ $value }} — {{ $label }}</option>@endforeach</select></div>
    </div>
    <input type="hidden" name="status" value="0">
    <label style="align-items:center;display:flex;gap:10px;margin:4px 0 20px"><input type="checkbox" name="status" value="1" style="width:auto" @checked((bool) old('status', $question->status))> Active (status 1) — make available to Bubanj</label>
    <button type="submit">{{ $question->exists ? 'Save question' : 'Create question' }}</button>
</form>
@endsection
