<?php

return [
    'groups' => [
        'A' => ['Mexico', 'Africa do Sul', 'Coreia do Sul', 'Republica Tcheca'],
        'B' => ['Canada', 'Bosnia e Herzegovina', 'Catar', 'Suica'],
        'C' => ['Brasil', 'Marrocos', 'Haiti', 'Escocia'],
        'D' => ['Estados Unidos', 'Paraguai', 'Australia', 'Turquia'],
        'E' => ['Alemanha', 'Curacao', 'Costa do Marfim', 'Equador'],
        'F' => ['Paises Baixos', 'Japao', 'Suecia', 'Tunisia'],
        'G' => ['Belgica', 'Egito', 'Ira', 'Nova Zelandia'],
        'H' => ['Espanha', 'Cabo Verde', 'Arabia Saudita', 'Uruguai'],
        'I' => ['Franca', 'Senegal', 'Iraque', 'Noruega'],
        'J' => ['Argentina', 'Argelia', 'Austria', 'Jordania'],
        'K' => ['Portugal', 'RD Congo', 'Uzbequistao', 'Colombia'],
        'L' => ['Inglaterra', 'Croacia', 'Gana', 'Panama'],
    ],

    'bracket' => [
        'round_of_32' => [
            'display_name' => '16 avos de final',
            'order' => 1,
            'matches' => [
                ['order' => 1, 'home_label' => 'Alemanha', 'away_label' => 'Paraguai'],
                ['order' => 2, 'home_label' => 'Franca', 'away_label' => 'Suecia'],
                ['order' => 3, 'home_label' => 'Africa do Sul', 'away_label' => 'Canada'],
                ['order' => 4, 'home_label' => 'Paises Baixos', 'away_label' => 'Marrocos'],
                ['order' => 5, 'home_label' => 'Portugal', 'away_label' => 'Croacia'],
                ['order' => 6, 'home_label' => 'Espanha', 'away_label' => 'Austria'],
                ['order' => 7, 'home_label' => 'Estados Unidos', 'away_label' => 'Bosnia e Herzegovina'],
                ['order' => 8, 'home_label' => 'Belgica', 'away_label' => 'Senegal'],
                ['order' => 9, 'home_label' => 'Brasil', 'away_label' => 'Japao'],
                ['order' => 10, 'home_label' => 'Costa do Marfim', 'away_label' => 'Noruega'],
                ['order' => 11, 'home_label' => 'Mexico', 'away_label' => 'Equador'],
                ['order' => 12, 'home_label' => 'Inglaterra', 'away_label' => 'RD Congo'],
                ['order' => 13, 'home_label' => 'Argentina', 'away_label' => 'Cabo Verde'],
                ['order' => 14, 'home_label' => 'Australia', 'away_label' => 'Egito'],
                ['order' => 15, 'home_label' => 'Suica', 'away_label' => 'Argelia'],
                ['order' => 16, 'home_label' => 'Colombia', 'away_label' => 'Gana'],
            ],
        ],
        'round_of_16' => [
            'display_name' => 'Oitavas de final',
            'order' => 2,
            'matches' => [
                ['order' => 1, 'home_label' => 'Vencedor F32-01', 'away_label' => 'Vencedor F32-02', 'source_matches' => [1, 2]],
                ['order' => 2, 'home_label' => 'Vencedor F32-03', 'away_label' => 'Vencedor F32-04', 'source_matches' => [3, 4]],
                ['order' => 3, 'home_label' => 'Vencedor F32-05', 'away_label' => 'Vencedor F32-06', 'source_matches' => [5, 6]],
                ['order' => 4, 'home_label' => 'Vencedor F32-07', 'away_label' => 'Vencedor F32-08', 'source_matches' => [7, 8]],
                ['order' => 5, 'home_label' => 'Vencedor F32-09', 'away_label' => 'Vencedor F32-10', 'source_matches' => [9, 10]],
                ['order' => 6, 'home_label' => 'Vencedor F32-11', 'away_label' => 'Vencedor F32-12', 'source_matches' => [11, 12]],
                ['order' => 7, 'home_label' => 'Vencedor F32-13', 'away_label' => 'Vencedor F32-14', 'source_matches' => [13, 14]],
                ['order' => 8, 'home_label' => 'Vencedor F32-15', 'away_label' => 'Vencedor F32-16', 'source_matches' => [15, 16]],
            ],
        ],
        'quarterfinal' => [
            'display_name' => 'Quartas de final',
            'order' => 3,
            'matches' => [
                ['order' => 1, 'home_label' => 'Vencedor OIT-01', 'away_label' => 'Vencedor OIT-02', 'source_matches' => [1, 2]],
                ['order' => 2, 'home_label' => 'Vencedor OIT-03', 'away_label' => 'Vencedor OIT-04', 'source_matches' => [3, 4]],
                ['order' => 3, 'home_label' => 'Vencedor OIT-05', 'away_label' => 'Vencedor OIT-06', 'source_matches' => [5, 6]],
                ['order' => 4, 'home_label' => 'Vencedor OIT-07', 'away_label' => 'Vencedor OIT-08', 'source_matches' => [7, 8]],
            ],
        ],
        'semifinal' => [
            'display_name' => 'Semifinal',
            'order' => 4,
            'matches' => [
                ['order' => 1, 'home_label' => 'Vencedor QF-01', 'away_label' => 'Vencedor QF-02', 'source_matches' => [1, 2]],
                ['order' => 2, 'home_label' => 'Vencedor QF-03', 'away_label' => 'Vencedor QF-04', 'source_matches' => [3, 4]],
            ],
        ],
        'third_place' => [
            'display_name' => 'Terceiro lugar',
            'order' => 5,
            'matches' => [
                ['order' => 1, 'home_label' => 'Perdedor SF-01', 'away_label' => 'Perdedor SF-02', 'source_matches' => [1, 2]],
            ],
        ],
        'final' => [
            'display_name' => 'Final',
            'order' => 6,
            'matches' => [
                ['order' => 1, 'home_label' => 'Vencedor SF-01', 'away_label' => 'Vencedor SF-02', 'source_matches' => [1, 2]],
            ],
        ],
    ],
];
