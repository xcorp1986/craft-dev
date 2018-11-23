<?php

namespace biglotteryfund\utils;

use biglotteryfund\utils\ContentHelpers;
use craft\elements\Entry;
use League\Fractal\TransformerAbstract;

class UpdatesTransformer extends TransformerAbstract
{
    public function __construct($locale)
    {
        $this->locale = $locale;
    }

    public function transform(Entry $entry)
    {
        list('entry' => $entry, 'status' => $status) = EntryHelpers::getDraftOrVersionOfEntry($entry);
        $commonFields = ContentHelpers::getCommonDetailFields($entry, $status, $this->locale);
        $primaryCategory = $entry->category ? $entry->category->inReverse()->one() : null;

        $extraFields = [
            'promoted' => $entry->articlePromoted,
            'trailPhoto' => Images::extractImageUrl($entry->trailPhoto), // @TODO Raw image. What size(s) should we crop this to?
            'category' => $primaryCategory ? ContentHelpers::categorySummary($primaryCategory, $this->locale) : null,
            'authors' => ContentHelpers::getTags($entry->authors->all(), $this->locale),
            'tags' => ContentHelpers::getTags($entry->tags->all(), $this->locale),
            'summary' => $entry->articleSummary,
            'content' => ContentHelpers::extractFlexibleContent($entry),
            'updateType' => [
                'name' => $entry->type->name,
                'slug' => str_replace('_', '-', $entry->type->handle),
            ],
        ];

        if ($entry->type->handle === 'press_releases') {
            $extraFields['contacts'] = $entry->pressReleaseContacts ?? null;
            $extraFields['notesToEditors'] = $entry->pressReleaseNotesToEditors ?? null;
            $extraFields['documentGroups'] = ContentHelpers::extractDocumentGroups($entry->documentGroups);
        }

        return array_merge($commonFields, $extraFields);
    }
}