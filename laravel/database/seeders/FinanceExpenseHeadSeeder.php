<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FinanceChartAccount;
use App\Models\FinanceExpenseHead;

class FinanceExpenseHeadSeeder extends Seeder
{
    public function run(): void
    {
        $expenseAccount = FinanceChartAccount::where('code', '5000')->first();
        if (!$expenseAccount) {
            return;
        }

        $expenseHeads = [
            // Clergy & Staff Payments
            ['code' => 'EXP-001', 'name' => 'Priest Stipend / Salary', 'description' => 'Monthly stipend paid to the parish priest.'],
            ['code' => 'EXP-002', 'name' => 'Priest Travel & Mileage Allowance', 'description' => 'Travel reimbursements and fuel allowances for parish visits.'],
            ['code' => 'EXP-003', 'name' => 'Assistant Priest Allowance', 'description' => 'Stipend/honorarium paid to assisting clergy.'],
            ['code' => 'EXP-004', 'name' => 'Priest Health Insurance Contribution', 'description' => 'Parish contribution to priest\'s medical insurance.'],
            ['code' => 'EXP-005', 'name' => 'Priest Pension / Welfare Fund', 'description' => 'Contributions to the clergy retirement and welfare funds.'],
            ['code' => 'EXP-006', 'name' => 'Choir Master / Organist Stipend', 'description' => 'Stipend or honorarium for musical directors.'],
            ['code' => 'EXP-007', 'name' => 'Sexton / Altar Assistant Salary', 'description' => 'Monthly wages paid to church caretaker or sexton.'],
            ['code' => 'EXP-008', 'name' => 'Guest Priest Honorarium', 'description' => 'Allowances for visiting clergy conducting special services.'],

            // Altar & Liturgical
            ['code' => 'EXP-011', 'name' => 'Holy Qurbana Bread & Wine', 'description' => 'Ingredients and costs for baking wheat bread and communion wine.'],
            ['code' => 'EXP-012', 'name' => 'Altar Candles & Oil', 'description' => 'Sanctuary candles, oil for lamps, and matches.'],
            ['code' => 'EXP-013', 'name' => 'Liturgical Incense & Charcoal', 'description' => 'Incense resins, charcoal briquettes, and altar accessories.'],
            ['code' => 'EXP-014', 'name' => 'Vestments & Liturgical Robes', 'description' => 'Tailoring, washing, and purchasing priest and altar server robes.'],
            ['code' => 'EXP-015', 'name' => 'Liturgical Books & Bibles', 'description' => 'Purchase of prayer books, bible lectionaries, and calendars.'],
            ['code' => 'EXP-016', 'name' => 'Altar Decoration & Flowers', 'description' => 'Fresh flowers, drapes, and altar decorations for feasts.'],

            // Church & Parsonage Maintenance
            ['code' => 'EXP-021', 'name' => 'Church Building Repair', 'description' => 'Plumbing, masonry, painting, and structural repairs of the church.'],
            ['code' => 'EXP-022', 'name' => 'Parsonage Repair & Maintenance', 'description' => 'Renovation, furniture, and upkeep of the priest\'s residence.'],
            ['code' => 'EXP-023', 'name' => 'Parish Hall Upkeep & Cleaning', 'description' => 'Cleaning supplies, professional sanitation, and repairs for the hall.'],
            ['code' => 'EXP-024', 'name' => 'Gardening & Landscaping', 'description' => 'Mowing lawn, clearing weeds, and cemetery/church landscaping.'],
            ['code' => 'EXP-025', 'name' => 'Security & Fire Safety System', 'description' => 'CCTV maintenance, security guards, fire extinguisher checks.'],
            ['code' => 'EXP-026', 'name' => 'Waste Disposal & Sewage', 'description' => 'Public waste collection fees and septic tank maintenance.'],

            // Utilities
            ['code' => 'EXP-031', 'name' => 'Church Electricity', 'description' => 'Power utility bills for the main church building.'],
            ['code' => 'EXP-032', 'name' => 'Parsonage Electricity', 'description' => 'Power utility bills for the priest\'s residence.'],
            ['code' => 'EXP-033', 'name' => 'Church Water & Sewerage', 'description' => 'Water utility bills for the church.'],
            ['code' => 'EXP-034', 'name' => 'Parsonage Water & Sewerage', 'description' => 'Water utility bills for the parsonage.'],
            ['code' => 'EXP-035', 'name' => 'Church Heating / Gas', 'description' => 'Gas, heating oil, or central district heating bills for the church.'],
            ['code' => 'EXP-036', 'name' => 'Parsonage Heating / Gas', 'description' => 'Heating utilities for the parsonage.'],
            ['code' => 'EXP-037', 'name' => 'Church Internet & Telephone', 'description' => 'Broadband wifi and landline telephone service for the church office.'],
            ['code' => 'EXP-038', 'name' => 'Parsonage Internet & Telephone', 'description' => 'Broadband and phone utilities for the parsonage.'],

            // Administrative Expenses
            ['code' => 'EXP-041', 'name' => 'Office Stationery & Printing', 'description' => 'Paper, pens, envelopes, folders, and calendar printing.'],
            ['code' => 'EXP-042', 'name' => 'Postage & Courier Charges', 'description' => 'Stamps, parcels, and express mail services.'],
            ['code' => 'EXP-043', 'name' => 'Computer Software Subscriptions', 'description' => 'Parish accounting software, Zoom, Microsoft 365, etc.'],
            ['code' => 'EXP-044', 'name' => 'Website Domain & Hosting', 'description' => 'Web hosting space, domain registration, and SSL certificates.'],
            ['code' => 'EXP-045', 'name' => 'Bank Transaction Fees', 'description' => 'Monthly account maintenance fees, transaction charges, card terminal fees.'],
            ['code' => 'EXP-046', 'name' => 'Legal & Professional Consultancy', 'description' => 'Attorney fees, notary fees, or property consultants.'],
            ['code' => 'EXP-047', 'name' => 'External Audit Fees', 'description' => 'Professional audit fees for annual financial statement verification.'],
            ['code' => 'EXP-048', 'name' => 'Souvenir & Directory Printing', 'description' => 'Costs for publishing directory, magazines, or souvenirs.'],
            ['code' => 'EXP-049', 'name' => 'General Advertising & PR', 'description' => 'Parish event posters, flyers, newspaper notices, social media ads.'],

            // Diocese Shares & Central Contributions
            ['code' => 'EXP-051', 'name' => 'Diocesan Share (Standard)', 'description' => 'Mandatory annual contribution forwarded to the Diocese Treasury.'],
            ['code' => 'EXP-052', 'name' => 'Diocesan Centenary Contribution', 'description' => 'Centenary specific contributions collected and forwarded.'],
            ['code' => 'EXP-053', 'name' => 'Bishop Reception & Travel support', 'description' => 'Accommodations, food, and travel support for visiting bishops.'],
            ['code' => 'EXP-054', 'name' => 'Diocesan Assembly Delegates Travel', 'description' => 'Travel reimbursements for lay delegates attending assembly meetings.'],

            // Festivals & Event Expenses
            ['code' => 'EXP-061', 'name' => 'Parish Festival (Perunnal) Food', 'description' => 'Groceries, catering, and kitchen expenses for Perunnal feast.'],
            ['code' => 'EXP-062', 'name' => 'Perunnal Sound, Stage & Lighting', 'description' => 'Hiring audio systems, stage scaffolding, and decorative lights.'],
            ['code' => 'EXP-063', 'name' => 'Perunnal Cultural Program speaker', 'description' => 'Honorarium, travel, and gifts for event speakers.'],
            ['code' => 'EXP-064', 'name' => 'Christmas Day Celebration', 'description' => 'Fellowship breakfast, Christmas tree, cake, and children gifts.'],
            ['code' => 'EXP-065', 'name' => 'Easter Day Celebration', 'description' => 'Easter eggs, candles, and community dinner expenses.'],
            ['code' => 'EXP-066', 'name' => 'Holy Week / Lent Liturgical supplies', 'description' => 'Additional Palm leaves, special candles, Passion Week setups.'],
            ['code' => 'EXP-067', 'name' => 'Parish Day Stage & Sound hire', 'description' => 'Sound and stage setup for parish day assembly.'],

            // Education & Sub-Organizations
            ['code' => 'EXP-071', 'name' => 'Sunday School Textbooks & Guides', 'description' => 'Purchasing textbooks and guides from central association.'],
            ['code' => 'EXP-072', 'name' => 'Sunday School Exam registration', 'description' => 'Registration and certificate fees paid to central board.'],
            ['code' => 'EXP-073', 'name' => 'Sunday School Festival Prizes', 'description' => 'Trophies, books, and gifts distributed to students.'],
            ['code' => 'EXP-074', 'name' => 'Youth Association Camp venue', 'description' => 'Booking retreat centers, cabins, or camp sites.'],
            ['code' => 'EXP-075', 'name' => 'Youth Camp Speaker Travel & Fee', 'description' => 'Honorarium and flights for youth retreat leaders.'],
            ['code' => 'EXP-076', 'name' => 'Marthamariyam Samajam study manuals', 'description' => 'Study booklets and devotional materials for the women\'s wing.'],
            ['code' => 'EXP-077', 'name' => 'AMOSS Altar Servers training', 'description' => 'Training guides, altar server cross/chains, and retreats.'],

            // Charity & Benevolence
            ['code' => 'EXP-081', 'name' => 'Benevolent Medical Aid', 'description' => 'Financial assistance given to members/non-members for medical treatment.'],
            ['code' => 'EXP-082', 'name' => 'Benevolent Education Scholarship', 'description' => 'Scholarships distributed to poor students.'],
            ['code' => 'EXP-083', 'name' => 'Benevolent Marriage Aid', 'description' => 'Grants to poor families conducting weddings.'],
            ['code' => 'EXP-084', 'name' => 'Benevolent Monthly Pension', 'description' => 'Regular monthly assistance for widows or destitute families.'],
            ['code' => 'EXP-085', 'name' => 'Disaster Relief Distribution', 'description' => 'Disaster relief funds transferred to affected areas.'],

            // Financial & Non-Operating
            ['code' => 'EXP-091', 'name' => 'Insurance (Property & Liability)', 'description' => 'Annual insurance premiums for the church and parsonage.'],
            ['code' => 'EXP-092', 'name' => 'Parish Vehicle Fuel & Maintenance', 'description' => 'Fuel, oil change, tyre swap, and servicing of parish car.'],
            ['code' => 'EXP-093', 'name' => 'Parish Loan Principal Repayment', 'description' => 'Monthly or annual principal payments on property loans.'],
            ['code' => 'EXP-094', 'name' => 'Parish Loan Interest Paid', 'description' => 'Interest payments on outstanding mortgages.'],
            ['code' => 'EXP-095', 'name' => 'Cash Batch Counting Discrepancy', 'description' => 'Write-offs for physical cash counting shortages.'],
            ['code' => 'EXP-096', 'name' => 'Bad Debt Write-off', 'description' => 'Writing off member subscription balances deemed uncollectible.'],
            ['code' => 'EXP-097', 'name' => 'Suspense Clearing Expense', 'description' => 'Temporary ledger adjustments for bank statement discrepancies.'],
            ['code' => 'EXP-098', 'name' => 'Miscellaneous Administrative Expense', 'description' => 'Small sundry expenses not fit in other categories.'],
        ];

        foreach ($expenseHeads as $head) {
            FinanceExpenseHead::updateOrCreate(
                ['code' => $head['code']],
                [
                    'chart_account_id' => $expenseAccount->id,
                    'name' => $head['name'],
                    'description' => $head['description'],
                    'is_active' => true,
                ]
            );
        }
    }
}
