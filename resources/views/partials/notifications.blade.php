{{-- Settlement work queue. Opened from the topbar bell; see AppServiceProvider::staffAlerts(). --}}
<div class="notif-panel" id="notifPanel" role="region" aria-labelledby="notifPanelTitle" hidden>
    <div class="notif-head">
        <span id="notifPanelTitle">Notifications</span>
        @if($__posoAlerts['count'] > 0)
            <span class="notif-head-count">{{ $__posoAlerts['count'] }}</span>
        @endif
    </div>

    @forelse($__posoAlerts['groups'] as $group)
        <div class="notif-group">
            <p class="notif-group-head"><span>{{ $group['label'] }}</span><span>{{ $group['total'] }}</span></p>
            @foreach($group['items'] as $item)
                <a class="notif-item" href="{{ route('violations.show', $item['violation']) }}">
                    <i class="bi {{ $group['icon'] }} notif-item-icon notif-{{ $group['key'] }}" aria-hidden="true"></i>
                    <span class="notif-item-body">
                        <span class="notif-item-name">{{ $item['name'] }}</span>
                        <span class="notif-item-detail">{{ $item['detail'] }}</span>
                        <span class="notif-item-meta">Record #{{ $item['violation']->id }} · {{ $item['offense'] }}</span>
                    </span>
                </a>
            @endforeach
            <a class="notif-more" href="{{ $group['url'] }}">
                @if($group['more'] > 0) Show {{ $group['more'] }} more @else View all @endif
                <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </a>
        </div>
    @empty
        <p class="notif-empty"><i class="bi bi-check-circle" aria-hidden="true"></i> No settlements need your attention.</p>
    @endforelse
</div>
