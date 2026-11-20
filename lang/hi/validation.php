<?php

return [
    'required' => ':attribute ज़रूरी है।',
    'email' => ':attribute मान्य ईमेल होना चाहिए।',
    'confirmed' => ':attribute की पुष्टि मेल नहीं खाती।',
    'string' => ':attribute अक्षर होने चाहिए।',
    'numeric' => ':attribute संख्या होनी चाहिए।',
    'integer' => ':attribute पूर्ण संख्या होनी चाहिए।',
    'date' => ':attribute मान्य तारीख होनी चाहिए।',
    'boolean' => ':attribute हाँ या नहीं होना चाहिए।',
    'unique' => 'यह :attribute पहले से है।',
    'exists' => 'चुना हुआ :attribute मान्य नहीं है।',
    'min' => [
        'string' => ':attribute कम से कम :min अक्षर का होना चाहिए।',
        'numeric' => ':attribute कम से कम :min होना चाहिए।',
    ],
    'max' => [
        'string' => ':attribute :max अक्षर से बड़ा नहीं होना चाहिए।',
    ],
    'password' => [
        'letters' => ':attribute में कम से कम एक अक्षर होना चाहिए।',
        'mixed' => ':attribute में बड़ा और छोटा अक्षर दोनों होने चाहिए।',
        'numbers' => ':attribute में कम से कम एक अंक होना चाहिए।',
        'symbols' => ':attribute में कम से कम एक चिह्न होना चाहिए।',
    ],
    'attributes' => [
        'email' => 'ईमेल',
        'password' => 'पासवर्ड',
        'name' => 'नाम',
        'code' => 'कोड',
    ],
];
