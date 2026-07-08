@props(['padding' => 'p-6'])

<div {{ $attributes->merge(['class' => 'casa-card '.$padding]) }}>
    {{ $slot }}
</div>
