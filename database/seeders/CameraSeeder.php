<?php

namespace Database\Seeders;

use App\Models\Camera;
use Illuminate\Database\Seeder;

class CameraSeeder extends Seeder
{
    public function run(): void
    {
        $cameras = [
            [
                'brand' => 'Canon',
                'model' => 'PowerShot G12',
                'category' => 'Digicam',
                'daily_rate' => 12,
                'description' => 'A pocketable classic point-and-shoot for everyday stills and video.',
                'specs' => ['10 MP', '5× optical zoom', 'Compact'],
            ],
            [
                'brand' => 'Canon',
                'model' => 'EOS 80D',
                'category' => 'DSLR',
                'daily_rate' => 28,
                'description' => 'A dependable DSLR with a bright optical viewfinder and familiar controls.',
                'specs' => ['24.2 MP', 'Full HD video', 'APS-C'],
            ],
            [
                'brand' => 'Blackmagic Design',
                'model' => 'URSA Mini Pro 4.6K',
                'category' => 'Cinema camera',
                'daily_rate' => 115,
                'description' => 'A full-size production camera built for professional cinema workflows.',
                'specs' => ['4.6K RAW', 'Super 35', 'EF mount'],
            ],
            [
                'brand' => 'DJI',
                'model' => 'Phantom 4 Pro',
                'category' => 'Drone',
                'daily_rate' => 45,
                'description' => 'A classic aerial camera platform for steady, high-resolution establishing shots.',
                'specs' => ['20 MP', '4K video', '3-axis gimbal'],
            ],
        ];

        foreach ($cameras as $camera) {
            Camera::updateOrCreate(
                ['brand' => $camera['brand'], 'model' => $camera['model']],
                [...$camera, 'availability' => 'available'],
            );
        }
    }
}
