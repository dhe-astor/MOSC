<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FinanceCategory;
use App\Models\Diocese;
use App\Models\User;
use Illuminate\Support\Str;

class FinanceCategorySeeder extends Seeder
{
    public function run(): void
    {
        $diocese = Diocese::first();
        if (!$diocese) {
            return;
        }

        $superAdmin = User::role('Super Admin')->first() ?? User::first();
        if (!$superAdmin) {
            return;
        }

        $incomeCategories = [
            ['name' => 'General Donation', 'type' => 'donation'],
            ['name' => 'Membership Contribution', 'type' => 'income'],
            ['name' => 'Course Fee', 'type' => 'fee'],
            ['name' => 'Event Fee', 'type' => 'fee'],
            ['name' => 'Parish Contribution', 'type' => 'income'],
            ['name' => 'Diocese Contribution', 'type' => 'income'],
            ['name' => 'Charity Collection', 'type' => 'donation'],
            ['name' => 'Other Income', 'type' => 'income'],
        ];

        $expenseCategories = [
            ['name' => 'Rent', 'type' => 'expense'],
            ['name' => 'Hall Booking', 'type' => 'expense'],
            ['name' => 'Priest Travel', 'type' => 'expense'],
            ['name' => 'Event Expense', 'type' => 'expense'],
            ['name' => 'Charity Support', 'type' => 'expense'],
            ['name' => 'Printing', 'type' => 'expense'],
            ['name' => 'Office Expense', 'type' => 'expense'],
            ['name' => 'Bank Charges', 'type' => 'expense'],
            ['name' => 'Other Expense', 'type' => 'expense'],
        ];

        foreach ($incomeCategories as $cat) {
            FinanceCategory::updateOrCreate(
                [
                    'diocese_id' => $diocese->id,
                    'church_id' => null,
                    'slug' => Str::slug($cat['name']),
                ],
                [
                    'name' => $cat['name'],
                    'category_type' => $cat['type'],
                    'description' => 'System default ' . strtolower($cat['name']) . ' category',
                    'is_system' => true,
                    'status' => 'active',
                    'created_by' => $superAdmin->id,
                ]
            );
        }

        foreach ($expenseCategories as $cat) {
            FinanceCategory::updateOrCreate(
                [
                    'diocese_id' => $diocese->id,
                    'church_id' => null,
                    'slug' => Str::slug($cat['name']),
                ],
                [
                    'name' => $cat['name'],
                    'category_type' => $cat['type'],
                    'description' => 'System default ' . strtolower($cat['name']) . ' category',
                    'is_system' => true,
                    'status' => 'active',
                    'created_by' => $superAdmin->id,
                ]
            );
        }
    }
}
