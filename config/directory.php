<?php

return [
    // Add the approved public roster here. Entries are separate from login accounts.
    // Each member: ['name' => 'Full name', 'position' => 'Official position',
    //              'photo' => 'images/directory/photo.webp'] (photo is optional).
    'groups' => [
        ['title' => 'Municipal Mayor', 'members' => [
            [
                'name' => 'Engr. Januario S. Garde',
                'position' => 'Municipal Mayor, Luna, Apayao',
                'photo' => 'images/directory/januario-s-garde.png',
                'photo_zoom' => 1.5,
                'photo_origin' => 0,
            ],
        ]],
        ['title' => 'POSO Leadership', 'members' => []],
        ['title' => 'POSO Staff', 'members' => [
            ['name' => 'Ronald Allan Umayam', 'position' => 'POSO Staff', 'photo' => 'images/directory/ronald-allan-umayam.jpg', 'photo_zoom' => 1.9, 'photo_origin' => 42],
            ['name' => 'Ronald June Perez', 'position' => 'POSO Staff', 'photo' => 'images/directory/ronald-june-perez.jpg', 'photo_zoom' => 1.7, 'photo_origin' => 0, 'photo_origin_x' => 27, 'photo_position' => 0],
            ['name' => 'Arnel Claveria', 'position' => 'POSO Staff', 'photo' => 'images/directory/arnel-claveria.jpg', 'photo_zoom' => 2.1, 'photo_origin' => 45],
            ['name' => 'Andy Ramos', 'position' => 'POSO Staff', 'photo' => 'images/directory/andy-ramos.jpg', 'photo_zoom' => 2.1, 'photo_origin' => 42, 'photo_origin_x' => 37],
            ['name' => 'Jimmy Morales', 'position' => 'POSO Staff', 'photo' => 'images/directory/jimmy-morales.jpg', 'photo_zoom' => 1.5, 'photo_origin' => 0, 'photo_position' => 0],
            ['name' => 'Marcelo Malab', 'position' => 'POSO Staff', 'photo' => 'images/directory/marcelo-malab.jpg', 'photo_zoom' => 1.9, 'photo_origin' => 44],
            ['name' => 'Felicito Baccay', 'position' => 'POSO Staff'],
            ['name' => 'Arthur Tapo', 'position' => 'POSO Staff'],
        ]],
        ['title' => 'Enforcers', 'members' => []],
    ],
];
