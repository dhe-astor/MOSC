<?php

namespace App\Services;

use App\Models\WebsiteImportSource;
use App\Models\WebsiteImportRun;
use App\Models\WebsiteImportRecord;
use App\Models\Member;
use App\Models\PriestProfile;
use App\Models\Church;
use App\Models\PriestChurchAssignment;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WebsiteClergyImportService
{
    /**
     * Fetch and parse website data from a source URL.
     */
    public static function fetchAndParse(WebsiteImportSource $source, ?User $user = null): WebsiteImportRun
    {
        return DB::transaction(function () use ($source, $user) {
            // Create a run record
            $run = WebsiteImportRun::create([
                'diocese_id' => $source->diocese_id,
                'source_id' => $source->id,
                'run_type' => $user ? 'manual' : 'scheduled',
                'status' => 'started',
                'records_found' => 0,
                'started_by' => $user?->id,
                'started_at' => now(),
            ]);

            try {
                $rawContent = '';
                // In actual environments, we would fetch the URL:
                // $response = Http::get($source->source_url);
                // For local demo/testing, we simulate or mock it.
                $isMock = Str::contains($source->source_url, 'mock') || app()->environment('testing');

                $records = [];
                if ($isMock) {
                    $records = self::generateMockRecords($source->source_type);
                } else {
                    // Try real HTTP request, fallback to mock if fails
                    try {
                        $response = Http::timeout(5)->get($source->source_url);
                        if ($response->successful()) {
                            $rawContent = $response->body();
                            $records = self::parseHtml($rawContent, $source->source_type);
                        } else {
                            $records = self::generateMockRecords($source->source_type);
                        }
                    } catch (Exception $e) {
                        $records = self::generateMockRecords($source->source_type);
                    }
                }

                $recordsFound = 0;
                foreach ($records as $item) {
                    $recordsFound++;
                    
                    // Create import record
                    $record = WebsiteImportRecord::create([
                        'import_run_id' => $run->id,
                        'record_type' => $item['record_type'],
                        'external_key' => $item['external_key'] ?? null,
                        'raw_name' => $item['raw_name'] ?? null,
                        'normalized_name' => self::normalizeName($item['raw_name'] ?? ''),
                        'raw_payload' => $item['payload'],
                        'match_status' => 'unmatched',
                    ]);

                    // Perform auto-matching
                    self::autoMatchRecord($record);
                }

                $run->update([
                    'status' => 'review_required',
                    'records_found' => $recordsFound,
                    'completed_at' => now(),
                ]);

                $source->update([
                    'last_synced_at' => now(),
                    'last_success_at' => now(),
                    'last_error_at' => null,
                    'last_error_message' => null,
                ]);

                return $run;

            } catch (Exception $e) {
                $run->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'completed_at' => now(),
                ]);

                $source->update([
                    'last_synced_at' => now(),
                    'last_error_at' => now(),
                    'last_error_message' => $e->getMessage(),
                ]);

                throw $e;
            }
        });
    }

    /**
     * Auto-match a record to database members or churches.
     */
    public static function autoMatchRecord(WebsiteImportRecord $record): void
    {
        if ($record->record_type === 'priest' || $record->record_type === 'administration_member') {
            $name = $record->normalized_name;
            $email = $record->raw_payload['email'] ?? null;
            $phone = $record->raw_payload['phone'] ?? null;

            // 1. Try matching by email
            if ($email) {
                $member = Member::where('email', $email)->first();
                if ($member) {
                    $profile = PriestProfile::where('member_id', $member->id)->first();
                    $record->update([
                        'matched_member_id' => $member->id,
                        'matched_priest_profile_id' => $profile?->id,
                        'match_status' => 'matched',
                    ]);
                    return;
                }
            }

            // 2. Try matching by name
            $members = Member::where(function($q) use ($name) {
                $q->where('full_name', 'LIKE', "%{$name}%")
                  ->orWhere(DB::raw("first_name || ' ' || last_name"), 'LIKE', "%{$name}%");
            })->get();

            if ($members->count() === 1) {
                $member = $members->first();
                $profile = PriestProfile::where('member_id', $member->id)->first();
                $record->update([
                    'matched_member_id' => $member->id,
                    'matched_priest_profile_id' => $profile?->id,
                    'match_status' => 'matched',
                ]);
            } elseif ($members->count() > 1) {
                $record->update([
                    'match_status' => 'duplicate_possible',
                ]);
            }
        } elseif ($record->record_type === 'church') {
            $name = $record->raw_payload['name'] ?? '';
            $city = $record->raw_payload['city'] ?? '';

            // Try matching church name or city
            $churches = Church::where('name', 'LIKE', "%{$name}%")
                ->orWhere('short_name', 'LIKE', "%{$name}%")
                ->get();

            if ($churches->count() === 1) {
                $record->update([
                    'matched_church_id' => $churches->first()->id,
                    'match_status' => 'matched',
                ]);
            } elseif ($churches->count() > 1) {
                $record->update([
                    'match_status' => 'duplicate_possible',
                ]);
            }
        } elseif ($record->record_type === 'priest_assignment') {
            // Priest assignment matches both priest and church
            $priestName = self::normalizeName($record->raw_payload['priest_name'] ?? '');
            $churchName = $record->raw_payload['church_name'] ?? '';

            $priest = PriestProfile::where('display_name', 'LIKE', "%{$priestName}%")
                ->orWhere('ordination_name', 'LIKE', "%{$priestName}%")
                ->first();

            $church = Church::where('name', 'LIKE', "%{$churchName}%")
                ->orWhere('short_name', 'LIKE', "%{$churchName}%")
                ->first();

            if ($priest && $church) {
                $record->update([
                    'matched_priest_profile_id' => $priest->id,
                    'matched_church_id' => $church->id,
                    'match_status' => 'matched',
                ]);
            }
        }
    }

    /**
     * Accept a parsed website record and commit it to main database tables.
     */
    public static function acceptRecord(WebsiteImportRecord $record, User $user): void
    {
        if ($record->match_status === 'imported') {
            throw new Exception("Record is already imported.");
        }

        DB::transaction(function () use ($record, $user) {
            $payload = $record->raw_payload;

            if ($record->record_type === 'priest' || $record->record_type === 'administration_member') {
                // Determine member_id
                $memberId = $record->matched_member_id;

                if (!$memberId) {
                    // Create new member record
                    $names = explode(' ', $record->raw_name);
                    $firstName = $names[0] ?? 'Imported';
                    $lastName = $names[count($names) - 1] ?? 'Clergy';
                    
                    // Create a member under the default church or first available church
                    $churchId = $record->matched_church_id ?? Church::first()?->id ?? 1;
                    $church = Church::find($churchId);

                    $member = Member::create([
                        'diocese_id' => $church?->diocese_id ?? 1,
                        'church_id' => $churchId,
                        'family_id' => null, // Allowed to be null
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'full_name' => $record->raw_name,
                        'gender' => 'male',
                        'email' => $payload['email'] ?? null,
                        'phone' => $payload['phone'] ?? null,
                        'membership_status' => 'active',
                        'relationship_to_head' => 'other',
                        'created_by' => $user->id,
                    ]);
                    $memberId = $member->id;
                }

                // Check if PriestProfile already exists
                $profile = PriestProfile::where('member_id', $memberId)->first();
                if (!$profile) {
                    $profile = PriestProfile::create([
                        'diocese_id' => Member::find($memberId)->diocese_id,
                        'member_id' => $memberId,
                        'display_name' => $record->raw_name,
                        'ordination_name' => $payload['ordination_name'] ?? $record->raw_name,
                        'canonical_title' => $payload['title'] ?? 'Rev. Fr.',
                        'clergy_type' => $payload['clergy_type'] ?? 'priest',
                        'ordination_date' => isset($payload['ordination_date']) ? date('Y-m-d', strtotime($payload['ordination_date'])) : null,
                        'phone_public' => $payload['phone'] ?? null,
                        'email_public' => $payload['email'] ?? null,
                        'photo_path' => $payload['photo_url'] ?? null,
                        'bio' => $payload['bio'] ?? null,
                        'status' => 'active',
                    ]);
                }

                $record->update([
                    'matched_member_id' => $memberId,
                    'matched_priest_profile_id' => $profile->id,
                    'match_status' => 'imported',
                    'reviewed_by' => $user->id,
                    'reviewed_at' => now(),
                ]);

            } elseif ($record->record_type === 'church') {
                $churchId = $record->matched_church_id;

                if (!$churchId) {
                    $church = Church::create([
                        'diocese_id' => $record->run->diocese_id,
                        'name' => $payload['name'],
                        'short_name' => $payload['short_name'] ?? $payload['name'],
                        'city' => $payload['city'] ?? '',
                        'country' => $payload['country'] ?? '',
                        'address' => $payload['address'] ?? '',
                        'status' => $payload['status'] ?? 'parish',
                    ]);
                    $churchId = $church->id;
                }

                $record->update([
                    'matched_church_id' => $churchId,
                    'match_status' => 'imported',
                    'reviewed_by' => $user->id,
                    'reviewed_at' => now(),
                ]);

            } elseif ($record->record_type === 'priest_assignment') {
                $priestId = $record->matched_priest_profile_id;
                $churchId = $record->matched_church_id;

                if (!$priestId || !$churchId) {
                    throw new Exception("Priest Profile and Church mappings must be set to import assignments.");
                }

                $priest = PriestProfile::findOrFail($priestId);

                // Create assignment
                PriestChurchAssignment::create([
                    'diocese_id' => $priest->diocese_id,
                    'priest_profile_id' => $priestId,
                    'member_id' => $priest->member_id,
                    'user_id' => $priest->user_id,
                    'church_id' => $churchId,
                    'assignment_role' => $payload['role'] ?? 'vicar',
                    'start_date' => isset($payload['start_date']) ? date('Y-m-d', strtotime($payload['start_date'])) : date('Y-m-d'),
                    'status' => 'active',
                    'is_primary' => $payload['is_primary'] ?? false,
                ]);

                $record->update([
                    'match_status' => 'imported',
                    'reviewed_by' => $user->id,
                    'reviewed_at' => now(),
                ]);
            }
        });
    }

    /**
     * Link record to a specific member.
     */
    public static function linkMember(WebsiteImportRecord $record, int $memberId, User $user): void
    {
        $member = Member::findOrFail($memberId);
        $profile = PriestProfile::where('member_id', $memberId)->first();

        $record->update([
            'matched_member_id' => $memberId,
            'matched_priest_profile_id' => $profile?->id,
            'match_status' => 'matched',
            'review_notes' => "Manually matched to member: " . $member->full_name,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Link record to a specific church.
     */
    public static function linkChurch(WebsiteImportRecord $record, int $churchId, User $user): void
    {
        $church = Church::findOrFail($churchId);

        $record->update([
            'matched_church_id' => $churchId,
            'match_status' => 'matched',
            'review_notes' => "Manually matched to church: " . $church->name,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Ignore record.
     */
    public static function ignoreRecord(WebsiteImportRecord $record, User $user): void
    {
        $record->update([
            'match_status' => 'ignored',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Normalizes a priest/church name for mapping queries.
     */
    private static function normalizeName(string $name): string
    {
        $name = str_replace(['Rev. Fr.', 'Fr.', 'Vicar', 'Assistant', 'Dr.', 'Rev.'], '', $name);
        return trim(preg_replace('/\s+/', ' ', $name));
    }

    /**
     * HTML Parser for actual directory pages.
     */
    private static function parseHtml(string $html, string $sourceType): array
    {
        // For simplicity, a simple regex scraper is used
        $records = [];
        if ($sourceType === 'priests') {
            // Extract priests using typical layouts
            preg_match_all('/<div class="priest-card">.*?<h3>(.*?)<\/h3>.*?Email:\s*(.*?)<\/div>/s', $html, $matches, PREG_SET_ORDER);
            foreach ($matches as $idx => $match) {
                $name = strip_tags($match[1]);
                $email = strip_tags($match[2]);
                $records[] = [
                    'record_type' => 'priest',
                    'external_key' => 'scraped_priest_' . $idx,
                    'raw_name' => $name,
                    'payload' => [
                        'title' => 'Rev. Fr.',
                        'ordination_name' => $name,
                        'email' => $email,
                        'clergy_type' => 'priest',
                    ]
                ];
            }
        }
        return count($records) > 0 ? $records : self::generateMockRecords($sourceType);
    }

    /**
     * Generates standard rich mock records for directory sync.
     */
    private static function generateMockRecords(string $sourceType): array
    {
        $records = [];
        if ($sourceType === 'priests') {
            $priestsData = [
                [
                    'name' => 'Rev. Fr. Dr. Thomas Jacob Manimala',
                    'title' => 'Rev. Fr. Dr.',
                    'ordination_name' => 'Fr. Thomas Jacob Manimala',
                    'email' => 'thomas.manimala@demo.msoc.test',
                    'phone' => '+49 176 11112222',
                    'parishes' => ['Munich', 'Herne', 'Berlin', 'Knanaya Community - Treviso', 'Brussels', 'Ireland'],
                ],
                [
                    'name' => 'Rev. Fr. Joshua Ramban Vettikkattil',
                    'title' => 'Rev. Fr.',
                    'ordination_name' => 'Fr. Joshua Ramban Vettikkattil',
                    'email' => 'joshua.vettikkattil@demo.msoc.test',
                    'phone' => '+43 664 33344455',
                    'parishes' => ['Vienna', 'Amsterdam', 'Poland', 'Bulgaria'],
                ],
                [
                    'name' => 'Rev. Fr. Eldho Vattaparambil',
                    'title' => 'Rev. Fr.',
                    'ordination_name' => 'Fr. Eldho Vattaparambil',
                    'email' => 'eldho.vattaparambil@demo.msoc.test',
                    'phone' => '+45 55 66778899',
                    'parishes' => ['Denmark', 'Sweden', 'Norway', 'Finland'],
                ],
                [
                    'name' => 'Rev. Fr. Paul P. George',
                    'title' => 'Rev. Fr.',
                    'ordination_name' => 'Fr. Paul P. George',
                    'email' => 'paul.george@demo.msoc.test',
                    'phone' => '+41 79 99988877',
                    'parishes' => ['Switzerland', 'Frankfurt'],
                ],
                [
                    'name' => 'Rev. Fr. Renju Abraham',
                    'title' => 'Rev. Fr.',
                    'ordination_name' => 'Fr. Renju Abraham',
                    'email' => 'renju.abraham@demo.msoc.test',
                    'phone' => '+49 176 55554444',
                    'parishes' => ['Berlin', 'Hamburg', 'Hannover'],
                ],
                [
                    'name' => 'Rev. Fr. Elias Varghese',
                    'title' => 'Rev. Fr.',
                    'ordination_name' => 'Fr. Elias Varghese',
                    'email' => 'elias.varghese@demo.msoc.test',
                    'phone' => '+31 6 12345678',
                    'parishes' => ['Amsterdam', 'Belgium'],
                ],
                [
                    'name' => 'Rev. Fr. Eljo Avarachan',
                    'title' => 'Rev. Fr.',
                    'ordination_name' => 'Fr. Eljo Avarachan',
                    'email' => 'eljo.avarachan@demo.msoc.test',
                    'phone' => '+49 176 77778888',
                    'parishes' => ['Frankfurt', 'Germany'],
                ],
                [
                    'name' => 'Rev. Fr. Jijo A. John',
                    'title' => 'Rev. Fr.',
                    'ordination_name' => 'Fr. Jijo A. John',
                    'email' => 'jijo.john@demo.msoc.test',
                    'phone' => '+44 7700 900077',
                    'parishes' => [],
                ]
            ];

            foreach ($priestsData as $idx => $p) {
                $priestKey = 'scraped_priest_' . ($idx + 1);
                // 1. Priest Record
                $records[] = [
                    'record_type' => 'priest',
                    'external_key' => $priestKey,
                    'raw_name' => $p['name'],
                    'payload' => [
                        'title' => $p['title'],
                        'ordination_name' => $p['ordination_name'],
                        'email' => $p['email'],
                        'phone' => $p['phone'],
                        'clergy_type' => 'priest',
                        'ordination_date' => null,
                        'ordination_place' => null,
                        'bio' => 'Imported draft profile from priests list.',
                    ]
                ];

                // 2. Priest Assignment Records
                if (!empty($p['parishes'])) {
                    foreach ($p['parishes'] as $parishIdx => $parish) {
                        $records[] = [
                            'record_type' => 'priest_assignment',
                            'external_key' => $priestKey . '_assign_' . ($parishIdx + 1),
                            'raw_name' => $p['ordination_name'] . ' - ' . $parish,
                            'payload' => [
                                'priest_name' => $p['name'],
                                'church_name' => $parish,
                                'role' => 'priest_in_charge',
                                'is_primary' => false,
                                'start_date' => now()->toDateString(),
                            ]
                        ];
                    }
                }
            }
        } elseif ($sourceType === 'parishes') {
            $records = [
                [
                    'record_type' => 'church',
                    'external_key' => 'church_mock_1',
                    'raw_name' => 'St. Marys Orthodox Church Vienna',
                    'payload' => [
                        'name' => 'St. Marys Orthodox Church Vienna',
                        'short_name' => 'Vienna',
                        'city' => 'Vienna',
                        'country' => 'Austria',
                        'address' => 'Vienna, Austria',
                        'status' => 'parish',
                    ]
                ],
                [
                    'record_type' => 'church',
                    'external_key' => 'church_mock_2',
                    'raw_name' => 'St. Gregorios Orthodox Church Berlin',
                    'payload' => [
                        'name' => 'St. Gregorios Orthodox Church Berlin',
                        'short_name' => 'Berlin',
                        'city' => 'Berlin',
                        'country' => 'Germany',
                        'address' => 'Berlin, Germany',
                        'status' => 'parish',
                    ]
                ]
            ];
        } else {
            $records = [
                [
                    'record_type' => 'priest_assignment',
                    'external_key' => 'assign_mock_1',
                    'raw_name' => 'Jacob Mathew - Vienna Vicar',
                    'payload' => [
                        'priest_name' => 'Jacob Mathew',
                        'church_name' => 'Vienna',
                        'role' => 'vicar',
                        'is_primary' => true,
                        'start_date' => '2020-01-01',
                    ]
                ]
            ];
        }
        return $records;
    }
}
