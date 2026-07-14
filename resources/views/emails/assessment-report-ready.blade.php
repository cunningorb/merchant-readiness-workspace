<x-mail::message>
# Your returns readiness report is ready

The value-proposition report for {{ $companyName }} is ready to review and share.

<x-mail::button :url="$reportUrl">
View report
</x-mail::button>

This report is heuristic and based on the answers and store signals provided during the assessment.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
