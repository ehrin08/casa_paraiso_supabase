<div {{ $attributes->merge(['class' => 'casa-table-wrap']) }}>
    <table class="min-w-full divide-y divide-casa-border">
        {{ $slot }}
    </table>
</div>
