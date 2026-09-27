<?php
if (!defined('ABSPATH')) {
    exit;
}

class Snookerclub_Templates {
    public static function init(): void {
        add_action('init', [self::class, 'register'], 20);
    }

    public static function register(): void {
        if (!function_exists('register_block_pattern')) {
            return;
        }
        register_block_pattern_category('snookerclub', [
            'label' => 'Snookerclub',
        ]);
        foreach (self::patterns() as $name => $pattern) {
            register_block_pattern('snookerclub/' . $name, $pattern);
        }
    }

    public static function block(string $view, string $skin = 'site', string $align = 'wide'): string {
        return '<!-- wp:snookerclub/board {"view":"' . $view . '","skin":"' . $skin . '","align":"' . $align . '"} /-->';
    }

    public static function patterns(): array {
        $site = 'site';
        return [
            'ranking' => [
                'title' => 'Live ranking',
                'description' => 'Podium en clubtabel: positie, gewonnen, frames, punten, handicap en break.',
                'categories' => ['snookerclub', 'widgets'],
                'keywords' => ['ranking', 'erelijst', 'stand', 'snooker'],
                'content' => self::block('ranking', $site),
            ],
            'results' => [
                'title' => 'Recente uitslagen',
                'description' => 'Laatste partijen met stand, datum en toernooi.',
                'categories' => ['snookerclub', 'widgets'],
                'keywords' => ['uitslagen', 'results', 'snooker'],
                'content' => self::block('results', $site),
            ],
            'live' => [
                'title' => 'Live overzicht',
                'description' => 'Hoogste break, laatste wedstrijd, top 3 en recente frames.',
                'categories' => ['snookerclub', 'widgets'],
                'keywords' => ['live', 'overzicht', 'snooker'],
                'content' => self::block('live', $site),
            ],
            'agenda' => [
                'title' => 'Clubagenda',
                'description' => 'Maandkalender en komende clubavonden.',
                'categories' => ['snookerclub', 'widgets'],
                'keywords' => ['agenda', 'clubavond', 'snooker'],
                'content' => self::block('agenda', $site),
            ],
            'kpis' => [
                'title' => 'Clubcijfers',
                'description' => 'Maanddoelen: wedstrijden, leden, avonden en breaks.',
                'categories' => ['snookerclub', 'widgets'],
                'content' => self::block('kpis', $site),
            ],
            'break' => [
                'title' => 'Hoogste break',
                'description' => 'De hoogste break van de club.',
                'categories' => ['snookerclub', 'widgets'],
                'content' => self::block('break', $site),
            ],
            'next' => [
                'title' => 'Volgende clubavond',
                'description' => 'De eerstvolgende avond of het volgende toernooi.',
                'categories' => ['snookerclub', 'widgets'],
                'content' => self::block('next', $site),
            ],
            'rapport' => [
                'title' => 'Ranglijst-rapport',
                'description' => 'Afdrukbare Excel-ranglijst: #, W, L, F+, F-, M%, F%, HB, Gem.',
                'categories' => ['snookerclub', 'widgets'],
                'keywords' => ['rapport', 'excel', 'ranglijst', 'snooker'],
                'content' => self::block('rapport', $site),
            ],
            'dossier' => [
                'title' => 'Spelersdossier',
                'description' => 'VERSUS, RESULT, W/L, BREAKS, ROUND, SEASON en career-kaarten.',
                'categories' => ['snookerclub', 'widgets'],
                'keywords' => ['dossier', 'excel', 'speler', 'snooker'],
                'content' => self::block('dossier', $site),
            ],
            'h2h' => [
                'title' => 'Head-to-head',
                'description' => 'Onderlinge partijen tussen twee spelers.',
                'categories' => ['snookerclub', 'widgets'],
                'keywords' => ['h2h', 'versus', 'snooker'],
                'content' => self::block('h2h', $site),
            ],
            'ingeven' => [
                'title' => 'Uitslag invoeren',
                'description' => 'Wizard om een partij in te voeren op deze site.',
                'categories' => ['snookerclub'],
                'content' => self::block('ingeven', 'club'),
            ],
            'home' => [
                'title' => 'Club homepage',
                'description' => 'Ranking naast live overzicht — als op een clubsite.',
                'categories' => ['snookerclub', 'featured'],
                'content' => '<!-- wp:columns {"align":"wide"} --><div class="wp-block-columns alignwide">'
                    . '<!-- wp:column --><div class="wp-block-column">' . self::block('ranking', $site, '') . '</div><!-- /wp:column -->'
                    . '<!-- wp:column --><div class="wp-block-column">' . self::block('live', $site, '') . self::block('next', $site, '') . '</div><!-- /wp:column -->'
                    . '</div><!-- /wp:columns -->',
            ],
            'results-page' => [
                'title' => 'Uitslagenpagina',
                'description' => 'Uitslagen, hoogste break en volgende avond.',
                'categories' => ['snookerclub'],
                'content' => self::block('results', $site) . self::block('break', $site) . self::block('next', $site),
            ],
        ];
    }
}
