<?php

return [

    'angles' => [
        'front' => [
            'label'       => 'Front face',
            'instruction' => 'Please look directly at the camera.',
            'hint'        => 'Ensure your whole face is visible and well lit.',
            'icon'        => 'front',
            'require_face' => true,
            'allow_gallery' => false,
        ],
        'left' => [
            'label'       => 'Left profile',
            'instruction' => 'Please turn your head slightly to the left.',
            'hint'        => 'Show your left cheek and ear if possible.',
            'icon'        => 'left',
            'require_face' => true,
            'allow_gallery' => false,
        ],
        'right' => [
            'label'       => 'Right profile',
            'instruction' => 'Please turn your head slightly to the right.',
            'hint'        => 'Show your right cheek and ear if possible.',
            'icon'        => 'right',
            'require_face' => true,
            'allow_gallery' => false,
        ],
        'holding_nida' => [
            'label'       => 'Selfie holding NIDA',
            'instruction' => 'Please hold your NIDA card next to your face.',
            'hint'        => 'Both your face and the ID must be clearly visible.',
            'icon'        => 'id',
            'require_face' => false,
            'allow_gallery' => true,
        ],
    ],

    'max_file_kb' => 5120,

    'allowed_mimes' => ['jpg', 'jpeg', 'png', 'webp'],

];
