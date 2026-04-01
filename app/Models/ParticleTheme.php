<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParticleTheme extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'config',
        'is_active',
        'is_preset',
        'preview_color',
    ];

    protected $casts = [
        'config' => 'array',
        'is_active' => 'boolean',
        'is_preset' => 'boolean',
    ];

    /**
     * Get the currently active theme
     */
    public static function active(): ?static
    {
        return static::where('is_active', true)->first();
    }

    /**
     * Activate this theme (deactivates others)
     */
    public function activate(): void
    {
        static::where('is_active', true)->update(['is_active' => false]);
        $this->update(['is_active' => true]);
    }

    /**
     * Scope for preset themes
     */
    public function scopePresets($query)
    {
        return $query->where('is_preset', true);
    }

    /**
     * Scope for custom themes
     */
    public function scopeCustom($query)
    {
        return $query->where('is_preset', false);
    }

    /**
     * Get default presets configuration
     */
    public static function getDefaultPresets(): array
    {
        return [
            [
                'name' => 'Hexagons',
                'slug' => 'hexagons',
                'preview_color' => '#d946ef',
                'config' => self::hexagonsConfig(),
            ],
            [
                'name' => 'Color Gradient',
                'slug' => 'color',
                'preview_color' => '#06b6d4',
                'config' => self::colorConfig(),
            ],
            [
                'name' => 'Network Links',
                'slug' => 'links',
                'preview_color' => '#a855f7',
                'config' => self::linksConfig(),
            ],
            [
                'name' => 'Starfield',
                'slug' => 'stars',
                'preview_color' => '#fbbf24',
                'config' => self::starsConfig(),
            ],
            [
                'name' => 'Snow',
                'slug' => 'snow',
                'preview_color' => '#e2e8f0',
                'config' => self::snowConfig(),
            ],
            [
                'name' => 'Spider Cursor',
                'slug' => 'spider',
                'preview_color' => '#10b981',
                'config' => self::spiderConfig(),
            ],
        ];
    }

    private static function hexagonsConfig(): array
    {
        return [
            'fpsLimit' => 60,
            'particles' => [
                'number' => ['value' => 50, 'density' => ['enable' => true, 'width' => 800, 'height' => 800]],
                'color' => ['value' => ['#d946ef', '#a855f7', '#8b5cf6']],
                'shape' => ['type' => 'polygon', 'options' => ['polygon' => ['sides' => 6]]],
                'opacity' => ['value' => ['min' => 0.3, 'max' => 0.8]],
                'size' => ['value' => ['min' => 10, 'max' => 20]],
                'rotate' => ['value' => 0, 'direction' => 'clockwise', 'animation' => ['enable' => true, 'speed' => 5, 'sync' => false]],
                'links' => ['enable' => true, 'distance' => 150, 'color' => '#d946ef', 'opacity' => 0.3, 'width' => 1],
                'move' => ['enable' => true, 'speed' => 1.5, 'direction' => 'none', 'outModes' => 'bounce'],
            ],
            'interactivity' => [
                'events' => [
                    'onHover' => ['enable' => true, 'mode' => 'grab', 'parallax' => ['enable' => true, 'force' => 60]],
                    'onClick' => ['enable' => true, 'mode' => 'push'],
                ],
                'modes' => [
                    'grab' => ['distance' => 200, 'links' => ['opacity' => 0.8]],
                    'push' => ['quantity' => 2],
                ],
            ],
            'detectRetina' => true,
        ];
    }

    private static function colorConfig(): array
    {
        return [
            'fpsLimit' => 60,
            'particles' => [
                'number' => ['value' => 80, 'density' => ['enable' => true, 'width' => 800, 'height' => 800]],
                'color' => ['value' => ['#d946ef', '#06b6d4', '#a855f7', '#f472b6', '#22d3d8']],
                'shape' => ['type' => 'circle'],
                'opacity' => ['value' => ['min' => 0.4, 'max' => 1], 'animation' => ['enable' => true, 'speed' => 1, 'sync' => false]],
                'size' => ['value' => ['min' => 2, 'max' => 6], 'animation' => ['enable' => true, 'speed' => 3, 'sync' => false]],
                'links' => ['enable' => false],
                'move' => [
                    'enable' => true,
                    'speed' => 2,
                    'direction' => 'none',
                    'outModes' => 'out',
                    'attract' => ['enable' => true, 'rotateX' => 600, 'rotateY' => 1200],
                ],
            ],
            'interactivity' => [
                'events' => [
                    'onHover' => ['enable' => true, 'mode' => 'bubble'],
                    'onClick' => ['enable' => true, 'mode' => 'repulse'],
                ],
                'modes' => [
                    'bubble' => ['distance' => 200, 'size' => 12, 'duration' => 2, 'opacity' => 1],
                    'repulse' => ['distance' => 200, 'duration' => 0.4],
                ],
            ],
            'detectRetina' => true,
        ];
    }

    private static function linksConfig(): array
    {
        return [
            'fpsLimit' => 60,
            'particles' => [
                'number' => ['value' => 60, 'density' => ['enable' => true, 'width' => 800, 'height' => 800]],
                'color' => ['value' => ['#d946ef', '#a855f7', '#06b6d4', '#8b5cf6']],
                'shape' => ['type' => ['circle', 'triangle']],
                'opacity' => ['value' => ['min' => 0.1, 'max' => 0.5]],
                'size' => ['value' => ['min' => 1, 'max' => 4]],
                'links' => [
                    'enable' => true,
                    'distance' => 150,
                    'color' => '#a855f7',
                    'opacity' => 0.15,
                    'width' => 1,
                    'triangles' => ['enable' => true, 'opacity' => 0.03],
                ],
                'move' => ['enable' => true, 'speed' => 1, 'direction' => 'none', 'outModes' => 'bounce'],
            ],
            'interactivity' => [
                'events' => [
                    'onHover' => ['enable' => true, 'mode' => ['grab', 'bubble'], 'parallax' => ['enable' => true, 'force' => 60]],
                    'onClick' => ['enable' => true, 'mode' => 'push'],
                ],
                'modes' => [
                    'grab' => ['distance' => 200, 'links' => ['opacity' => 0.8, 'color' => '#d946ef']],
                    'bubble' => ['distance' => 250, 'size' => 6, 'duration' => 2, 'opacity' => 0.8],
                    'push' => ['quantity' => 4],
                ],
            ],
            'detectRetina' => true,
        ];
    }

    private static function starsConfig(): array
    {
        return [
            'fpsLimit' => 60,
            'particles' => [
                'number' => ['value' => 100, 'density' => ['enable' => true, 'width' => 800, 'height' => 800]],
                'color' => ['value' => ['#ffffff', '#fbbf24', '#f472b6']],
                'shape' => ['type' => 'star', 'options' => ['star' => ['sides' => 5]]],
                'opacity' => ['value' => ['min' => 0.2, 'max' => 1], 'animation' => ['enable' => true, 'speed' => 0.5, 'sync' => false]],
                'size' => ['value' => ['min' => 1, 'max' => 4]],
                'links' => ['enable' => false],
                'move' => ['enable' => true, 'speed' => 0.3, 'direction' => 'none', 'outModes' => 'out'],
            ],
            'interactivity' => [
                'events' => [
                    'onHover' => ['enable' => true, 'mode' => 'bubble', 'parallax' => ['enable' => true, 'force' => 80, 'smooth' => 10]],
                    'onClick' => ['enable' => true, 'mode' => 'push'],
                ],
                'modes' => [
                    'bubble' => ['distance' => 150, 'size' => 8, 'duration' => 2, 'opacity' => 1],
                    'push' => ['quantity' => 3],
                ],
            ],
            'detectRetina' => true,
        ];
    }

    private static function spiderConfig(): array
    {
        return [
            'renderer' => 'spider',
            'spider_count' => 2,
            'color' => '#ffffff',
        ];
    }

    private static function snowConfig(): array
    {
        return [
            'fpsLimit' => 60,
            'particles' => [
                'number' => ['value' => 80, 'density' => ['enable' => true, 'width' => 800, 'height' => 800]],
                'color' => ['value' => '#ffffff'],
                'shape' => ['type' => 'circle'],
                'opacity' => ['value' => ['min' => 0.3, 'max' => 0.8]],
                'size' => ['value' => ['min' => 1, 'max' => 5]],
                'links' => ['enable' => false],
                'move' => [
                    'enable' => true,
                    'speed' => 1,
                    'direction' => 'bottom',
                    'outModes' => ['default' => 'out', 'bottom' => 'out', 'top' => 'out'],
                    'straight' => false,
                ],
                'wobble' => ['enable' => true, 'distance' => 10, 'speed' => 10],
            ],
            'interactivity' => [
                'events' => [
                    'onHover' => ['enable' => true, 'mode' => 'repulse'],
                    'onClick' => ['enable' => true, 'mode' => 'push'],
                ],
                'modes' => [
                    'repulse' => ['distance' => 100, 'duration' => 0.4],
                    'push' => ['quantity' => 4],
                ],
            ],
            'detectRetina' => true,
        ];
    }
}
