<span @class([
    'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-semibold',
    'bg-red-600 text-white' => $tier == 1,
    'bg-gray-800 text-white dark:bg-gray-200 dark:text-gray-900' => $tier == 2,
    'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300' => $tier >= 3,
]) title="Priority tier {{ $tier }}">T{{ $tier }}</span>
