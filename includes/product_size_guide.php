<?php
return [
    'category_groups' => [
        't-shirts' => 'top',
        'shirts' => 'top',
        'polos' => 'top',
        'hoodies' => 'top',
        'sweaters' => 'top',
        'outerwear' => 'top',
        'pants' => 'bottom',
        'jeans' => 'bottom',
        'chinos' => 'bottom',
        'shorts' => 'bottom',
    ],
    'sizes' => [
        'men' => [
            'top' => ['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL', 'XS/L', 'S/L', 'M/L', 'L/L', 'XL/L', 'XXL/L', '3XL/L'],
            'bottom' => ['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL', '4XL', '5XL'],
        ],
        'women' => [
            'top' => ['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL', '4XL'],
            'bottom' => ['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL', '4XL', '5XL', '6XL', '7XL', '8XL', '9XL', '10XL', '11XL'],
        ],
        'kids' => [
            'top' => ['XS', 'S', 'M', 'L', 'XL'],
            'bottom' => ['XS', 'S', 'M', 'L', 'XL'],
        ],
    ],
    'bottom_ranges' => [
        'men' => [
            '25-27' => ['label' => 'XS-M', 'sizes' => ['XS', 'S', 'M']],
            '28-30' => ['label' => 'S-L', 'sizes' => ['S', 'M', 'L']],
            '31-33' => ['label' => 'M-XL', 'sizes' => ['M', 'L', 'XL']],
            '34-38' => ['label' => 'L-XXL', 'sizes' => ['L', 'XL', 'XXL']],
            '40-44' => ['label' => '3XL-5XL', 'sizes' => ['3XL', '4XL', '5XL']],
        ],
        'women' => [
            '32-36' => ['label' => 'XXS-S', 'sizes' => ['XXS', 'XS', 'S']],
            '38-42' => ['label' => 'M-XL', 'sizes' => ['M', 'L', 'XL']],
            '44-48' => ['label' => 'XXL-4XL', 'sizes' => ['XXL', '3XL', '4XL']],
            '50-54' => ['label' => '5XL-7XL', 'sizes' => ['5XL', '6XL', '7XL']],
            '56-60' => ['label' => '8XL-10XL', 'sizes' => ['8XL', '9XL', '10XL']],
            '62' => ['label' => '11XL', 'sizes' => ['11XL']],
        ],
    ],
];
