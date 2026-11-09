Hi {{ \Illuminate\Support\Str::of($user->name)->before(' ') }},

Your scharge code is: {{ $code }}

It works for {{ $minutes }} minutes. If you did not try to sign in to scharge, ignore this email.

scharge · {{ config('app.url') }}
