@php
    $themes = config('ordinances.themes', []);
    $ordinances = collect(config('ordinances.ordinances', []));
@endphp
<section id="ordinances" class="landing-steps ordinances" aria-labelledby="ordinances-title">
    <div class="landing-section-heading">
        <div>
            <p class="landing-eyebrow">MUNICIPAL ORDINANCES</p>
            <h2 id="ordinances-title">Ordinances enforced in Luna</h2>
        </div>
    </div>
    <p class="landing-description">These are the ordinances the Public Order &amp; Safety Office enforces across the Municipality of Luna, Apayao, with what each one requires and the penalties it carries. This is a plain-language transcription for guidance — <strong>the signed ordinance on file with the Sangguniang Bayan is the official text</strong>.</p>

    @if($ordinances->isEmpty())
        <p class="ordinance-empty">Ordinances will be published here soon.</p>
    @else
        @foreach($ordinances->groupBy('theme') as $theme => $group)
            <div class="ordinance-group">
                <h3 class="ordinance-group-title">{{ $themes[$theme] ?? 'Other ordinances' }}</h3>

                @foreach($group as $ordinance)
                    <article class="ordinance-item">
                        <header class="ordinance-head">
                            <p class="ordinance-ref">
                                <span class="ordinance-number">Ordinance No. {{ $ordinance['number'] }}</span>
                                <span class="ordinance-year">series of {{ $ordinance['year'] }}</span>
                            </p>
                            <h4 class="ordinance-title">{{ $ordinance['title'] }}</h4>
                            <p class="ordinance-summary">{{ $ordinance['summary'] }}</p>
                        </header>

                        <div class="ordinance-body">
                            @if(!empty($ordinance['provisions']))
                                <div class="ordinance-block">
                                    <h5 class="ordinance-block-title">What it covers</h5>
                                    <ul class="ordinance-provisions">
                                        @foreach($ordinance['provisions'] as $provision)
                                            <li>{{ $provision }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @if(!empty($ordinance['penalties']))
                                <div class="ordinance-block">
                                    <h5 class="ordinance-block-title">Penalties</h5>
                                    <dl class="ordinance-penalties">
                                        @foreach($ordinance['penalties'] as $penalty)
                                            <dt>{{ $penalty['offense'] }}</dt>
                                            <dd>{{ $penalty['penalty'] }}</dd>
                                        @endforeach
                                    </dl>
                                </div>
                            @endif
                        </div>

                        @if(!empty($ordinance['note']))
                            <p class="ordinance-note">{{ $ordinance['note'] }}</p>
                        @endif
                    </article>
                @endforeach
            </div>
        @endforeach
    @endif
</section>
