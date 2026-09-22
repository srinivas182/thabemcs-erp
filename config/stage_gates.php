<?php

declare(strict_types=1);

/*
| Default stage-gate checklists, created for every new project.
| Based on South African development practice (see docs/assumptions.md, PR1–PR4).
| Items marked 'required' => false are "where applicable" and do not block advancing.
| Per-company templates are planned; until then, edit this file.
*/

return [

    'plan' => [
        ['title' => 'Project brief approved by directors', 'required' => true],
        ['title' => 'Product and target market defined', 'required' => true],
        ['title' => 'Preliminary feasibility completed', 'required' => true],
        ['title' => 'Development objectives and key risks recorded', 'required' => true],
    ],

    'fund' => [
        ['title' => 'Feasibility and cash-flow model approved', 'required' => true],
        ['title' => 'Funding mix agreed (equity, investors, debt)', 'required' => true],
        ['title' => 'Investor and funder agreements signed', 'required' => true],
        ['title' => 'Separate project bank account opened', 'required' => true],
    ],

    'land' => [
        ['title' => 'Title deed and ownership verified (Deeds Office search)', 'required' => true],
        ['title' => 'Zoning and development rights confirmed', 'required' => true],
        ['title' => 'Municipal services capacity and access confirmed', 'required' => true],
        ['title' => 'Servitudes and site constraints checked', 'required' => true],
        ['title' => 'Geotechnical investigation completed', 'required' => true],
        ['title' => 'Environmental screening completed', 'required' => false],
        ['title' => 'Land acquisition agreement signed', 'required' => true],
    ],

    'approve' => [
        ['title' => 'Town planning approval (rezoning, subdivision, consent use)', 'required' => false],
        ['title' => 'Building plans approved by the municipality', 'required' => true],
        ['title' => 'Engineering services agreements approved', 'required' => true],
        ['title' => 'Environmental authorisation obtained', 'required' => false],
        ['title' => 'Fire department approval', 'required' => false],
        ['title' => 'NHBRC home enrolment (residential)', 'required' => false],
    ],

    'build' => [
        ['title' => 'Professional team appointed', 'required' => true],
        ['title' => 'Principal contractor appointed (JBCC, NEC or GCC contract signed)', 'required' => true],
        ['title' => 'Health and safety file approved; notification of construction work submitted where required', 'required' => true],
        ['title' => 'Site established', 'required' => true],
        ['title' => 'Practical completion certificate issued', 'required' => true],
        ['title' => 'Occupancy certificate issued by the municipality', 'required' => true],
    ],

    'sell_rent' => [
        ['title' => 'Pricing or rental schedule approved', 'required' => true],
        ['title' => 'Sales or leasing agents appointed', 'required' => false],
        ['title' => 'Conveyancing attorneys appointed', 'required' => false],
        ['title' => 'All units sold, transferred or let', 'required' => true],
    ],

    'close' => [
        ['title' => 'Final account agreed with the contractor', 'required' => true],
        ['title' => 'Defects liability period closed and retention released', 'required' => true],
        ['title' => 'Investor and funder obligations settled', 'required' => true],
        ['title' => 'Close-out report and lessons learned approved', 'required' => true],
    ],

];
