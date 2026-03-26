<?php

namespace Database\Seeders;

use App\Models\Car;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CarSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $owner = User::query()->first();

        if (! $owner) {
            $owner = User::query()->firstOrCreate(
                ['email' => 'cars.seed@example.com'],
                [
                    'name' => 'Cars Seed User',
                    'password' => Hash::make(Str::random(32)),
                    'email_verified_at' => now(),
                ]
            );
        }

        $cars = [
            [
                'title' => 'BMW M3 Competition',
                'brand' => 'BMW',
                'production_year' => 2023,
                'horsepower' => 510,
                'image' => 'https://www.slashgear.com/img/gallery/2023-bmw-m3-competition-xdrive-review-bonafide-supercar-killer/l-intro-1694549932.jpg',
                'description' => 'High-performance sports sedan with rear-wheel drive dynamics.',
            ],
            [
                'title' => 'Audi RS6 Avant',
                'brand' => 'Audi',
                'production_year' => 2022,
                'horsepower' => 600,
                'image' => 'https://www.cnet.com/a/img/resize/917afb99da2a4148097d80b950c98747238d33e6/hub/2022/07/13/f1114ae6-ac96-4e58-aa5a-d4741149e8a9/ogi1-2022-audi-rs6-avant-002.jpg?auto=webp&fit=crop&height=675&width=1200',
                'description' => 'Twin-turbo V8 performance wagon with everyday practicality.',
            ],
            [
                'title' => 'Porsche 911 Carrera S',
                'brand' => 'Porsche',
                'production_year' => 2021,
                'horsepower' => 450,
                'image' => 'https://robbreport.com/wp-content/uploads/2021/05/1-13.jpg?w=1000',
                'description' => 'Iconic rear-engine coupe with precise handling and strong acceleration.',
            ],
            [
                'title' => 'Mercedes-AMG C63 S',
                'brand' => 'Mercedes-Benz',
                'production_year' => 2020,
                'horsepower' => 503,
                'image' => 'https://windingroad.com/wp-content/uploads/autos_db/thumbnails/Photo_Aug_10_4_20_27_PM.jpg',
                'description' => 'Luxury sports sedan combining comfort and aggressive AMG power.',
            ],
            [
                'title' => 'Toyota GR Supra',
                'brand' => 'Toyota',
                'production_year' => 2023,
                'horsepower' => 382,
                'image' => 'https://cdn.motor1.com/images/mgl/VzXbYR/s1/2023-toyota-gr-supra-gt4-evo.jpg',
                'description' => 'Two-seat sports coupe known for turbocharged inline-six performance.',
            ],
            [
                'title' => 'Nissan GT-R',
                'brand' => 'Nissan',
                'production_year' => 2019,
                'horsepower' => 565,
                'image' => 'https://wieck-nissanao-production.s3.us-west-1.amazonaws.com/releaseInlineImages/a5c21ac282c8cec160193ee5f450fe8a911959cb',
                'description' => 'All-wheel-drive supercar benchmark with rapid launch capability.',
            ],
            [
                'title' => 'Ford Mustang GT',
                'brand' => 'Ford',
                'production_year' => 2022,
                'horsepower' => 450,
                'image' => 'https://hips.hearstapps.com/hmg-prod/images/2022-ford-mustang-stealth-edition-02-1633475393.jpg?crop=0.671xw:1.00xh;0.125xw,0&resize=2048:*',
                'description' => 'Classic V8 muscle car with modern technology and bold styling.',
            ],
            [
                'title' => 'Chevrolet Corvette Z06',
                'brand' => 'Chevrolet',
                'production_year' => 2023,
                'horsepower' => 670,
                'image' => 'https://www.cnet.com/a/img/resize/10cabb35075a74f62d16b5a9708f9de622d73cf2/hub/2022/10/03/b7904ec6-005c-4d7c-ad90-0fe7f95c382c/chevrolet-corvette-z06-2023-738090.jpg?auto=webp&width=1200',
                'description' => 'Mid-engine American supercar with naturally aspirated V8 character.',
            ],
            [
                'title' => 'Honda Civic Type R',
                'brand' => 'Honda',
                'production_year' => 2024,
                'horsepower' => 315,
                'image' => 'https://editorial.pxcrush.net/carsales/general/editorial/2024-honda-civic-type-r_7433.jpg?width=1024&height=682',
                'description' => 'Track-capable hot hatch with sharp steering and manual gearbox.',
            ],
            [
                'title' => 'Lamborghini Huracan EVO',
                'brand' => 'Lamborghini',
                'production_year' => 2021,
                'horsepower' => 631,
                'image' => 'https://img.sm360.ca/images/article/humberviewgroup-941/116178//2021-lamborghini-huracan-evo1687960861485.png',
                'description' => 'V10 supercar delivering dramatic design and instant response.',
            ],
            [
                'title' => 'Volkswagen Golf R',
                'brand' => 'Volkswagen',
                'production_year' => 2023,
                'horsepower' => 315,
                'image' => 'https://hips.hearstapps.com/mtg-prod/65a1d20ed44c0100084da60b/2023-volkswagen-golf-r-20th-anniversary-edition-31.jpg',
                'description' => 'All-wheel-drive hatchback that blends daily comfort with speed.',
            ],
            [
                'title' => 'Subaru WRX STI',
                'brand' => 'Subaru',
                'production_year' => 2020,
                'horsepower' => 310,
                'image' => 'https://media.drive.com.au/obj/tx_q:50,rs:auto:1920:1080:1/caradvice/private/lzk2agszaebk5lhrlpzs',
                'description' => 'Rally-inspired turbo sedan with strong grip and playful handling.',
            ],
            [
                'title' => 'McLaren 720S',
                'brand' => 'McLaren',
                'production_year' => 2022,
                'horsepower' => 710,
                'image' => 'https://i.gaw.to/vehicles/photos/40/28/402886-2022-mclaren-720s.jpg?1024x640',
                'description' => 'Lightweight twin-turbo supercar focused on power-to-weight performance.',
            ],
        ];

        Car::query()
            ->where(function ($query) {
                $query->where('title', '')->orWhereNull('title');
            })
            ->where(function ($query) {
                $query->where('brand', '')->orWhereNull('brand');
            })
            ->delete();

        foreach ($cars as $carData) {
            Car::query()->updateOrCreate(
                [
                    'title' => $carData['title'],
                    'brand' => $carData['brand'],
                    'production_year' => $carData['production_year'],
                ],
                [
                    ...$carData,
                    'user_id' => $owner->id,
                ]
            );
        }

        $currentVersion = (int) Cache::get('cars:cache_version', 1);
        Cache::forever('cars:cache_version', $currentVersion + 1);
    }
}
