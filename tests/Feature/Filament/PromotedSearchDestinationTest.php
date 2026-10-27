<?php

declare(strict_types=1);

use Capell\Admin\Actions\AssignPermissionsToRole;
use Capell\Admin\Actions\PersistMissingSettingsDefaultsAction;
use Capell\Search\Actions\ApplySearchResultEnhancementsAction;
use Capell\Search\Actions\ResolvePromotedSearchResultsAction;
use Capell\Search\Data\SearchResultData;
use Capell\Search\Filament\Pages\SearchSettingsPage;
use Capell\Search\Settings\SearchSettings;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(CreatesAdminUser::class);

beforeEach(function (): void {
    Permission::firstOrCreate(['name' => 'View:SettingsPage', 'guard_name' => 'web']);
    AssignPermissionsToRole::run(pages: [SearchSettingsPage::class]);
    $this->actingAsAdmin();
    $this->authenticatedUser()->givePermissionTo(['View:SettingsPage', 'View:SearchSettingsPage']);
    PersistMissingSettingsDefaultsAction::run(SearchSettings::class);
});

it('rejects a promotion during settings save and retains the title and query for correction', function (string $url): void {
    $before = resolve(SearchSettings::class)->promoted_results;
    $page = Livewire::test(SearchSettingsPage::class)->fillForm([
        'promoted_results' => [[
            'queries' => ['Pricing'], 'title' => 'Plan comparison', 'url' => $url,
        ]],
    ])->call('save')->assertHasFormErrors();

    $promotions = data_get($page->get('data'), 'promoted_results');
    expect($promotions)->toBeArray();
    throw_unless(is_array($promotions), LogicException::class);
    /** @var array{title: string, queries: list<string>} $promotion */
    $promotion = array_values($promotions)[0];
    expect($promotion['title'])->toBe('Plan comparison')->and($promotion['queries'])->toBe(['Pricing'])
        ->and(resolve(SearchSettings::class)->promoted_results)->toBe($before);
})->with(['/admin/users', 'javascript:alert(1)']);

it('saves a valid promotion and serves it only for its exact normalised query', function (): void {
    Livewire::test(SearchSettingsPage::class)->fillForm([
        'promoted_results' => [[
            'queries' => ['  PRICING  '], 'title' => 'Plan comparison', 'url' => '/pricing?token=private&plan=team',
        ]],
    ])->call('save')->assertHasNoFormErrors();

    expect(ResolvePromotedSearchResultsAction::run('pricing'))->toHaveCount(1)
        ->and(ResolvePromotedSearchResultsAction::run('pricing details'))->toBeEmpty();
    /** @var Collection<int, SearchResultData> $items */
    $items = collect();
    $results = ApplySearchResultEnhancementsAction::run(new LengthAwarePaginator($items, 0, 10, 1), 'pricing');
    expect(collect($results->items())->pluck('url')->all())->toBe(['/pricing?plan=team']);
});
