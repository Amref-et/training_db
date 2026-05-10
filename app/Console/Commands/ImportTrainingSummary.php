<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Participant;
use App\Models\Training;
use App\Models\TrainingEvent;
use App\Models\TrainingOrganizer;
use App\Models\TrainingCategory;
use App\Models\Organization;
use App\Models\Region;
use App\Models\TrainingEventParticipant;
use Carbon\Carbon;

class ImportTrainingSummary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-training-summary {--file= : Path to the CSV file to import}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import training summary from CSV file';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = $this->option('file') ?: 'D:/Training summary_6MAY2025(Sheet1).csv';
        if (!file_exists($filePath)) {
            $this->error('CSV file not found at ' . $filePath);
            return;
        }

        $handle = fopen($filePath, 'r');
        $header = fgetcsv($handle); // Skip header
        $count = 0;
        $errors = [];

        while (($row = fgetcsv($handle)) !== false) {
            try {
                $this->processRow($row);
                $count++;
            } catch (\Exception $e) {
                $errors[] = "Row " . ($count + 2) . ": " . $e->getMessage();
            }
        }

        fclose($handle);
        $this->info("Imported $count rows successfully.");
        if ($errors) {
            $this->error("Errors encountered:");
            foreach ($errors as $error) {
                $this->error($error);
            }
        }
    }

    private function processRow($row)
    {
        // Extract fields based on CSV column positions
        $regionName = trim($row[0] ?? '');
        $trainingTitle = trim($row[1] ?? '');
        $startDateStr = trim($row[2] ?? '');
        $categoryName = trim($row[3] ?? '');
        $endDateStr = trim($row[4] ?? '');
        $organizerName = trim($row[5] ?? '');
        $participantName = trim($row[6] ?? '');
        $participantId = trim($row[7] ?? '');
        $gender = trim($row[8] ?? '');
        $mobile = trim($row[9] ?? '');
        $email = trim($row[10] ?? '');
        $profession = trim($row[11] ?? '');
        $partOrgName = trim($row[12] ?? '');
        $partOrgPhone = trim($row[13] ?? '');
        $partOrgFax = trim($row[14] ?? '');
        $partOrgCategory = trim($row[15] ?? '');
        $partOrgType = trim($row[16] ?? '');
        $preScore = trim($row[17] ?? '');
        $midScore = trim($row[18] ?? '');
        $postScore = trim($row[19] ?? '');
        $trainerName = trim($row[20] ?? '');
        $comments = trim($row[21] ?? '');

        // Convert dates
        $startDate = $this->parseDate($startDateStr);
        $endDate = $this->parseDate($endDateStr);

        // Find region
        $region = Region::where('name', $regionName)->first();
        if (!$region) {
            throw new \Exception("Region not found: $regionName");
        }

        // Find or create training category
        $category = TrainingCategory::firstOrCreate(['name' => $categoryName]);

        // Find or create training
        $training = Training::firstOrCreate([
            'title' => $trainingTitle,
            'training_category_id' => $category->id,
        ]);

        // Find or create training organizer
        $organizer = TrainingOrganizer::firstOrCreate(['title' => $organizerName]);

        // Find or create training event
        $trainingEvent = TrainingEvent::firstOrCreate([
            'training_id' => $training->id,
            'training_organizer_id' => $organizer->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ], [
            'status' => 'completed',
        ]);

        // Find or create participant organization
        $partOrg = Organization::firstOrCreate(['name' => $partOrgName], [
            'region_id' => $region->id,
            'category' => $partOrgCategory ?: null,
            'type' => $partOrgType ?: null,
            'phone' => $partOrgPhone ?: null,
            'fax' => $partOrgFax ?: null,
        ]);

        // Parse participant name
        $names = explode(' ', $participantName);
        $firstName = $names[0] ?? '';
        $fatherName = $names[1] ?? '';
        $grandfatherName = implode(' ', array_slice($names, 2));

        // Find or create participant
        $participant = Participant::where('first_name', $firstName)
            ->where('father_name', $fatherName)
            ->where('grandfather_name', $grandfatherName)
            ->where('mobile_phone', $mobile ?: null)
            ->where('email', $email ?: null)
            ->first();

        if (!$participant) {
            $participant = Participant::create([
                'first_name' => $firstName,
                'father_name' => $fatherName,
                'grandfather_name' => $grandfatherName,
                'name' => $participantName,
                'gender' => $gender,
                'mobile_phone' => $mobile ?: null,
                'email' => $email ?: null,
                'profession' => $profession ?: null,
                'organization_id' => $partOrg->id,
                'region_id' => $region->id,
                'participant_code' => $this->generateParticipantCode($firstName, $fatherName, $grandfatherName),
            ]);
        }

        // Enroll participant
        TrainingEventParticipant::firstOrCreate([
            'training_event_id' => $trainingEvent->id,
            'participant_id' => $participant->id,
        ], [
            'pre_test_score' => $preScore ?: null,
            'mid_test_score' => $midScore ?: null,
            'post_test_score' => $postScore ?: null,
            'comments' => $comments ?: null,
        ]);
    }

    private function parseDate($dateStr)
    {
        if (empty($dateStr)) {
            throw new \Exception('Date is required');
        }
        return Carbon::createFromFormat('m/d/Y', $dateStr)->format('Y-m-d');
    }

    private function generateParticipantCode($first, $father, $grandfather)
    {
        $initials = strtoupper(substr($first, 0, 1) . substr($father, 0, 1) . substr($grandfather, 0, 1));
        $random = rand(100000, 999999);
        return $initials . 'X' . $random;
    }
}
