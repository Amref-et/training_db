<?php

namespace App\Services;

use App\Models\Profession;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class LegacyHilSqlPreparationService
{
    private const TRAINING_PARTICIPANT_COLUMNS = [
        'id',
        'project_id',
        'region',
        'zone',
        'woreda',
        'org_unit',
        'training_title',
        'training_start_date',
        'training_end_date',
        'participant_name',
        'gender',
        'mobile_phone',
        'email',
        'profession',
        'organization_name',
        'organization_category',
        'organization_type',
        'pre_score_w1',
        'post_score_w1',
        'pre_score_w2',
        'post_score_w2',
        'pre_score_w3',
        'post_score_w3',
        'pre_score_w4',
        'post_score_w4',
        'organizer_name',
        'coaching_visit_1',
        'coaching_visit_2',
        'coaching_visit_3',
        'created_at',
        'updated_at',
    ];

    private const PROJECT_COLUMNS = [
        'id',
        'title',
        'technical_area',
        'participant_ids',
        'coaching_visit_1',
        'coaching_visit_2',
        'coaching_visit_3',
        'status',
        'remark',
        'created_at',
        'updated_at',
    ];

    public function prepare(string $sqlPath, ?string $outputDirectory = null): array
    {
        if (! File::exists($sqlPath)) {
            throw new \RuntimeException('SQL file not found: '.$sqlPath);
        }

        $outputDirectory ??= storage_path('app/import-prep/hil-legacy-'.now()->format('Ymd-His'));
        File::ensureDirectoryExists($outputDirectory);

        [$legacyParticipants, $legacyProjects] = $this->extractSourceRows($sqlPath);

        $prepared = $this->prepareDatasets($legacyParticipants, $legacyProjects);
        $files = $this->writePreparedFiles($outputDirectory, $prepared);

        $summary = [
            'source_file' => $sqlPath,
            'output_directory' => $outputDirectory,
            'generated_at' => now()->toDateTimeString(),
            'source_counts' => $prepared['summary']['source_counts'],
            'prepared_counts' => $prepared['summary']['prepared_counts'],
            'data_quality' => $prepared['summary']['data_quality'],
            'files' => $files,
        ];

        File::put(
            $outputDirectory.DIRECTORY_SEPARATOR.'manifest.json',
            json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        return $summary;
    }

    private function extractSourceRows(string $sqlPath): array
    {
        $handle = fopen($sqlPath, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open SQL file.');
        }

        $participants = [];
        $projects = [];
        $buffer = null;
        $table = null;

        try {
            while (($line = fgets($handle)) !== false) {
                if ($buffer === null) {
                    if (str_starts_with($line, 'INSERT INTO `training_participants` VALUES ')) {
                        $buffer = $line;
                        $table = 'training_participants';
                    } elseif (str_starts_with($line, 'INSERT INTO `projects` VALUES ')) {
                        $buffer = $line;
                        $table = 'projects';
                    } else {
                        continue;
                    }
                } else {
                    $buffer .= $line;
                }

                if (! str_ends_with(rtrim($buffer), ';')) {
                    continue;
                }

                $rows = $this->parseInsertStatement($buffer);

                if ($table === 'training_participants') {
                    foreach ($rows as $row) {
                        $participants[] = $this->combineRow(self::TRAINING_PARTICIPANT_COLUMNS, $row);
                    }
                }

                if ($table === 'projects') {
                    foreach ($rows as $row) {
                        $projects[] = $this->combineRow(self::PROJECT_COLUMNS, $row);
                    }
                }

                $buffer = null;
                $table = null;
            }
        } finally {
            fclose($handle);
        }

        return [$participants, $projects];
    }

    private function combineRow(array $columns, array $values): array
    {
        if (count($values) !== count($columns)) {
            throw new \RuntimeException('Unexpected SQL column count while parsing legacy dump.');
        }

        return array_combine($columns, $values);
    }

    private function parseInsertStatement(string $statement): array
    {
        if (! preg_match('/^INSERT INTO `[^`]+` VALUES (.*);$/s', trim($statement), $matches)) {
            throw new \RuntimeException('Unsupported SQL INSERT statement encountered.');
        }

        return $this->parseSqlTupleList($matches[1]);
    }

    private function parseSqlTupleList(string $valueList): array
    {
        $rows = [];
        $row = [];
        $field = '';
        $insideTuple = false;
        $inString = false;
        $escapeNext = false;
        $fieldQuoted = false;

        $length = strlen($valueList);

        for ($index = 0; $index < $length; $index++) {
            $character = $valueList[$index];

            if (! $insideTuple) {
                if ($character === '(') {
                    $insideTuple = true;
                    $row = [];
                    $field = '';
                    $fieldQuoted = false;
                }

                continue;
            }

            if ($inString) {
                if ($escapeNext) {
                    $field .= match ($character) {
                        'n' => "\n",
                        'r' => "\r",
                        't' => "\t",
                        '0' => "\0",
                        default => $character,
                    };
                    $escapeNext = false;
                    continue;
                }

                if ($character === '\\') {
                    $escapeNext = true;
                    continue;
                }

                if ($character === "'") {
                    $inString = false;
                    continue;
                }

                $field .= $character;
                continue;
            }

            if ($character === "'") {
                $inString = true;
                $fieldQuoted = true;
                continue;
            }

            if ($character === ',') {
                $row[] = $this->convertSqlField($field, $fieldQuoted);
                $field = '';
                $fieldQuoted = false;
                continue;
            }

            if ($character === ')') {
                $row[] = $this->convertSqlField($field, $fieldQuoted);
                $rows[] = $row;
                $insideTuple = false;
                $field = '';
                $fieldQuoted = false;
                continue;
            }

            if ($character !== "\r" && $character !== "\n") {
                $field .= $character;
            }
        }

        return $rows;
    }

    private function convertSqlField(string $value, bool $quoted): mixed
    {
        if ($quoted) {
            return $value;
        }

        $trimmed = trim($value);

        if ($trimmed === 'NULL' || $trimmed === '') {
            return null;
        }

        if (is_numeric($trimmed)) {
            return str_contains($trimmed, '.') ? (float) $trimmed : (int) $trimmed;
        }

        return $trimmed;
    }

    private function prepareDatasets(array $legacyParticipants, array $legacyProjects): array
    {
        $professionLookup = Profession::query()
            ->pluck('name')
            ->mapWithKeys(fn (string $name) => [$this->normalizeKey($name) => $name])
            ->all();
        $professionAliases = $this->professionAliases();

        $participantStages = [];
        $participantReady = [];
        $participantReview = [];
        $organizationReadyMap = [];
        $organizationReview = [];
        $eventGroups = [];
        $missingProfessionCounts = [];
        $invalidEmailCount = 0;
        $incompleteGeographyCount = 0;
        $dateAnomalyCount = 0;
        $participantNameByLegacyId = [];

        foreach ($legacyParticipants as $legacyRow) {
            $preparedParticipant = $this->prepareParticipantRow($legacyRow, $professionLookup, $professionAliases);
            $participantStages[] = $preparedParticipant['staging'];
            $participantNameByLegacyId[(string) $legacyRow['id']] = $preparedParticipant['staging']['full_name'];

            if (! empty($preparedParticipant['flags']['invalid_email'])) {
                $invalidEmailCount++;
            }

            if (! empty($preparedParticipant['flags']['incomplete_geography'])) {
                $incompleteGeographyCount++;
            }

            if (! empty($preparedParticipant['flags']['date_anomaly'])) {
                $dateAnomalyCount++;
            }

            if ($preparedParticipant['flags']['missing_profession']) {
                $professionKey = $preparedParticipant['staging']['profession'];
                $missingProfessionCounts[$professionKey] = ($missingProfessionCounts[$professionKey] ?? 0) + 1;
            }

            if ($preparedParticipant['ready']) {
                $participantReady[] = $preparedParticipant['upload'];
            } else {
                $participantReview[] = $preparedParticipant['staging'];
            }

            if ($preparedParticipant['organization_ready']) {
                $organizationKey = implode('|', [
                    $this->normalizeKey($preparedParticipant['organization']['region']),
                    $this->normalizeKey($preparedParticipant['organization']['zone']),
                    $this->normalizeKey($preparedParticipant['organization']['woreda']),
                    $this->normalizeKey($preparedParticipant['organization']['organization']),
                ]);

                $organizationReadyMap[$organizationKey] ??= $preparedParticipant['organization'];
            } elseif ($preparedParticipant['organization_review'] !== null) {
                $organizationReview[] = $preparedParticipant['organization_review'];
            }

            $eventKey = $this->buildLegacyEventKey($preparedParticipant['event']);
            if (! isset($eventGroups[$eventKey])) {
                $eventGroups[$eventKey] = [
                    'legacy_event_key' => $eventKey,
                    'suggested_event_name' => $preparedParticipant['event']['training_title'],
                    'training_title' => $preparedParticipant['event']['training_title'],
                    'project_name' => '',
                    'who_organized_the_training' => '',
                    'subawardee_name' => '',
                    'legacy_organizer_name' => $preparedParticipant['event']['legacy_organizer_name'],
                    'region' => $preparedParticipant['event']['region'],
                    'zone' => $preparedParticipant['event']['zone'],
                    'woreda' => $preparedParticipant['event']['woreda'],
                    'host_organization_suggestion' => $preparedParticipant['event']['host_organization'],
                    'start_date' => $preparedParticipant['event']['start_date'],
                    'end_date' => $preparedParticipant['event']['end_date'],
                    'workshop_count' => $preparedParticipant['event']['workshop_count'],
                    'participant_count' => 0,
                    'legacy_participant_ids' => '',
                    'issues' => '',
                ];
            }

            $eventGroups[$eventKey]['participant_count']++;
            $eventGroups[$eventKey]['workshop_count'] = max(
                (int) $eventGroups[$eventKey]['workshop_count'],
                (int) $preparedParticipant['event']['workshop_count']
            );

            $legacyIds = array_filter(explode('|', (string) $eventGroups[$eventKey]['legacy_participant_ids']));
            $legacyIds[] = (string) $legacyRow['id'];
            $eventGroups[$eventKey]['legacy_participant_ids'] = implode('|', array_values(array_unique($legacyIds)));

            $issues = array_filter(array_map('trim', explode(';', (string) $eventGroups[$eventKey]['issues'])));
            foreach ($preparedParticipant['event']['issues'] as $issue) {
                $issues[] = $issue;
            }
            $eventGroups[$eventKey]['issues'] = implode('; ', array_values(array_unique($issues)));
        }

        $capstoneProjects = [];
        foreach ($legacyProjects as $legacyProject) {
            $participantIds = $this->decodeLegacyParticipantIds($legacyProject['participant_ids']);
            $participantNames = collect($participantIds)
                ->map(fn (string $id) => $participantNameByLegacyId[$id] ?? null)
                ->filter()
                ->values()
                ->all();

            $capstoneProjects[] = [
                'legacy_project_id' => (string) ($legacyProject['id'] ?? ''),
                'title' => $this->cleanText((string) ($legacyProject['title'] ?? '')),
                'technical_area' => $this->cleanText((string) ($legacyProject['technical_area'] ?? '')),
                'legacy_participant_ids' => implode('|', $participantIds),
                'participant_names' => implode(' | ', $participantNames),
                'coaching_visit_1_notes' => $this->cleanText((string) ($legacyProject['coaching_visit_1'] ?? ''), false),
                'coaching_visit_2_notes' => $this->cleanText((string) ($legacyProject['coaching_visit_2'] ?? ''), false),
                'coaching_visit_3_notes' => $this->cleanText((string) ($legacyProject['coaching_visit_3'] ?? ''), false),
                'status' => $this->cleanText((string) ($legacyProject['status'] ?? '')),
                'remark' => $this->cleanText((string) ($legacyProject['remark'] ?? ''), false),
            ];
        }

        $missingProfessions = collect($missingProfessionCounts)
            ->sortDesc()
            ->map(fn (int $count, string $profession) => [
                'profession' => $profession,
                'rows' => $count,
            ])
            ->values()
            ->all();

        return [
            'organization_ready' => array_values($organizationReadyMap),
            'organization_review' => $organizationReview,
            'participants_ready' => $participantReady,
            'participants_review' => $participantReview,
            'participants_staging' => $participantStages,
            'training_events_staging' => array_values($eventGroups),
            'capstone_projects_staging' => $capstoneProjects,
            'missing_professions' => $missingProfessions,
            'summary' => [
                'source_counts' => [
                    'legacy_training_participants' => count($legacyParticipants),
                    'legacy_projects' => count($legacyProjects),
                ],
                'prepared_counts' => [
                    'organization_rows_ready' => count($organizationReadyMap),
                    'organization_rows_review' => count($organizationReview),
                    'participants_ready' => count($participantReady),
                    'participants_review' => count($participantReview),
                    'training_events_staging' => count($eventGroups),
                    'capstone_projects_staging' => count($capstoneProjects),
                ],
                'data_quality' => [
                    'invalid_email_rows' => $invalidEmailCount,
                    'incomplete_geography_rows' => $incompleteGeographyCount,
                    'date_anomaly_rows' => $dateAnomalyCount,
                    'missing_profession_values' => $missingProfessions,
                ],
            ],
        ];
    }

    private function prepareParticipantRow(array $legacyRow, array $professionLookup, array $professionAliases): array
    {
        $name = $this->splitParticipantName((string) ($legacyRow['participant_name'] ?? ''));
        $email = $this->normalizeEmail((string) ($legacyRow['email'] ?? ''));
        $mobilePhone = $this->normalizePhone((string) ($legacyRow['mobile_phone'] ?? ''));
        $gender = $this->normalizeGender((string) ($legacyRow['gender'] ?? ''));

        $region = $this->normalizePlaceValue((string) ($legacyRow['region'] ?? ''));
        $zone = $this->normalizePlaceValue((string) ($legacyRow['zone'] ?? ''));
        $woreda = $this->normalizePlaceValue((string) ($legacyRow['woreda'] ?? ''));
        $organization = $this->normalizeOrganizationValue(
            (string) ($legacyRow['organization_name'] ?? ''),
            (string) ($legacyRow['org_unit'] ?? '')
        );

        if ($this->normalizeKey($region) === 'national' && ($zone === '' || $woreda === '')) {
            $zone = 'National';
            $woreda = 'National';
        }

        $professionRaw = $this->cleanText((string) ($legacyRow['profession'] ?? ''));
        $professionKey = $this->normalizeKey($professionRaw);
        $profession = $professionLookup[$professionKey]
            ?? $professionAliases[$professionKey]
            ?? $professionRaw;
        $missingProfession = $professionRaw === ''
            || (! isset($professionLookup[$professionKey]) && ! isset($professionAliases[$professionKey]));

        $startDate = $this->normalizeDate((string) ($legacyRow['training_start_date'] ?? ''));
        $endDate = $this->normalizeDate((string) ($legacyRow['training_end_date'] ?? ''));
        $dateAnomaly = $startDate !== '' && $endDate !== '' && $startDate > $endDate;

        $workshopCount = $this->legacyWorkshopCount($legacyRow);
        $invalidEmail = $email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false;
        $incompleteGeography = $region === '' || $zone === '' || $woreda === '' || $organization === '';

        $issues = [];
        if ($name['needs_review']) {
            $issues[] = $name['issue'];
        }
        if ($invalidEmail) {
            $issues[] = 'Email missing or invalid';
        }
        if ($mobilePhone === '') {
            $issues[] = 'Mobile phone missing';
        }
        if ($gender === '') {
            $issues[] = 'Gender missing or invalid';
        }
        if ($missingProfession) {
            $issues[] = 'Profession not found in current app';
        }
        if ($incompleteGeography) {
            $issues[] = 'Region, zone, woreda, or organization is incomplete';
        }
        if ($dateAnomaly) {
            $issues[] = 'Training start date is after end date';
        }

        $uploadRow = [
            'participant_code' => '',
            'first_name' => $name['first_name'],
            'father_name' => $name['father_name'],
            'grandfather_name' => $name['grandfather_name'],
            'date_of_birth' => '',
            'age' => '',
            'gender' => $gender,
            'home_phone' => '',
            'mobile_phone' => $mobilePhone,
            'email' => $email,
            'profession' => $profession,
            'region' => $region,
            'zone' => $zone,
            'woreda' => $woreda,
            'organization' => $organization,
        ];

        $stagingRow = $uploadRow + [
            'legacy_participant_id' => (string) ($legacyRow['id'] ?? ''),
            'legacy_project_id' => (string) ($legacyRow['project_id'] ?? ''),
            'full_name' => trim(implode(' ', array_filter([$name['first_name'], $name['father_name'], $name['grandfather_name']]))),
            'source_training_title' => $this->normalizeTrainingTitle((string) ($legacyRow['training_title'] ?? '')),
            'source_organizer_name' => $this->cleanText((string) ($legacyRow['organizer_name'] ?? '')),
            'issue_summary' => implode('; ', $issues),
        ];

        $organizationReady = ! $incompleteGeography;
        $organizationReview = $organizationReady ? null : [
            'legacy_participant_id' => (string) ($legacyRow['id'] ?? ''),
            'region' => $region,
            'zone' => $zone,
            'woreda' => $woreda,
            'organization' => $organization,
            'issue_summary' => 'Cannot build a clean organization hierarchy row from this participant record.',
        ];

        $organizationRow = [
            'region' => $region,
            'zone' => $zone,
            'woreda' => $woreda,
            'organization' => $organization,
        ];

        $eventIssues = [];
        if ($dateAnomaly) {
            $eventIssues[] = 'Training dates need review';
        }
        if ($region === '' || $zone === '' || $woreda === '') {
            $eventIssues[] = 'Training geography incomplete';
        }

        $eventRow = [
            'training_title' => $this->normalizeTrainingTitle((string) ($legacyRow['training_title'] ?? '')),
            'legacy_organizer_name' => $this->cleanText((string) ($legacyRow['organizer_name'] ?? '')),
            'region' => $region,
            'zone' => $zone,
            'woreda' => $woreda,
            'host_organization' => $organization,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'workshop_count' => $workshopCount,
            'issues' => $eventIssues,
        ];

        $ready = empty($issues);

        return [
            'ready' => $ready,
            'upload' => $uploadRow,
            'staging' => $stagingRow,
            'organization_ready' => $organizationReady,
            'organization' => $organizationRow,
            'organization_review' => $organizationReview,
            'event' => $eventRow,
            'flags' => [
                'invalid_email' => $invalidEmail,
                'incomplete_geography' => $incompleteGeography,
                'missing_profession' => $missingProfession,
                'date_anomaly' => $dateAnomaly,
            ],
        ];
    }

    private function splitParticipantName(string $name): array
    {
        $cleaned = $this->cleanText($name);
        $cleaned = preg_replace('/^(dr|dr\.|dr,|mr|mrs|ms|miss)\s+/i', '', $cleaned) ?? $cleaned;
        $cleaned = trim((string) preg_replace('/\s+/', ' ', $cleaned));
        $parts = array_values(array_filter(explode(' ', $cleaned)));

        $firstName = $parts[0] ?? '';
        $fatherName = $parts[1] ?? '';
        $grandfatherName = count($parts) >= 3 ? implode(' ', array_slice($parts, 2)) : '';

        $needsReview = false;
        $issue = '';
        if (count($parts) < 3) {
            $needsReview = true;
            $issue = 'Participant name does not split cleanly into first/father/grandfather';
        }

        return [
            'first_name' => $firstName,
            'father_name' => $fatherName,
            'grandfather_name' => $grandfatherName,
            'needs_review' => $needsReview,
            'issue' => $issue,
        ];
    }

    private function normalizeEmail(string $email): string
    {
        $email = trim(mb_strtolower($email));
        $email = str_replace([' ', '_'], ['', ''], $email);

        if ($email === '-' || $email === '') {
            return '';
        }

        $parts = explode('@', $email);
        if (count($parts) === 2) {
            $parts[1] = str_replace(',', '.', $parts[1]);
            $email = implode('@', $parts);
        }

        return $email;
    }

    private function normalizePhone(string $phone): string
    {
        $phone = trim($phone);
        if ($phone === '' || $phone === '-') {
            return '';
        }

        return preg_replace('/[^0-9+]/', '', $phone) ?? '';
    }

    private function normalizeGender(string $gender): string
    {
        $normalized = $this->normalizeKey($gender);

        return match ($normalized) {
            'male', 'm' => 'Male',
            'female', 'f' => 'Female',
            default => '',
        };
    }

    private function normalizeTrainingTitle(string $value): string
    {
        $cleaned = $this->cleanText($value);

        return $cleaned === '' ? '' : Str::title(mb_strtolower($cleaned));
    }

    private function normalizePlaceValue(string $value): string
    {
        $cleaned = $this->cleanText($value);
        $key = $this->normalizeKey($cleaned);

        if (in_array($key, ['', '-', '--', 'null'], true)) {
            return '';
        }

        return $cleaned;
    }

    private function normalizeOrganizationValue(string $organizationName, string $orgUnit): string
    {
        $primary = $this->normalizePlaceValue($organizationName);
        if ($primary !== '') {
            return $primary;
        }

        return $this->normalizePlaceValue($orgUnit);
    }

    private function normalizeDate(string $value): string
    {
        $value = trim($value);
        if ($value === '' || $value === '-') {
            return '';
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return $value;
        }
    }

    private function cleanText(string $value, bool $collapseWhitespace = true): string
    {
        $value = str_replace("\xc2\xa0", ' ', $value);
        $value = trim($value);

        if ($collapseWhitespace) {
            $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        }

        return trim($value);
    }

    private function normalizeKey(string $value): string
    {
        return mb_strtolower($this->cleanText($value));
    }

    private function legacyWorkshopCount(array $legacyRow): int
    {
        $count = 0;

        for ($number = 1; $number <= 4; $number++) {
            if ($legacyRow['pre_score_w'.$number] !== null || $legacyRow['post_score_w'.$number] !== null) {
                $count = $number;
            }
        }

        return max(1, $count);
    }

    private function buildLegacyEventKey(array $event): string
    {
        return implode('|', [
            $this->normalizeKey($event['training_title']),
            $this->normalizeKey($event['legacy_organizer_name']),
            $this->normalizeKey($event['region']),
            $this->normalizeKey($event['zone']),
            $this->normalizeKey($event['woreda']),
            $event['start_date'],
            $event['end_date'],
        ]);
    }

    private function decodeLegacyParticipantIds(mixed $rawValue): array
    {
        if (! is_string($rawValue) || trim($rawValue) === '') {
            return [];
        }

        $decoded = json_decode($rawValue, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map(fn ($id) => trim((string) $id), $decoded)));
        }

        preg_match_all('/\d+/', $rawValue, $matches);

        return array_values(array_unique($matches[0] ?? []));
    }

    private function professionAliases(): array
    {
        return [
            'bsc nurse' => 'Nurse',
            'bsc' => 'Nurse',
            'bsc nurse ' => 'Nurse',
            'clinical nurse' => 'Nurse',
            'c/nurse' => 'Nurse',
            'c/n' => 'Nurse',
            'nurse' => 'Nurse',
            'public health officer' => 'Public Health/Program related',
            'general mph' => 'Public Health/Program related',
            'mph' => 'Public Health/Program related',
            'mph in nutrition' => 'Public Health/Program related',
            'mph-nutrition' => 'Public Health/Program related',
            'mch focal' => 'Public Health/Program related',
            'mch' => 'Public Health/Program related',
            'ho' => 'Health Officer',
            'health officer' => 'Health Officer',
            'pharmacy' => 'Pharmacy professional',
            'pharmacy technician' => 'Pharmacy professional',
            'pharmacy profesional' => 'Pharmacy professional',
            'medical doctor' => 'Physician',
            'md' => 'Physician',
            'clinical midwifery' => 'Midwife',
            'bsc midwifery' => 'Midwife',
            'bsc midwif' => 'Midwife',
            'midwifer' => 'Midwife',
            'laboratory' => 'Laboratory Professional',
            'environmental health officer' => 'Environmental health related',
            'enviromental health officer' => 'Environmental health related',
            'opd' => 'Administration/Management related',
            'hrm' => 'Administration/Management related',
            'master of economics' => 'Administration/Management related',
            'mba' => 'Administration/Management related',
            'ba business manegement' => 'Administration/Management related',
            'ba in management' => 'Administration/Management related',
            'hit' => 'SI related (M&E, Surveillance, IT)',
            'health information technician' => 'SI related (M&E, Surveillance, IT)',
            'bsc hit' => 'SI related (M&E, Surveillance, IT)',
            'ict' => 'SI related (M&E, Surveillance, IT)',
            'bsc human nutrition' => 'Public Health/Program related',
        ];
    }

    private function writePreparedFiles(string $outputDirectory, array $prepared): array
    {
        $files = [];

        $files['organizations_ready_csv'] = $this->writeCsv(
            $outputDirectory.DIRECTORY_SEPARATOR.'organizations_ready.csv',
            ['region', 'zone', 'woreda', 'organization'],
            $prepared['organization_ready']
        );

        $files['organizations_review_csv'] = $this->writeCsv(
            $outputDirectory.DIRECTORY_SEPARATOR.'organizations_review.csv',
            ['legacy_participant_id', 'region', 'zone', 'woreda', 'organization', 'issue_summary'],
            $prepared['organization_review']
        );

        $files['participants_ready_csv'] = $this->writeCsv(
            $outputDirectory.DIRECTORY_SEPARATOR.'participants_ready.csv',
            ['participant_code', 'first_name', 'father_name', 'grandfather_name', 'date_of_birth', 'age', 'gender', 'home_phone', 'mobile_phone', 'email', 'profession', 'region', 'zone', 'woreda', 'organization'],
            $prepared['participants_ready']
        );

        $files['participants_review_csv'] = $this->writeCsv(
            $outputDirectory.DIRECTORY_SEPARATOR.'participants_review.csv',
            ['legacy_participant_id', 'legacy_project_id', 'full_name', 'first_name', 'father_name', 'grandfather_name', 'date_of_birth', 'age', 'gender', 'home_phone', 'mobile_phone', 'email', 'profession', 'region', 'zone', 'woreda', 'organization', 'source_training_title', 'source_organizer_name', 'issue_summary'],
            $prepared['participants_review']
        );

        $files['participants_staging_csv'] = $this->writeCsv(
            $outputDirectory.DIRECTORY_SEPARATOR.'participants_staging.csv',
            ['legacy_participant_id', 'legacy_project_id', 'full_name', 'participant_code', 'first_name', 'father_name', 'grandfather_name', 'date_of_birth', 'age', 'gender', 'home_phone', 'mobile_phone', 'email', 'profession', 'region', 'zone', 'woreda', 'organization', 'source_training_title', 'source_organizer_name', 'issue_summary'],
            $prepared['participants_staging']
        );

        $files['training_events_staging_csv'] = $this->writeCsv(
            $outputDirectory.DIRECTORY_SEPARATOR.'training_events_staging.csv',
            ['legacy_event_key', 'suggested_event_name', 'training_title', 'project_name', 'who_organized_the_training', 'subawardee_name', 'legacy_organizer_name', 'region', 'zone', 'woreda', 'host_organization_suggestion', 'start_date', 'end_date', 'workshop_count', 'participant_count', 'legacy_participant_ids', 'issues'],
            $prepared['training_events_staging']
        );

        $files['capstone_projects_staging_csv'] = $this->writeCsv(
            $outputDirectory.DIRECTORY_SEPARATOR.'capstone_projects_staging.csv',
            ['legacy_project_id', 'title', 'technical_area', 'legacy_participant_ids', 'participant_names', 'coaching_visit_1_notes', 'coaching_visit_2_notes', 'coaching_visit_3_notes', 'status', 'remark'],
            $prepared['capstone_projects_staging']
        );

        $files['missing_professions_csv'] = $this->writeCsv(
            $outputDirectory.DIRECTORY_SEPARATOR.'missing_professions.csv',
            ['profession', 'rows'],
            $prepared['missing_professions']
        );

        $summaryPath = $outputDirectory.DIRECTORY_SEPARATOR.'README.md';
        File::put($summaryPath, $this->buildSummaryMarkdown($prepared['summary']));
        $files['readme'] = $summaryPath;

        return $files;
    }

    private function writeCsv(string $path, array $headers, array $rows): string
    {
        $handle = fopen($path, 'w');
        if ($handle === false) {
            throw new \RuntimeException('Unable to write CSV file: '.$path);
        }

        try {
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                $line = [];
                foreach ($headers as $header) {
                    $line[] = $row[$header] ?? '';
                }
                fputcsv($handle, $line);
            }
        } finally {
            fclose($handle);
        }

        return $path;
    }

    private function buildSummaryMarkdown(array $summary): string
    {
        $missingProfessionLines = collect($summary['data_quality']['missing_profession_values'] ?? [])
            ->take(20)
            ->map(fn (array $row) => '- '.$row['profession'].' ('.$row['rows'].' rows)')
            ->implode("\n");

        if ($missingProfessionLines === '') {
            $missingProfessionLines = '- None';
        }

        return implode("\n", [
            '# Legacy HIL SQL Preparation',
            '',
            '## Source Counts',
            '- Legacy participant rows: '.$summary['source_counts']['legacy_training_participants'],
            '- Legacy capstone project rows: '.$summary['source_counts']['legacy_projects'],
            '',
            '## Prepared Counts',
            '- Organization rows ready for hierarchy import: '.$summary['prepared_counts']['organization_rows_ready'],
            '- Organization rows needing review: '.$summary['prepared_counts']['organization_rows_review'],
            '- Participant rows ready for CSV import: '.$summary['prepared_counts']['participants_ready'],
            '- Participant rows needing review: '.$summary['prepared_counts']['participants_review'],
            '- Training event staging rows: '.$summary['prepared_counts']['training_events_staging'],
            '- Capstone project staging rows: '.$summary['prepared_counts']['capstone_projects_staging'],
            '',
            '## Data Quality Flags',
            '- Invalid email rows: '.$summary['data_quality']['invalid_email_rows'],
            '- Incomplete geography rows: '.$summary['data_quality']['incomplete_geography_rows'],
            '- Date anomaly rows: '.$summary['data_quality']['date_anomaly_rows'],
            '- Missing profession values:',
            $missingProfessionLines,
            '',
            '## Recommended Import Order',
            '1. Import `organizations_ready.csv` with `php artisan organizations:import-hierarchy <path>`.',
            '2. Review and fix `participants_review.csv` until the unresolved rows are cleared.',
            '3. Import `participants_ready.csv` from the Participants admin import screen.',
            '4. Use `training_events_staging.csv` to create or script training-event imports.',
            '5. Use `capstone_projects_staging.csv` for project-note migration after participant reconciliation.',
            '',
        ]);
    }
}
