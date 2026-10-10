<?php

namespace App\Support;

/**
 * Stock photos used in the UI (public/images). Vecteezy's free licence requires
 * crediting the photographer, so every place that shows a photo also shows this credit.
 */
class Photos
{
    public const CREDITS = [
        'shop' => ['text' => 'Photo: Yelena Akulova / Vecteezy', 'url' => 'https://www.vecteezy.com/photo/19771700-blurring-of-nuts-screws-and-spare-parts-for-repairs-and-repairs-mechanics-in-the-shop-window-out-of-focus'],
        'hero' => ['text' => 'Photo: Vitalii Borkovskyi / Vecteezy', 'url' => 'https://www.vecteezy.com/photo/72945840-screws-with-plastic-nozzles-and-tool-on-brown-wooden-vintage-background'],
        'cement' => ['text' => 'Photo: papan saenkutrueang / Vecteezy', 'url' => 'https://www.vecteezy.com/photo/8423322-hand-of-worker-plastering-cement-on-wall'],
    ];

    public static function credit(string $key): array
    {
        return self::CREDITS[$key];
    }
}
