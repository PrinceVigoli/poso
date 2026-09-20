<section id="staff-directory" class="landing-steps staff-directory" aria-labelledby="directory-title">
    <div class="landing-section-heading">
        <div><p class="landing-eyebrow">THE PEOPLE WHO SERVE LUNA</p><h2 id="directory-title">Officials &amp; staff directory</h2></div>
    </div>
    <p class="directory-intro">Municipal leadership and the Public Order &amp; Safety Office team.</p>
    @foreach(config('directory.groups', []) as $group)
        <section class="directory-group" aria-labelledby="directory-group-{{ $loop->index }}">
            <h3 id="directory-group-{{ $loop->index }}">{{ $group['title'] }}</h3>
            @if(count($group['members']))
                <div class="directory-grid">
                    @foreach($group['members'] as $member)
                    <article class="directory-card">
                        @if(!empty($member['photo']))
                            <img class="directory-photo" src="{{ asset($member['photo']) }}" alt="{{ $member['name'] }}" width="72" height="72" loading="lazy">
                        @else
                            <span class="directory-avatar">@include('partials.landing-icon', ['icon' => 'person'])</span>
                        @endif
                        <div><h4>{{ $member['name'] }}</h4><p>{{ $member['position'] }}</p></div>
                    </article>
                    @endforeach
                </div>
            @else
                <div class="directory-pending"><span class="directory-avatar">@include('partials.landing-icon', ['icon' => 'person'])</span><p>Directory details will be published soon.</p></div>
            @endif
        </section>
    @endforeach
</section>
