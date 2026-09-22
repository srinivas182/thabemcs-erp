<?php

declare(strict_types=1);

/*
| Default land due-diligence checklist (South Africa). See docs/assumptions.md LA1.
| A parcel cannot be marked as transferred while a required check is pending or has an unresolved issue.
*/

return [
    ['key' => 'title', 'title' => 'Title deed and ownership confirmed (Deeds Office search)', 'required' => true],
    ['key' => 'encumbrances', 'title' => 'Bonds, interdicts and title conditions reviewed', 'required' => true],
    ['key' => 'zoning', 'title' => 'Zoning and development rights confirmed with the municipality', 'required' => true],
    ['key' => 'servitudes', 'title' => 'Servitudes and restrictive conditions checked', 'required' => true],
    ['key' => 'services', 'title' => 'Bulk water, sewer, electricity and roads capacity confirmed', 'required' => true],
    ['key' => 'access', 'title' => 'Legal access to a public road confirmed', 'required' => true],
    ['key' => 'geotech', 'title' => 'Geotechnical investigation (soils, dolomite, slopes)', 'required' => true],
    ['key' => 'environmental', 'title' => 'Environmental screening (wetlands, protected species, NEMA triggers)', 'required' => true],
    ['key' => 'flood', 'title' => 'Flood lines determined', 'required' => false],
    ['key' => 'heritage', 'title' => 'Heritage check (structures older than 60 years, graves)', 'required' => false],
    ['key' => 'land_claims', 'title' => 'Land restitution claims checked', 'required' => true],
    ['key' => 'rates', 'title' => 'Rates clearance and municipal accounts in order', 'required' => true],
    ['key' => 'valuation', 'title' => 'Independent valuation supports the price', 'required' => false],
];
