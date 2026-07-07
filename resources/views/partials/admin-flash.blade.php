@if (session('status'))
    <div class="pos-alert-success mb-6">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="pos-alert-error mb-6">{{ $errors->first() }}</div>
@endif
