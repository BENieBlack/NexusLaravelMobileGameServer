<style>
    .page { max-width: 640px; margin: 0 auto; padding: 40px 20px; }
    form { display: grid; gap: 16px; background: white; padding: 24px; border-radius: 10px; }
    label { display: grid; gap: 6px; color: #333; font-weight: 600; }
    input { padding: 10px; border: 1px solid #d6d9e0; border-radius: 6px; font: inherit; }
    button { padding: 10px 16px; border: 0; border-radius: 6px; background: #667eea; color: white; cursor: pointer; }
    .message { margin: 12px 0; color: #18794e; }
    .errors { margin: 12px 0; color: #b42318; }
</style>
@if (session('status'))
    <p class="message">{{ session('status') }}</p>
@endif
@if ($errors->any())
    <div class="errors"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
