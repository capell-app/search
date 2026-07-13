<?php

declare(strict_types=1);

use Capell\Admin\Facades\CapellAdmin;
use Capell\Search\Filament\Settings\Contributors\SearchDashboardSettingsContributor;
use Capell\Search\Filament\Widgets\TopSearchesFilamentWidget;
use Capell\Search\Filament\Widgets\TrendingSearchesFilamentWidget;
use Capell\Search\Filament\Widgets\ZeroResultSearchesFilamentWidget;
use Capell\Search\Models\SearchLog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function (): void {
    config()->set('capell-search.logs.table_name', 'search_logs');

    Schema::dropIfExists('search_logs');
    Schema::create('search_logs', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('site_id')->nullable()->index();
        $table->foreignId('language_id')->nullable()->index();
        $table->string('query');
        $table->string('normalized_query')->index();
        $table->string('normalized_query_hash', 64)->nullable()->index();
        $table->unsignedInteger('results_count')->default(0);
        $table->string('clicked_result_url')->nullable();
        $table->string('clicked_result_hash', 64)->nullable()->index();
        $table->string('ip_hash', 64)->nullable();
        $table->string('user_agent_hash', 64)->nullable();
        $table->timestamp('searched_at')->index();
        $table->timestamps();
    });

    SearchLog::factory()->create([
        'query' => 'Laravel',
        'normalized_query' => 'laravel',
        'results_count' => 5,
        'searched_at' => now()->subDay(),
    ]);
    SearchLog::factory()->create([
        'query' => 'Missing page',
        'normalized_query' => 'missing page',
        'results_count' => 0,
        'searched_at' => now()->subDay(),
    ]);
});

afterEach(function (): void {
    Schema::dropIfExists('search_logs');
});

test('dashboard settings contributor exposes site search widget keys', function (): void {
    $keys = collect((new SearchDashboardSettingsContributor)->settingsKeys())->pluck('key')->all();

    expect($keys)->toBe([
        'search_overview',
        'top_searches',
        'trending_searches',
        'zero_result_searches',
    ]);
});

test('site search dashboard widgets render', function (string $widgetClass): void {
    Livewire::test($widgetClass)->assertOk();
})->with([
    TopSearchesFilamentWidget::class,
    TrendingSearchesFilamentWidget::class,
    ZeroResultSearchesFilamentWidget::class,
]);

test('site search contributes overview stats instead of an overview widget', function (): void {
    $stats = collect(CapellAdmin::getOverviewStats(false));

    expect($stats->pluck('key')->all())->toContain(
        'search_overview',
        'search_overview.unique_queries',
        'search_overview.zero_result_rate',
    );
});

test('site search overview stat cache stays scoped to the current request', function (): void {
    app()->instance('request', Request::create('/admin/search/first'));

    $firstRequestStat = collect(CapellAdmin::getOverviewStats(false))
        ->firstWhere('key', 'search_overview');

    SearchLog::factory()->create([
        'query' => 'Fresh query',
        'normalized_query' => 'fresh query',
        'results_count' => 3,
        'searched_at' => now(),
    ]);

    app()->instance('request', Request::create('/admin/search/second'));

    $secondRequestStat = collect(CapellAdmin::getOverviewStats(false))
        ->firstWhere('key', 'search_overview');

    expect($firstRequestStat?->value)->toBe('2')
        ->and($secondRequestStat?->value)->toBe('3');
});
