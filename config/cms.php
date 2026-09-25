<?php

declare(strict_types=1);

/*
| The blocks a page can be built from. The editor in the back office builds its form from this list, and
| the website renders each block with a matching view, so adding a block type means adding an entry here
| and a view - no change to the editor.
|
| Field types: text, textarea, richtext, image, link, number, toggle, select, repeater.
*/

return [
    // Where website images live. Public, unlike the private documents disk.
    'disk' => env('CMS_DISK', 'public'),

    'blocks' => [
        'hero' => [
            'label' => 'Hero banner',
            'help' => 'The big first impression: a photograph, a headline and a button.',
            'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Headline', 'required' => true],
                'subheading' => ['type' => 'textarea', 'label' => 'Supporting line'],
                'image' => ['type' => 'image', 'label' => 'Background photograph'],
                'button_label' => ['type' => 'text', 'label' => 'Button text'],
                'button_link' => ['type' => 'link', 'label' => 'Button goes to'],
                'overlay' => ['type' => 'toggle', 'label' => 'Darken the photograph so the text reads clearly', 'default' => true],
            ],
        ],
        'intro' => [
            'label' => 'Introduction',
            'help' => 'A short piece of writing, usually under the hero.',
            'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading'],
                'body' => ['type' => 'richtext', 'label' => 'Text', 'required' => true],
            ],
        ],
        'services' => [
            'label' => 'Services',
            'help' => 'What the company does, as cards.',
            'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading'],
                'items' => ['type' => 'repeater', 'label' => 'Services', 'fields' => [
                    'title' => ['type' => 'text', 'label' => 'Service'],
                    'body' => ['type' => 'textarea', 'label' => 'Description'],
                    'icon' => ['type' => 'select', 'label' => 'Icon', 'options' => ['building', 'ruler', 'hard-hat', 'key', 'handshake', 'chart', 'leaf', 'shield']],
                ]],
            ],
        ],
        'developments' => [
            'label' => 'Developments',
            'help' => 'Pulls real projects from the system. Nothing to keep up to date by hand.',
            'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading'],
                'show' => ['type' => 'select', 'label' => 'Which ones', 'options' => ['selling', 'under_construction', 'complete', 'all']],
                'limit' => ['type' => 'number', 'label' => 'How many to show', 'default' => 6],
                'show_availability' => ['type' => 'toggle', 'label' => 'Show how many units are still available', 'default' => true],
            ],
        ],
        'statistics' => [
            'label' => 'Track record',
            'help' => 'Large numbers: units delivered, years in business, square metres.',
            'fields' => [
                'items' => ['type' => 'repeater', 'label' => 'Figures', 'fields' => [
                    'value' => ['type' => 'text', 'label' => 'Figure'],
                    'label' => ['type' => 'text', 'label' => 'What it is'],
                ]],
            ],
        ],
        'process' => [
            'label' => 'How we work',
            'help' => 'Numbered steps, for explaining a process to a buyer or a landowner.',
            'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading'],
                'steps' => ['type' => 'repeater', 'label' => 'Steps', 'fields' => [
                    'title' => ['type' => 'text', 'label' => 'Step'],
                    'body' => ['type' => 'textarea', 'label' => 'What happens'],
                ]],
            ],
        ],
        'team' => [
            'label' => 'The team',
            'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading'],
                'people' => ['type' => 'repeater', 'label' => 'People', 'fields' => [
                    'name' => ['type' => 'text', 'label' => 'Name'],
                    'role' => ['type' => 'text', 'label' => 'Role'],
                    'photo' => ['type' => 'image', 'label' => 'Photograph'],
                    'bio' => ['type' => 'textarea', 'label' => 'Short biography'],
                ]],
            ],
        ],
        'testimonials' => [
            'label' => 'What people say',
            'fields' => [
                'items' => ['type' => 'repeater', 'label' => 'Quotes', 'fields' => [
                    'quote' => ['type' => 'textarea', 'label' => 'What they said'],
                    'name' => ['type' => 'text', 'label' => 'Who said it'],
                    'role' => ['type' => 'text', 'label' => 'Their role or company'],
                ]],
            ],
        ],
        'gallery' => [
            'label' => 'Photographs',
            'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading'],
                'images' => ['type' => 'repeater', 'label' => 'Images', 'fields' => [
                    'image' => ['type' => 'image', 'label' => 'Photograph'],
                    'caption' => ['type' => 'text', 'label' => 'Caption'],
                ]],
            ],
        ],
        'faq' => [
            'label' => 'Questions and answers',
            'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading'],
                'items' => ['type' => 'repeater', 'label' => 'Questions', 'fields' => [
                    'question' => ['type' => 'text', 'label' => 'Question'],
                    'answer' => ['type' => 'textarea', 'label' => 'Answer'],
                ]],
            ],
        ],
        'text_image' => [
            'label' => 'Text beside a photograph',
            'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading'],
                'body' => ['type' => 'richtext', 'label' => 'Text'],
                'image' => ['type' => 'image', 'label' => 'Photograph'],
                'image_side' => ['type' => 'select', 'label' => 'Photograph on the', 'options' => ['left', 'right']],
            ],
        ],
        'form' => [
            'label' => 'A form',
            'help' => 'Drops in one of the forms you built, for enquiries or contact.',
            'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Heading'],
                'body' => ['type' => 'textarea', 'label' => 'Text above the form'],
                'form' => ['type' => 'select_form', 'label' => 'Which form'],
            ],
        ],
        'call_to_action' => [
            'label' => 'Call to action',
            'help' => 'A band across the page asking the visitor to do something.',
            'fields' => [
                'heading' => ['type' => 'text', 'label' => 'Headline', 'required' => true],
                'body' => ['type' => 'textarea', 'label' => 'Supporting line'],
                'button_label' => ['type' => 'text', 'label' => 'Button text'],
                'button_link' => ['type' => 'link', 'label' => 'Button goes to'],
            ],
        ],
    ],

    'templates' => [
        'home' => 'Home page',
        'page' => 'Standard page',
        'contact' => 'Contact page',
        'developments' => 'Developments listing',
    ],

    // Field types a form can use, and what they become when an enquiry is created.
    'form_fields' => ['text', 'email', 'phone', 'textarea', 'select', 'checkbox', 'date'],

    // Slugs the CMS may not take, because the application already uses them.
    'reserved_slugs' => ['login', 'logout', 'register', 'dashboard', 'projects', 'settings', 'reports', 'sales',
        'rentals', 'inbox', 'profile', 'health', 'site', 'api', 'lookup', 'quote', 'tenant', 'up', 'build'],

    'uploads' => [
        'max_bytes' => 10 * 1024 * 1024,
        'accepted' => ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml', 'application/pdf'],
    ],
];
