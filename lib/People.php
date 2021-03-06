<?php

namespace biglotteryfund\utils;

use biglotteryfund\utils\ContentHelpers;
use craft\elements\Entry;
use League\Fractal\TransformerAbstract;

class PeopleTransformer extends TransformerAbstract
{
    public function __construct($locale)
    {
        $this->locale = $locale;
    }

    public function transform(Entry $entry)
    {
        list('entry' => $entry, 'status' => $status) = EntryHelpers::getDraftOrVersionOfEntry($entry);
        $common = ContentHelpers::getCommonFields($entry, $status, $this->locale);

        return array_merge($common, [
            'people' => array_map(function ($person) {
                $image = $person->personPhoto->one() ?? null;
                return [
                    'name' => $person->personName,
                    'role' => $person->personRole ?? null,
                    'image' => $image ? [
                        // Is the source image large or small?
                        // Used to determine what layout to use
                        'type' => $image->width > 500 ? 'large' : 'small',
                        'url' => Images::imgixUrl(
                            $image->url,
                            ['fit' => 'crop', 'crop' => 'entropy', 'max-w' => 1200]
                        ),
                    ] : null,
                    'bio' => $person->personBio,
                ];
            }, $entry->people->all() ?? []),
            'documentGroups' => ContentHelpers::extractDocumentGroups($entry->documentGroups),
        ]);
    }
}
