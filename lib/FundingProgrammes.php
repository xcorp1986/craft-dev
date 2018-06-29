<?php

namespace biglotteryfund\utils;

use biglotteryfund\utils\EntryHelpers;
use biglotteryfund\utils\Images;
use craft\elements\Entry;
use League\Fractal\TransformerAbstract;

class FundingProgrammeTransformer extends TransformerAbstract
{
    public function __construct($locale)
    {
        $this->locale = $locale;
    }

    public function transform(Entry $entry)
    {
        list('entry' => $entry, 'status' => $status) = EntryHelpers::getDraftOrVersionOfEntry($entry);

        $data = [
            'id' => $entry->id,
            'availableLanguages' => EntryHelpers::getAvailableLanguages($entry->id, $this->locale),
            'status' => $status,
            'dateUpdated' => $entry->dateUpdated,
            'title' => $entry->title,
            'url' => $entry->url,
            'path' => $entry->uri,
            'hero' => Images::extractHeroImage($entry->heroImage),
            'heroCredit' => $entry->heroImageCredit ?? null,
            'summary' => getFundingProgramMatrix($entry, $this->locale),
            'intro' => $entry->programmeIntro,
            'contentSections' => getFundingProgrammeRegionsMatrix($entry, $this->locale),
        ];

        if ($entry->relatedCaseStudies) {
            $data['caseStudies'] = EntryHelpers::extractCaseStudySummaries($entry->relatedCaseStudies->all());
        }

        return $data;
    }
}
