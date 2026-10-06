<x-mail::message>
# Hello {{ $name }},

@if ($action === 'activated')
Your **{{ $appName }}** subscription is now **active** from {{ $startDate }} until **{{ $endDate }}**.
You can see all the latest entries in every category.
@elseif ($action === 'extended')
Good news — your **{{ $appName }}** subscription has been **extended until {{ $endDate }}**.
@else
Your **{{ $appName }}** subscription has **expired**. You can still log in and see the latest few entries.
To renew, please contact the admin:
@if ($contactPhone)
- Phone / WhatsApp: {{ $contactPhone }}
@endif
@if ($contactEmail)
- E-mail: {{ $contactEmail }}
@endif
@endif

<x-mail::button :url="$url">
Open {{ $appName }}
</x-mail::button>

Thanks,<br>
{{ $appName }}
</x-mail::message>
