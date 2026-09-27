<?php
if (!defined('ABSPATH') && !defined('SNOOKERCLUB_STORE')) {
    exit;
}

class Snookerclub_Theme {
    public const PRESETS = [
        'baize' => [
            'label' => 'Baize',
            'bg' => '#f3f7f0',
            'bgSide' => '#163524',
            'surface' => '#ffffff',
            'surface2' => '#eef6ee',
            'ink' => '#1d2a22',
            'muted' => '#5b6d61',
            'accent' => '#2ea85a',
            'accentSoft' => '#f0b429',
            'topbarInk' => '#f4fff6',
            'line' => 'rgba(22, 53, 36, 0.1)',
        ],
        'midnight' => [
            'label' => 'Nacht',
            'bg' => '#12161a',
            'bgSide' => '#0b0e11',
            'surface' => '#1b2128',
            'surface2' => '#232b34',
            'ink' => '#e8eef2',
            'muted' => '#9aa8b3',
            'accent' => '#3dd68c',
            'accentSoft' => '#f0c14b',
            'topbarInk' => '#e8eef2',
            'line' => 'rgba(232, 238, 242, 0.12)',
        ],
        'ivory' => [
            'label' => 'Ivoor',
            'bg' => '#f7f1e6',
            'bgSide' => '#3d2b1f',
            'surface' => '#fffaf2',
            'surface2' => '#f3e6d2',
            'ink' => '#2b2118',
            'muted' => '#7a6856',
            'accent' => '#c48a2a',
            'accentSoft' => '#d4a84b',
            'topbarInk' => '#fff6e8',
            'line' => 'rgba(61, 43, 31, 0.12)',
        ],
        'classic' => [
            'label' => 'Club',
            'bg' => '#f6efe8',
            'bgSide' => '#4a1c14',
            'surface' => '#ffffff',
            'surface2' => '#f3e4d8',
            'ink' => '#2a1814',
            'muted' => '#7a5348',
            'accent' => '#9b2c2c',
            'accentSoft' => '#d4a017',
            'topbarInk' => '#fff4ee',
            'line' => 'rgba(74, 28, 20, 0.12)',
        ],
        'ruby' => [
            'label' => 'Ruby',
            'bg' => '#f6f0f1',
            'bgSide' => '#1a0c10',
            'surface' => '#ffffff',
            'surface2' => '#f3e4e7',
            'ink' => '#241016',
            'muted' => '#6d4a54',
            'accent' => '#c81e3a',
            'accentSoft' => '#e8c547',
            'topbarInk' => '#fff4f6',
            'line' => 'rgba(36, 16, 22, 0.12)',
        ],
    ];

    public const FONTS = [
        'club' => ['sans' => '"Inter", "Segoe UI", system-ui, sans-serif', 'display' => '"Nunito", "Inter", sans-serif'],
        'system' => ['sans' => 'system-ui, "Segoe UI", sans-serif', 'display' => 'system-ui, "Segoe UI", sans-serif'],
        'serif' => ['sans' => 'Georgia, "Times New Roman", serif', 'display' => 'Georgia, "Times New Roman", serif'],
    ];

    public const RADII = ['snug' => 10, 'club' => 18, 'round' => 26];

    public static function init(): void {
        add_action('customize_register', [self::class, 'register_customizer']);
        add_action('customize_save_after', [self::class, 'sync_customizer']);
    }

    public static function hex($value, string $fallback): string {
        $raw = trim((string) $value);
        if ($raw !== '' && $raw[0] !== '#') {
            $raw = '#' . $raw;
        }
        if (preg_match('/^#[0-9a-fA-F]{3}$/', $raw)) {
            $raw = '#' . $raw[1] . $raw[1] . $raw[2] . $raw[2] . $raw[3] . $raw[3];
        }
        return preg_match('/^#[0-9a-fA-F]{6}$/', $raw) ? $raw : $fallback;
    }

    public static function wp_colors(): array {
        $out = [];
        if (function_exists('wp_get_global_styles')) {
            $styles = wp_get_global_styles(['color']);
            if (is_array($styles)) {
                foreach (['background' => 'bg', 'text' => 'ink'] as $from => $to) {
                    $val = $styles[$from] ?? '';
                    if (is_string($val) && preg_match('/#([0-9a-fA-F]{3,8})/', $val, $m)) {
                        $out[$to] = self::hex('#' . $m[1], '');
                    }
                }
            }
        }
        if (function_exists('wp_get_global_settings')) {
            $palette = wp_get_global_settings(['color', 'palette']);
            $colors = [];
            foreach (['theme', 'default', 'custom'] as $group) {
                if (!empty($palette[$group]) && is_array($palette[$group])) {
                    $colors = array_merge($colors, $palette[$group]);
                }
            }
            foreach ($colors as $swatch) {
                $slug = strtolower((string) ($swatch['slug'] ?? ''));
                $hex = self::hex((string) ($swatch['color'] ?? ''), '');
                if ($hex === '') {
                    continue;
                }
                if ($slug === 'primary' || $slug === 'accent' || $slug === 'contrast') {
                    $out['accent'] = $out['accent'] ?? $hex;
                }
                if ($slug === 'secondary' || $slug === 'tertiary') {
                    $out['accentSoft'] = $out['accentSoft'] ?? $hex;
                }
            }
            if (empty($out['accent']) && !empty($colors[0]['color'])) {
                $out['accent'] = self::hex((string) $colors[0]['color'], '');
            }
        }
        if (function_exists('get_background_color')) {
            $bg = (string) get_background_color();
            if ($bg !== '') {
                $out['bg'] = self::hex($bg, $out['bg'] ?? '#f3f7f0');
            }
        }
        if (function_exists('get_header_textcolor')) {
            $ink = (string) get_header_textcolor();
            if ($ink !== '' && strtolower($ink) !== 'blank') {
                $out['ink'] = self::hex($ink, $out['ink'] ?? '#1d2a22');
            }
        }
        return array_filter($out);
    }

    public static function normalize(array $input = []): array {
        $preset = strtolower((string) ($input['themePreset'] ?? ''));
        if ($preset === '') {
            $preset = 'custom';
        }
        if ($preset !== 'custom' && !isset(self::PRESETS[$preset])) {
            $preset = 'baize';
        }
        $base = self::PRESETS[$preset === 'custom' ? 'baize' : $preset];
        if ($preset === 'custom') {
            $colors = [
                'bg' => self::hex($input['bg'] ?? '', $base['bg']),
                'bgSide' => self::hex($input['bgSide'] ?? '', $base['bgSide']),
                'surface' => self::hex($input['surface'] ?? '', $base['surface']),
                'surface2' => self::hex($input['surface2'] ?? '', $base['surface2']),
                'ink' => self::hex($input['ink'] ?? '', $base['ink']),
                'muted' => self::hex($input['muted'] ?? '', $base['muted']),
                'accent' => self::hex($input['accent'] ?? '', $base['accent']),
                'accentSoft' => self::hex($input['accentSoft'] ?? '', $base['accentSoft']),
                'topbarInk' => self::hex($input['topbarInk'] ?? '', $base['topbarInk']),
                'line' => (string) ($input['line'] ?? $base['line']),
            ];
        } else {
            $colors = $base;
        }
        $inherit = !empty($input['inheritWp']) && $input['inheritWp'] !== '0';
        if ($inherit) {
            $colors = array_merge($colors, self::wp_colors());
        }
        $font = isset(self::FONTS[$input['font'] ?? '']) ? (string) $input['font'] : 'club';
        $radius = isset(self::RADII[$input['radius'] ?? '']) ? (string) $input['radius'] : 'club';
        $density = (($input['density'] ?? '') === 'compact') ? 'compact' : 'comfortable';
        return array_merge($colors, [
            'themePreset' => $preset,
            'font' => $font,
            'radius' => $radius,
            'density' => $density,
            'inheritWp' => $inherit,
            'sans' => $inherit ? 'inherit' : self::FONTS[$font]['sans'],
            'display' => $inherit ? 'inherit' : self::FONTS[$font]['display'],
            'radiusPx' => self::RADII[$radius],
            'label' => $preset === 'custom' ? 'Eigen' : $base['label'],
        ]);
    }

    public static function css_text(array $brand): string {
        $theme = self::normalize($brand);
        $vars = [
            '--bg' => $theme['bg'],
            '--bg-side' => $theme['bgSide'],
            '--surface' => $theme['surface'],
            '--surface-2' => $theme['surface2'],
            '--ink' => $theme['ink'],
            '--muted' => $theme['muted'],
            '--accent' => $theme['accent'],
            '--accent-2' => $theme['accentSoft'],
            '--gold' => $theme['accentSoft'],
            '--topbar-ink' => $theme['topbarInk'],
            '--line' => $theme['line'],
            '--radius' => $theme['radiusPx'] . 'px',
            '--sans' => $theme['sans'],
            '--display' => $theme['display'],
        ];
        $body = [];
        foreach ($vars as $name => $value) {
            $body[] = $name . ':' . $value;
        }
        return '.snooker-app,.snookerclub-embed,.snookerclub-board,.snooker-live-embed,:root{' . implode(';', $body) . '}';
    }

    public static function register_customizer(\WP_Customize_Manager $wp_customize): void {
        $brand = class_exists('Snookerclub_Plugin') ? Snookerclub_Plugin::store()->get_brand() : [];
        $theme = self::normalize($brand);
        $wp_customize->add_section('snookerclub_theme', [
            'title' => 'Snookerclub',
            'description' => 'Clubnaam, kleuren en lettertype. Geen extra link in het sitemenu; dit geldt voor /snooker/ en de shortcodes.',
            'priority' => 80,
        ]);
        $wp_customize->add_setting('snookerclub_club_name', [
            'default' => (string) ($brand['clubName'] ?? 'SC De Merodesnookers'),
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        $wp_customize->add_control('snookerclub_club_name', [
            'label' => 'Clubnaam',
            'section' => 'snookerclub_theme',
            'type' => 'text',
            'description' => 'Staat op ranking, dossier en afdruk. Standaard SC De Merodesnookers.',
        ]);
        $wp_customize->add_setting('snookerclub_tagline', [
            'default' => (string) ($brand['tagline'] ?? ''),
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        $wp_customize->add_control('snookerclub_tagline', [
            'label' => 'Tagline',
            'section' => 'snookerclub_theme',
            'type' => 'text',
        ]);
        $wp_customize->add_setting('snookerclub_venue', [
            'default' => (string) ($brand['venue'] ?? ''),
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        $wp_customize->add_control('snookerclub_venue', [
            'label' => 'Locatie',
            'section' => 'snookerclub_theme',
            'type' => 'text',
        ]);
        $wp_customize->add_setting('snookerclub_hours', [
            'default' => (string) ($brand['openingHours'] ?? ''),
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        $wp_customize->add_control('snookerclub_hours', [
            'label' => 'Clubavond / uren',
            'section' => 'snookerclub_theme',
            'type' => 'text',
        ]);
        $wp_customize->add_setting('snookerclub_preset', [
            'default' => $theme['themePreset'],
            'sanitize_callback' => [self::class, 'sanitize_preset'],
        ]);
        $choices = ['custom' => 'Eigen kleuren'];
        foreach (self::PRESETS as $id => $row) {
            $choices[$id] = $row['label'];
        }
        $wp_customize->add_control('snookerclub_preset', [
            'label' => 'Thema',
            'section' => 'snookerclub_theme',
            'type' => 'select',
            'choices' => $choices,
        ]);
        foreach (['accent' => 'Accent', 'accentSoft' => 'Tweede kleur', 'bg' => 'Achtergrond', 'bgSide' => 'Balk', 'ink' => 'Tekst'] as $key => $label) {
            $wp_customize->add_setting('snookerclub_' . $key, [
                'default' => $theme[$key],
                'sanitize_callback' => 'sanitize_hex_color',
            ]);
            $wp_customize->add_control(new \WP_Customize_Color_Control($wp_customize, 'snookerclub_' . $key, [
                'label' => $label,
                'section' => 'snookerclub_theme',
            ]));
        }
        $wp_customize->add_setting('snookerclub_font', [
            'default' => $theme['font'],
            'sanitize_callback' => fn($v) => isset(self::FONTS[$v]) ? $v : 'club',
        ]);
        $wp_customize->add_control('snookerclub_font', [
            'label' => 'Lettertype',
            'section' => 'snookerclub_theme',
            'type' => 'select',
            'choices' => ['club' => 'Club (Nunito / Inter)', 'system' => 'Systeem', 'serif' => 'Schreef'],
        ]);
        $wp_customize->add_setting('snookerclub_density', [
            'default' => $theme['density'],
            'sanitize_callback' => fn($v) => $v === 'compact' ? 'compact' : 'comfortable',
        ]);
        $wp_customize->add_control('snookerclub_density', [
            'label' => 'Dichtheid',
            'section' => 'snookerclub_theme',
            'type' => 'select',
            'choices' => ['comfortable' => 'Ruim', 'compact' => 'Compact'],
        ]);
        $wp_customize->add_setting('snookerclub_inherit_wp', [
            'default' => $theme['inheritWp'] ? '1' : '',
            'sanitize_callback' => fn($v) => $v ? '1' : '',
        ]);
        $wp_customize->add_control('snookerclub_inherit_wp', [
            'label' => 'Volg kleuren van het WordPress-thema',
            'section' => 'snookerclub_theme',
            'type' => 'checkbox',
        ]);
    }

    public static function sanitize_preset($value): string {
        $value = strtolower((string) $value);
        if ($value === 'custom' || isset(self::PRESETS[$value])) {
            return $value;
        }
        return 'baize';
    }

    public static function sync_customizer(): void {
        if (!class_exists('Snookerclub_Plugin')) {
            return;
        }
        $preset = self::sanitize_preset(get_theme_mod('snookerclub_preset', 'baize'));
        $name = sanitize_text_field((string) get_theme_mod('snookerclub_club_name', ''));
        $brand = Snookerclub_Plugin::store()->save_brand([
            'clubName' => $name,
            'tagline' => sanitize_text_field((string) get_theme_mod('snookerclub_tagline', '')),
            'venue' => sanitize_text_field((string) get_theme_mod('snookerclub_venue', '')),
            'openingHours' => sanitize_text_field((string) get_theme_mod('snookerclub_hours', '')),
            'themePreset' => $preset,
            'accent' => get_theme_mod('snookerclub_accent', ''),
            'accentSoft' => get_theme_mod('snookerclub_accentSoft', ''),
            'bg' => get_theme_mod('snookerclub_bg', ''),
            'bgSide' => get_theme_mod('snookerclub_bgSide', ''),
            'ink' => get_theme_mod('snookerclub_ink', ''),
            'font' => get_theme_mod('snookerclub_font', 'club'),
            'density' => get_theme_mod('snookerclub_density', 'comfortable'),
            'inheritWp' => (bool) get_theme_mod('snookerclub_inherit_wp', ''),
        ]);
        Snookerclub_Plugin::sync_app_page_title((string) ($brand['clubName'] ?? $name));
    }
}
