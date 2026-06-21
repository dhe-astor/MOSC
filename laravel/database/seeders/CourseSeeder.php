<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Course;
use App\Models\Diocese;
use App\Models\User;

class CourseSeeder extends Seeder
{
    public function run(): void
    {
        $diocese = Diocese::first();
        if (!$diocese) {
            return;
        }

        $superAdmin = User::where('email', 'superadmin@msoc-europe.org')->first();
        $createdBy = $superAdmin ? $superAdmin->id : 1;

        $courses = [
            [
                'name' => 'Pre-Marriage Course',
                'slug' => 'pre-marriage-course',
                'course_type' => 'pre_marriage',
                'description' => 'A mandatory pre-marital preparation course for youth and couples planning to get married in the church.',
                'eligibility' => 'Eligible for all members of marriageable age.',
                'default_fee_amount' => 50.00,
                'certificate_enabled' => true,
                'feedback_required' => true,
                'attendance_required_percentage' => 100,
            ],
            [
                'name' => 'Post-Marriage Course',
                'slug' => 'post-marriage-course',
                'course_type' => 'post_marriage',
                'description' => 'A supportive enrichment course for newly married couples focusing on Christian family life, relationships, and parenthood.',
                'eligibility' => 'Eligible for married couples.',
                'default_fee_amount' => 30.00,
                'certificate_enabled' => true,
                'feedback_required' => false,
                'attendance_required_percentage' => 75,
            ],
            [
                'name' => 'Syriac Language Course',
                'slug' => 'syriac-language-course',
                'course_type' => 'syriac_language',
                'description' => 'Introduction to the liturgical and classical Syriac language, focusing on prayer readings and basic vocabulary.',
                'eligibility' => 'Open to all age groups.',
                'default_fee_amount' => 25.00,
                'certificate_enabled' => true,
                'feedback_required' => false,
                'attendance_required_percentage' => 75,
            ],
            [
                'name' => 'Bible Course',
                'slug' => 'bible-course',
                'course_type' => 'bible_course',
                'description' => 'A structured study of Old and New Testament books, history, theology, and exegesis.',
                'eligibility' => 'Open to all members.',
                'default_fee_amount' => 0.00,
                'certificate_enabled' => true,
                'feedback_required' => false,
                'attendance_required_percentage' => 80,
            ],
            [
                'name' => 'Liturgical Course',
                'slug' => 'liturgical-course',
                'course_type' => 'liturgical_course',
                'description' => 'Theological and practical study of the Holy Qurbana, sacraments, and liturgical year.',
                'eligibility' => 'Open to all members.',
                'default_fee_amount' => 0.00,
                'certificate_enabled' => true,
                'feedback_required' => false,
                'attendance_required_percentage' => 75,
            ],
            [
                'name' => 'Altar Assistants Course',
                'slug' => 'altar-assistants-course',
                'course_type' => 'altar_assistants',
                'description' => 'Training for boys and youth serving in the sanctuary during the Holy Qurbana and sacraments.',
                'eligibility' => 'Restricted to altar servers / altar assistants.',
                'default_fee_amount' => 0.00,
                'certificate_enabled' => true,
                'feedback_required' => false,
                'attendance_required_percentage' => 90,
            ],
        ];

        foreach ($courses as $c) {
            Course::updateOrCreate(
                ['slug' => $c['slug']],
                array_merge($c, [
                    'diocese_id' => $diocese->id,
                    'status' => 'active',
                    'show_on_portal' => true,
                    'created_by' => $createdBy,
                ])
            );
        }
    }
}
