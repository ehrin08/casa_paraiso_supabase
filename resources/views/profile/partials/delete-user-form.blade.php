<section class="space-y-5">
    <header>
        <h2 class="font-display text-lg font-black text-casa-text">Delete account</h2>
        <p class="mt-1 text-sm leading-6 text-casa-muted">Deletion is permanent. For your protection, Google will ask you to confirm your identity first.</p>
    </header>
    <a href="{{ route('profile.deletion.google') }}" class="inline-flex min-h-11 items-center rounded-xl bg-red-700 px-4 py-2 text-sm font-bold text-white hover:bg-red-800 focus:outline-none focus:ring-4 focus:ring-red-200">Confirm with Google</a>
    @if (session('deletion_confirmed'))
        <form method="post" action="{{ route('profile.destroy') }}">
            @csrf @method('delete')
            <button class="inline-flex min-h-11 items-center rounded-xl border border-red-700 px-4 py-2 text-sm font-bold text-red-800 hover:bg-red-50 focus:outline-none focus:ring-4 focus:ring-red-200">Permanently delete my account</button>
        </form>
    @endif
</section>
