<x-mail::message>
# Hello {{ $name }},

Your **{{ $appName }}** subscription will expire
@if ($daysLeft === 0)
**today**
@else
in **{{ $daysLeft }} {{ $daysLeft === 1 ? 'day' : 'days' }}**
@endif
(on {{ $endDate }}).

You keep full access until then. After that you will only see the latest few entries in each category.
To renew, please contact the admin:

@if ($contactPhone)
- Phone / WhatsApp: {{ $contactPhone }}
@endif
@if ($contactEmail)
- E-mail: {{ $contactEmail }}
@endif

<x-mail::button :url="$url">
View my subscription
</x-mail::button>

Thanks,<br>
{{ $appName }}
</x-mail::message>
