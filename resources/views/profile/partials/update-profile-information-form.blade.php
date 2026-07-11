<section>
    <header>
        <h2 class="font-display text-lg font-black text-casa-text">Profile information</h2>
        <p class="mt-1 text-sm leading-6 text-casa-muted">Your Google email identifies your account and cannot be changed here.</p>
    </header>
    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf @method('patch')
        <div>
            <x-input-label for="name" value="Name" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>
        <div>
            <x-input-label for="email" value="Google email" />
            <x-text-input id="email" type="email" class="mt-1 block w-full opacity-70" :value="$user->email" disabled />
        </div>
        <div>
            <x-input-label for="phone" value="Phone (optional)" />
            <x-text-input id="phone" name="phone" type="tel" class="mt-1 block w-full" :value="old('phone', $user->phone)" autocomplete="tel" />
            <x-input-error class="mt-2" :messages="$errors->get('phone')" />
        </div>
        <x-primary-button>Save profile</x-primary-button>
    </form>
</section>
