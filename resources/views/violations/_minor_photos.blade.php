@if($violation->minor_photos)
<div class="card p-3 mt-3">
    <h3 class="h6">Pictures for minors</h3>
    <div class="d-flex flex-wrap gap-2">
        @foreach($violation->minor_photos as $index => $path)
            <a href="{{ route('violations.minor-photo', ['violation' => $violation, 'photo' => $index]) }}" target="_blank" rel="noopener"><img src="{{ route('violations.minor-photo', ['violation' => $violation, 'photo' => $index]) }}" alt="Attached picture {{ $index + 1 }}" style="width:120px;height:120px;object-fit:cover" class="rounded"></a>
        @endforeach
    </div>
</div>
@endif
