<style>
    .page { max-width: 960px; margin: 0 auto; padding: 40px 20px; }
    .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .button, .edit { padding: 8px 14px; border-radius: 6px; text-decoration: none; }
    .button { background: #667eea; color: white; }
    .edit { color: #4f46e5; }
    table { width: 100%; border-collapse: collapse; background: white; }
    th, td { padding: 12px; border-bottom: 1px solid #e5e7eb; text-align: left; }
    .message { margin-bottom: 12px; color: #18794e; }
</style>
@if (session('status'))<p class="message">{{ session('status') }}</p>@endif
