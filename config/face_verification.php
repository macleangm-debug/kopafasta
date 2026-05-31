<?php

return [

    'angles' => [
        'front' => [
            'label'       => 'Front face',
            'instruction' => 'Look straight at the camera. Ensure your whole face is visible and well lit.',
            'icon'        => 'front',
        ],
        'left' => [
            'label'       => 'Left profile',
            'instruction' => 'Turn your head slightly to show your left side.',
            'icon'        => 'left',
        ],
        'right' => [
            'label'       => 'Right profile',
            'instruction' => 'Turn your head slightly to show your right side.',
            'icon'        => 'right',
        ],
        'holding_nida' => [
            'label'       => 'Selfie holding NIDA',
            'instruction' => 'Hold your NIDA ID next to your face. Both must be clearly visible.',
            'icon'        => 'id',
        ],
    ],

    'max_file_kb' => 5120,

    'allowed_mimes' => ['jpg', 'jpeg', 'png', 'webp'],

];
