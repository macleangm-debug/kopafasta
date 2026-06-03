<?php

return [

    'angles' => [
        'front' => [
            'label'       => 'Face front',
            'step_title'  => 'Face front → Capture photo',
            'instruction' => 'Look directly at the camera, then tap Capture.',
            'hint'        => 'Ensure your whole face is visible and well lit.',
            'icon'        => 'front',
            'require_face' => true,
            'allow_gallery' => false,
        ],
        'left' => [
            'label'       => 'Face left',
            'step_title'  => 'Face left → Capture photo',
            'instruction' => 'Turn your head slightly left, then tap Capture.',
            'hint'        => 'Show your left cheek and ear if possible.',
            'icon'        => 'left',
            'require_face' => true,
            'allow_gallery' => false,
        ],
        'right' => [
            'label'       => 'Face right',
            'step_title'  => 'Face right → Capture photo',
            'instruction' => 'Turn your head slightly right, then tap Capture.',
            'hint'        => 'Show your right cheek and ear if possible.',
            'icon'        => 'right',
            'require_face' => true,
            'allow_gallery' => false,
        ],
        'holding_nida' => [
            'label'       => 'Hold national ID',
            'step_title'  => 'Hold national ID → Capture photo',
            'instruction' => 'Hold your NIDA next to your face, then tap Capture.',
            'hint'        => 'Both your face and the ID must be clearly visible.',
            'icon'        => 'id',
            'require_face' => false,
            'allow_gallery' => true,
        ],
    ],

    'max_file_kb' => 5120,

    'allowed_mimes' => ['jpg', 'jpeg', 'png', 'webp'],

];
