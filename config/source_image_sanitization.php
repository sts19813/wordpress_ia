<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Branding regions from external source sites
    |--------------------------------------------------------------------------
    |
    | These rules are intentionally opt-in. A region is altered only when the
    | image is attributable to the matching source domain, which protects manual
    | uploads and unbranded editorial images. Coordinates are percentages of the
    | original image and are processed locally with GD (no AI request or tokens).
    |
    */
    'domains' => [
        'quintanaroohoy.com' => [
            ['x' => 0.76, 'y' => 0.01, 'width' => 0.23, 'height' => 0.22, 'strength' => 6],
        ],
    ],
];
