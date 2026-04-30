@props(['href', 'active' => false])

<a href="{{ $href }}"
   class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium transition-colors
          {{ $active
              ? 'bg-gray-800 text-white'
              : 'text-gray-400 hover:text-gray-100 hover:bg-gray-800/50' }}">
    <span class="{{ $active ? 'text-green-400' : 'text-gray-500' }}">
        {{ $icon }}
    </span>
    {{ $slot }}
</a>