@php
    $currentStep = request()->routeIs('enforcer.show', 'violations.show') ? 3 : (request()->routeIs('enforcer.review', 'enforcer.confirm') ? 2 : 1);
@endphp
<ol class="enforcer-steps" aria-label="Submission progress">
    @foreach(['Details', 'Review', 'Done'] as $step)
        <li class="{{ $loop->iteration <= $currentStep ? 'is-current' : '' }}" @if($loop->iteration === $currentStep) aria-current="step" @endif><span>{{ $loop->iteration }}</span>{{ $step }}</li>
    @endforeach
</ol>
