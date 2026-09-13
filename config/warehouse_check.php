<?php

return [

    'otp_minutes' => 120,

    'barcode' => [
        'table' => env('BARCODE_TABLE', 'ItemBarcode'),
        'barcode_column' => env('BARCODE_COLUMN', 'Barcode'),
        'item_column' => env('BARCODE_ITEM_COLUMN', 'ItemCode'),
        'uom_column' => env('BARCODE_UOM_COLUMN', 'UOM'),
    ],

    'ratio' => [
        'table' => env('RATIO_TABLE', 'ItemRatio'),
        'item_column' => env('RATIO_ITEM_COLUMN', 'ItemCode'),
        'uom_column' => env('RATIO_UOM_COLUMN', 'UOM'),
        'value_column' => env('RATIO_VALUE_COLUMN', 'Ratio'),
    ],
];
