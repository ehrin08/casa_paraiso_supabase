<div {{ $attributes->merge(['class' => 'casa-table-wrap']) }} tabindex="0" role="region">
    <table class="min-w-full divide-y divide-casa-border">
        {{ $slot }}
    </table>
</div>
