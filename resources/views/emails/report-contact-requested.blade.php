<x-mail::message>
# Talk to the team request

{{ $companyName }} clicked **Talk to the team** from their readiness report.

Contact email: {{ $contactEmail ?: 'No email provided' }}

<x-mail::button :url="$reportUrl">
View report
</x-mail::button>

Report link: {{ $reportUrl }}
</x-mail::message>
