<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\WebsiteSetting;

class WebsiteSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key' => 'public_contact_email',
                'value' => 'info@msoc-europe.org',
                'group' => 'contact'
            ],
            [
                'key' => 'public_contact_phone',
                'value' => '+43 1 234567',
                'group' => 'contact'
            ],
            [
                'key' => 'public_footer_address',
                'value' => 'MSOC Europe Diocesan Center, Vienna, Austria',
                'group' => 'contact'
            ],
            [
                'key' => 'public_contact_map_url',
                'value' => 'https://maps.google.com/?q=Vienna',
                'group' => 'contact'
            ],
            [
                'key' => 'public_office_hours',
                'value' => 'Mon - Fri: 9:00 AM - 5:00 PM',
                'group' => 'contact'
            ],
            [
                'key' => 'social_links',
                'value' => [
                    'facebook' => 'https://facebook.com/msoc-europe',
                    'youtube' => 'https://youtube.com/msoc-europe',
                    'twitter' => 'https://twitter.com/msoc-europe'
                ],
                'group' => 'social'
            ],
            [
                'key' => 'seo_defaults',
                'value' => [
                    'title' => 'MSOC Europe Diocese',
                    'description' => 'Official Portal of the Malankara Syrian Orthodox Church (MSOC) Europe Diocese.',
                    'keywords' => 'MSOC, Europe, Orthodox, Church, Diocese'
                ],
                'group' => 'seo'
            ],
            [
                'key' => 'homepage_featured_news_count',
                'value' => 3,
                'group' => 'homepage'
            ],
            [
                'key' => 'homepage_featured_events_count',
                'value' => 3,
                'group' => 'homepage'
            ],
            [
                'key' => 'homepage_featured_gallery_count',
                'value' => 3,
                'group' => 'homepage'
            ]
        ];

        foreach ($settings as $s) {
            WebsiteSetting::updateOrCreate(
                [
                    'diocese_id' => 1,
                    'key' => $s['key']
                ],
                [
                    'value' => $s['value'],
                    'group' => $s['group']
                ]
            );
        }
    }
}
