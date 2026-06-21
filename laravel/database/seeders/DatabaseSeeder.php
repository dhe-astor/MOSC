<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            DioceseSeeder::class,
            CountrySeeder::class,
            ChurchSeeder::class,
            UserSeeder::class,
            CertificateTemplateSeeder::class,
            CourseSeeder::class,
            MinistryOrganizationSeeder::class,
            FinanceCategorySeeder::class,
            WebsiteSettingSeeder::class,
            NotificationTemplateSeeder::class,
            ReportDefinitionSeeder::class,
            FinanceChartAccountSeeder::class,
            FinanceFundClassSeeder::class,
            FinanceIncomeHeadSeeder::class,
            FinanceExpenseHeadSeeder::class,
        ]);
    }
}
