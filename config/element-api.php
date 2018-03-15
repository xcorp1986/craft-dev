<?php

use biglotteryfund\utils\EntryHelpers;
use biglotteryfund\utils\Images;
use craft\elements\Entry;

function normaliseCacheHeaders()
{
    $headers = \Craft::$app->response->headers;

    $headers->set('access-control-allow-origin', '*');
    $headers->set('cache-control', 'public, max-age=0');
    header_remove('Expires');
    header_remove('Pragma');
}

function getBasicEntryData(Entry $entry)
{
    $basicData = [
        'id' => $entry->id,
        'path' => $entry->uri,
        'url' => $entry->url,
        'title' => $entry->title,
        'dateUpdated' => $entry->dateUpdated,
    ];

    if ($entry->themeColour) {
        $basicData['themeColour'] = $entry->themeColour->value;
    }

    if ($entry->trailText) {
        $basicData['trailText'] = $entry->trailText;
    }

    if ($entry->trailPhoto) {
        $photos = [];
        foreach ($entry->trailPhoto->all() as $photo) {
            $photos[] = $photo->url;
        }
        if ($photos) {
            $basicData['photo'] = $photos[0];
        }
    }

    return $basicData;
}

function getRelatedEntries($entry, $relationType)
{
    $relatedEntries = [];
    $relatedSearch = [];

    if ($relationType == 'children') {
        $relatedSearch = $entry->getChildren()->all();
    } else if ($relationType == 'siblings') {
        // get parent first to allow including self as a sibling
        $parent = $entry->getParent();
        if ($parent) {
            $relatedSearch = $parent->getDescendants(1)->all();
        }
    }

    foreach ($relatedSearch as $relatedItem) {
        $relatedData = getBasicEntryData($relatedItem);
        $relatedData['isCurrent'] = $entry->uri == $relatedData['path'];
        $relatedEntries[] = $relatedData;
    }

    return $relatedEntries;
}

function parseSegmentMatrix($entry, $locale)
{
    $segments = [];
    if ($entry->contentSegment) {
        foreach ($entry->contentSegment->all() as $block) {
            $segment = [];
            $segment['title'] = $block->segmentTitle;
            $segment['content'] = $block->segmentContent;

            $segmentImage = $block->segmentImage->one();
            if ($segmentImage) {
                $segment['photo'] = $segmentImage->url;
            }

            array_push($segments, $segment);
        }
    }
    return $segments;
}

function getFundingProgramMatrix($entry, $locale)
{
    $bodyBlocks = [];
    $useNewContent = (bool) $entry->useNewContent;
    if ($entry->fundingProgramme) {
        foreach ($entry->fundingProgramme->all() as $block) {
            switch ($block->type->handle) {
                case 'fundingProgrammeBlock':
                    $fundingData = [];
                    $fundingData['title'] = $block->programmeTitle;

                    /**
                     * If useNewContent switch is enabled set linkUrl to the
                     * cannonical uri rather than the custom linkUrl field.
                     */
                    $pathLinkUrl = $locale === 'cy' ? "/welsh/$entry->uri" : "/$entry->uri";
                    $fundingData['linkUrl'] = $useNewContent ? $pathLinkUrl : $block->linkUrl;

                    // Use custom thumbnail if one is set, otherwise default to hero image.
                    $heroImage = Images::extractImage($entry->heroImage);
                    $thumbnailSrc = Images::extractImage($block->photo) ?? $heroImage->imageMedium->one();

                    $fundingData['photo'] = Images::imgixUrl($thumbnailSrc->url, [
                        'w' => 100,
                        'h' => 100,
                        'crop' => 'faces'
                    ]);

                    $orgTypes = [];
                    foreach ($block->organisationType as $o) {
                        $orgTypes[] = EntryHelpers::translate($locale, $o->label);
                    }
                    if ($orgTypes) {
                        $fundingData['organisationTypes'] = $orgTypes;
                    }

                    if ($block->description) {
                        $fundingData['description'] = $block->description;
                    }

                    if ($block->area) {
                        $fundingData['area'] = [
                            'label' => EntryHelpers::translate($locale, $block->area->label),
                            'value' => $block->area->value,
                        ];
                    }

                    if ($block->minimumFundingSize && $block->maximumFundingSize) {
                        $fundingData['fundingSize'] = [
                            'minimum' => (int) $block->minimumFundingSize,
                            'maximum' => (int) $block->maximumFundingSize,
                        ];
                    }

                    if ($block->fundingSizeDescription) {
                        $fundingData['fundingSizeDescription'] = $block->fundingSizeDescription;
                    }

                    if ($block->totalAvailable) {
                        $fundingData['totalAvailable'] = $block->totalAvailable;
                    }

                    if ($block->applicationDeadline) {
                        $fundingData['applicationDeadline'] = $block->applicationDeadline;
                    }

                    $bodyBlocks = $fundingData;
                    break;
            }
        }
    }
    return $bodyBlocks;
}

function getFundingProgrammeRegionsMatrix($entry, $locale)
{
    $regions = [];
    if ($entry->programmeRegions) {
        foreach ($entry->programmeRegions->all() as $block) {
            switch ($block->type->handle) {
                case 'programmeRegion':
                    $region = [
                        'title' => $block->programmeRegionTitle,
                        'body' => $block->programmeRegionBody,
                    ];
                    array_push($regions, $region);
                    break;
            }
        }
    }
    return $regions;
}

/**********************************************************
 * API ENDPOINTS
 **********************************************************/

/**
 * API Endpoint: Get Routes
 * Get a list of all canonical URLs from the CMS
 */
function getRoutes()
{
    normaliseCacheHeaders();

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'elementsPerPage' => 1000,
        'criteria' => [
            'section' => ['about', 'fundingProgrammes', 'fundingGuidance', 'buildingBetterOpportunities'],
            'status' => ['live', 'pending', 'expired'],
            'orderBy' => 'uri',
        ],
        'transformer' => function (craft\elements\Entry $entry) {
            return [
                'id' => $entry->id,
                'title' => $entry->title,
                'path' => '/' . $entry->uri,
                'live' => $entry->status === 'live',
                'isFromCms' => true,
            ];
        },
    ];
}

/**
 * API Endpoint: Homepage
 */
function getHomepage($locale)
{
    normaliseCacheHeaders();

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => [
            'section' => 'homepage',
            'site' => $locale,
        ],
        'one' => true,
        'transformer' => function (Entry $entry) use ($locale) {
            $newsQuery = EntryHelpers::queryPromotedNews();

            $data = [
                'id' => $entry->id,
                'heroImages' => [
                    'default' => Images::extractHomepageHeroImage($entry->homepageHeroImages->first()),
                    'candidates' => Images::extractHomepageHeroImages($entry->homepageHeroImages->all()),
                ],
                'newsArticles' => EntryHelpers::extractNewsSummaries($newsQuery->all()),
            ];

            return $data;
        },
    ];
}

/**
 * API Endpoint: Get Promoted News
 * Get a list of all promoted news articles
 */
function getPromotedNews($locale)
{
    normaliseCacheHeaders();

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => [
            'section' => 'news',
            'articlePromoted' => true,
            'site' => $locale,
        ],
        'transformer' => function (Entry $entry) {
            return EntryHelpers::extractNewsSummary($entry);
        },
    ];
}

/**
 * API Endpoint: Get Funding Programmes
 * Get a list of all active funding programmes
 */
function getFundingProgrammes($locale)
{
    normaliseCacheHeaders();

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => [
            'section' => 'fundingProgrammes',
            'site' => $locale,
            'status' => 'live',
        ],
        'transformer' => function (Entry $entry) use ($locale) {
            return [
                'id' => $entry->id,
                'status' => $entry->status,
                'title' => $entry->title,
                'url' => $entry->url,
                'urlPath' => $entry->uri,
                'content' => getFundingProgramMatrix($entry, $locale),
            ];
        },
    ];
}

/**
 * API Endpoint: Get Funding Programme
 * Get full details of a single funding programme
 */
function getFundingProgramme($locale, $slug)
{
    normaliseCacheHeaders();

    $section = 'fundingProgrammes';

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => [
            'site' => $locale,
            'section' => $section,
            'slug' => $slug,
            /**
             * Include expired entries
             * Allows expiry date to be used to drop items of the listing,
             * but still maintain the details page for historical purposes
             */
            'status' => ['live', 'expired'],
        ],
        'one' => true,
        'transformer' => function (Entry $entry) use ($locale, $section, $slug) {
            list('entry' => $entry, 'status' => $status) = EntryHelpers::getDraftOrVersionOfEntry($entry);

            if ($entry->useNewContent === false) {
                throw new \yii\web\NotFoundHttpException('Programme not found');
            }

            $data = [
                'id' => $entry->id,
                'availableLanguages' => EntryHelpers::getAvailableLanguages($entry->id, $locale),
                'status' => $status,
                'dateUpdated' => $entry->dateUpdated,
                'title' => $entry->title,
                'url' => $entry->url,
                'path' => $entry->uri,
                'hero' => Images::extractHeroImage($entry->heroImage),
                'summary' => getFundingProgramMatrix($entry, $locale),
                'intro' => $entry->programmeIntro,
                'contentSections' => getFundingProgrammeRegionsMatrix($entry, $locale),
            ];

            if ($entry->relatedCaseStudies) {
                $data['caseStudies'] = EntryHelpers::extractCaseStudySummaries($entry->relatedCaseStudies->all());
            }

            return $data;
        },
    ];
}

function getListing($locale)
{
    normaliseCacheHeaders();

    $pagePath = \Craft::$app->request->getParam('path');

    $searchCriteria = [
        'site' => $locale,
    ];

    if ($pagePath) {
        $searchCriteria['uri'] = $pagePath;
    } else {
        $searchCriteria['level'] = 1;
    }

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => $searchCriteria,
        'transformer' => function (Entry $entry) use ($locale, $pagePath) {
            list('entry' => $entry, 'status' => $status) = EntryHelpers::getDraftOrVersionOfEntry($entry);

            $entryData = getBasicEntryData($entry);

            $entryData['availableLanguages'] = EntryHelpers::getAvailableLanguages($entry->id, $locale);

            $entryData['status'] = $status;

            $entryData['hero'] = Images::extractHeroImage($entry->heroImage);

            if ($entry->introductionText) {
                $entryData['introduction'] = $entry->introductionText;
            }

            // casting to string prevents empty fields
            if ((string) $entry->outroText) {
                $entryData['outro'] = $entry->outroText;
            }

            $segments = parseSegmentMatrix($entry, $locale);
            if ($segments) {
                $entryData['segments'] = $segments;
            }

            if ($entry->relatedContent) {
                $entryData['relatedContent'] = $entry->relatedContent;
            }

            $children = getRelatedEntries($entry, 'children');
            if (count($children) > 0) {
                $entryData['children'] = $children;
            }

            $siblings = getRelatedEntries($entry, 'siblings');
            if (count($siblings) > 0) {
                $entryData['siblings'] = $siblings;
            }

            if ($entry->relatedCaseStudies) {
                $entryData['caseStudies'] = EntryHelpers::extractCaseStudySummaries($entry->relatedCaseStudies->all());
            }

            return $entryData;
        },
    ];
}

/**
 * API Endpoint: Get case studies
 * Get a list of summaries for all case studies
 */
function getCaseStudies($locale)
{
    normaliseCacheHeaders();

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => [
            'section' => 'caseStudies',
            'site' => $locale,
            'status' => 'live',
        ],
        'transformer' => function (Entry $entry) {
            return EntryHelpers::extractCaseStudySummary($entry);
        },
    ];
}

function getProfiles($locale, $section)
{
    normaliseCacheHeaders();

    if (!in_array($section, ['seniorManagementTeam', 'boardMembers'])) {
        throw new Error('Invalid section');
    }

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => [
            'section' => $section,
            'site' => $locale,
        ],
        'transformer' => function (Entry $entry) {
            return [
                'id' => $entry->id,
                'slug' => $entry->slug,
                'title' => $entry->title,
                'role' => $entry->profileRole,
                'image' => Images::extractImageUrl($entry->profilePhoto),
                'bio' => $entry->profileBio,
            ];
        },
    ];
}

function getSurveys($locale)
{
    normaliseCacheHeaders();

    $searchCriteria = [
        'section' => 'surveys',
        'site' => $locale,
    ];

    // Fetch everything, including closed surveys, if ?all=true is set
    $showAll = \Craft::$app->request->getParam('all');
    if ($showAll) {
        $searchCriteria['status'] = null;
    }

    return [
        'serializer' => 'jsonApi',
        'elementType' => Entry::class,
        'criteria' => $searchCriteria,
        'transformer' => function (Entry $entry) use ($locale) {

            $choices = array_map(function ($choice) {
                return [
                    'id' => (int) $choice->id,
                    'title' => $choice->choiceTitle,
                    'allowMessage' => $choice->allowMessage,
                ];
            }, $entry->choices->all());

            return [
                'id' => $entry->id,
                'status' => $entry->status,
                'surveyPath' => $entry->path,
                'dateCreated' => $entry->dateCreated,
                'title' => $entry->title,
                'question' => $entry->question,
                'choices' => $choices,
                'global' => $entry->global,
            ];
        },
    ];
}

return [
    'endpoints' => [
        'api/v1/list-routes' => getRoutes,
        'api/v1/<locale:en|cy>/homepage' => getHomepage,
        'api/v1/<locale:en|cy>/promoted-news' => getPromotedNews,
        'api/v1/<locale:en|cy>/funding-programmes' => getFundingProgrammes,
        'api/v1/<locale:en|cy>/funding-programme/<slug>' => getFundingProgramme,
        'api/v1/<locale:en|cy>/listing' => getListing,
        'api/v1/<locale:en|cy>/case-studies' => getCaseStudies,
        'api/v1/<locale:en|cy>/profiles/<section>' => getProfiles,
        'api/v1/<locale:en|cy>/surveys' => getSurveys,
    ],
];
