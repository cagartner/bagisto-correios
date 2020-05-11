<?php

return [
    'cagartner_correios' => [
        'code' => 'correios',
        'title' => 'Correios',
        'description' => 'Correios',
        'active' => true,
        'class' => \Cagartner\Correios\Carriers\Correios::class,
        'methods' => 'sedex,pac',
        'tax_handling' => 0,
        'extra_time' => 1,
        'method_template' => 'Entrega em até :dia dia úteis(s)',
        'package_type' => 'caixa',
        'package_length' => 16,
        'package_height' => 11,
        'package_width' => 11,
        'default_method' => 'pac',
        'default_price' => 20,
        'default_estimate' => 10,
    ],
];