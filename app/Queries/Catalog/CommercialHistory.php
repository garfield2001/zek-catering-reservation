<?php

namespace App\Queries\Catalog;

use App\Models\AuditEvent;
use App\Models\IndividualListing;
use App\Models\Product;
use App\Models\SelectionGroup;
use App\Services\Catalog\CommercialComparison;

class CommercialHistory
{
    /** Call only after the corresponding commercial history policy permits access. */
    public function forOwner(Product|IndividualListing $owner): array
    {
        $package = $owner instanceof Product;
        $versions = $owner->versions()->orderBy('version_number')->get();
        $groupIds = $package ? SelectionGroup::whereIn('package_version_id', $versions->modelKeys())->pluck('id')->all() : [];
        $events = AuditEvent::query()->where(function ($query) use ($package, $owner, $versions, $groupIds) {
            $query->where(fn ($q) => $q->where('subject_type', $package ? 'package_version' : 'listing_version')->whereIn('subject_id', $versions->modelKeys()))
                ->orWhere(fn ($q) => $q->where('subject_type', $package ? 'product' : 'individual_listing')->where('subject_id', $owner->id));
            if ($package) {
                $query->orWhere(fn ($q) => $q->where('subject_type', 'selection_group')->where(fn ($g) => $g->whereIn('subject_id', $groupIds)->orWhereIn('before_data->package_version_id', $versions->modelKeys())->orWhereIn('after_data->package_version_id', $versions->modelKeys())));
            }
        })->orderByDesc('id')->limit(201)->get(['id', 'actor_label', 'action', 'subject_type', 'subject_id', 'created_at']);
        $previous = null;
        $publications = [];
        $publishers = AuditEvent::where('subject_type', $package ? 'package_version' : 'listing_version')
            ->whereIn('subject_id', $versions->modelKeys())->where('action', $package ? 'package.version_published' : 'listing.published')
            ->get(['subject_id', 'actor_label'])->keyBy('subject_id');
        foreach ($versions->where('status', 'published') as $version) {
            $event = $publishers->get($version->id);
            $snapshot = $version->snapshot();
            $publications[] = ['version_id' => $version->id, 'version_number' => $version->version_number,
                'published_at' => $version->published_at, 'publisher' => $event->actor_label ?? 'Historical actor label unavailable (account '.$version->published_by.')',
                'compared_to' => $previous['number'] ?? null,
                'changes' => app(CommercialComparison::class)->between($previous['snapshot'] ?? null, $snapshot)];
            $previous = ['number' => $version->version_number, 'snapshot' => $snapshot];
        }

        return ['publications' => $publications, 'events' => $events->take(200)->values(), 'older_events_omitted' => $events->count() > 200];
    }
}
