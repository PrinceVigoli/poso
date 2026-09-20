<?php

namespace App\Providers;

use App\Models\Citation;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\RateLimiter::for('citizen-lookup', fn ($request) => \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by($request->ip()));
        \Illuminate\Pagination\Paginator::useBootstrapFive();
        // Share the government seal image data with the main layout.
        //
        // Note: this used to be handled with `@include('partials.seal')` at
        // the top of layouts/app.blade.php, but Blade's @include renders the
        // partial in its own isolated scope — a variable assigned inside an
        // included view (via @php) never propagates back out to the parent
        // template. That's why $__posoSeal was undefined by the time the
        // layout tried to use it. A view composer shares the value with the
        // layout *before* it renders, which is the correct way to do this.
        View::composer(['layouts.app', 'layouts.enforcer', 'layouts.public', 'landing'], function ($view) {
            $view->with('__posoSeal', View::make('partials.seal')->render());
        });

        // Settlement work queue behind the staff topbar bell.
        //
        // This lives here rather than in an @php block inside the layout so the
        // queries are visible to tests and profiling instead of hiding in a
        // template that every admin page renders. Only admins verify Treasury
        // settlements, so nobody else pays for them.
        View::composer('layouts.app', fn ($view) => $view->with('__posoAlerts', $this->staffAlerts()));
    }

    /**
     * Counts every settlement awaiting an admin, but loads details for only the
     * first few. The badge has to be exact; the panel only shows a preview and
     * links to the full filtered list for the rest.
     */
    private function staffAlerts(): array
    {
        $empty = ['count' => 0, 'groups' => []];
        if (! auth()->user()?->isAdmin()) {
            return $empty;
        }

        $today = today()->toDateString();
        // One aggregate pass, so an extra queue does not cost an extra query.
        $totals = Citation::payable()->unverified()
            ->selectRaw('SUM(CASE WHEN fine_amount IS NULL THEN 1 ELSE 0 END) AS blocked_total')
            ->selectRaw('SUM(CASE WHEN fine_amount IS NOT NULL AND due_date < ? THEN 1 ELSE 0 END) AS overdue_total', [$today])
            ->first();
        $blocked = (int) ($totals->blocked_total ?? 0);
        $overdue = (int) ($totals->overdue_total ?? 0);
        if ($blocked + $overdue === 0) {
            return $empty;
        }

        $rows = Citation::needsAttention()->with(['violation.violationType'])
            ->orderByRaw('CASE WHEN fine_amount IS NULL THEN 0 ELSE 1 END')
            ->orderBy('due_date')->orderBy('id')->limit(8)->get()
            ->filter(fn (Citation $row) => $row->violation !== null)
            ->map(fn (Citation $row) => [
                'type' => $row->fine_amount === null ? 'blocked' : 'overdue',
                'violation' => $row->violation,
                'name' => $row->violation->personDetail('full_name') ?: 'Name unavailable',
                'offense' => $row->violation->violationType?->offense_name ?? 'Offense unavailable',
                'detail' => $row->fine_amount === null
                    ? 'Fine not configured — verification is blocked'
                    : $row->due_date->diffInDays(today()).' days past due · '.$row->fine_label,
            ]);

        return ['count' => $blocked + $overdue, 'groups' => array_values(array_filter([
            $this->alertGroup('blocked', 'Needs fine configured', 'bi-sliders', $blocked, 'needs_review', $rows),
            $this->alertGroup('overdue', 'Overdue verification', 'bi-exclamation-triangle', $overdue, 'overdue', $rows),
        ]))];
    }

    private function alertGroup(string $type, string $label, string $icon, int $total, string $filter, $rows): ?array
    {
        if ($total === 0) {
            return null;
        }
        $items = $rows->where('type', $type)->take(4)->values();

        return ['key' => $type, 'label' => $label, 'icon' => $icon, 'total' => $total, 'items' => $items,
            'more' => max(0, $total - $items->count()),
            'url' => route('violations.index', ['payment_status' => $filter])];
    }
}
