@props([
    'dialogId' => 'site-header-search-dialog',
    'triggerLabel' => __('capell-search::generic.search_label'),
    'triggerClass' => null,
    'showTriggerLabel' => false,
])

<div class="site-header-search relative flex items-center justify-end lg:ml-2">
    <x-capell-search::header.search-trigger
        :dialog-id="$dialogId"
        :label="$triggerLabel"
        :class="$triggerClass"
        :show-label="$showTriggerLabel"
    />
</div>

<x-capell-search::header.search-dialog :dialog-id="$dialogId" />
