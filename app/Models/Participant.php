<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Participant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'participant_code',
        'first_name',
        'father_name',
        'grandfather_name',
        'date_of_birth',
        'age',
        'region_id',
        'zone_id',
        'woreda_id',
        'organization_id',
        'gender',
        'home_phone',
        'mobile_phone',
        'email',
        'profession',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'age' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $participant): void {
            $participant->first_name = self::cleanNamePart($participant->first_name);
            $participant->father_name = self::cleanNamePart($participant->father_name);
            $participant->grandfather_name = self::cleanNamePart($participant->grandfather_name);

            $participant->name = trim(implode(' ', array_values(array_filter([
                $participant->first_name,
                $participant->father_name,
                $participant->grandfather_name,
            ]))));

            $referenceDate = self::referenceDate();

            if (! empty($participant->date_of_birth)) {
                $dob = Carbon::parse($participant->date_of_birth)->startOfDay();
                $participant->age = self::calculateAgeFromDateOfBirth($dob, $referenceDate);
            } elseif ($participant->age !== null && $participant->age !== '') {
                $age = max(0, (int) $participant->age);
                $participant->age = $age;
                $participant->date_of_birth = Carbon::create($referenceDate->year - $age, 7, 1)->toDateString();
            }

            if (empty($participant->participant_code)) {
                $participant->participant_code = self::generateUniqueParticipantCode($participant);
            }
        });
    }

    private static function cleanNamePart(mixed $value): ?string
    {
        $name = trim((string) $value);

        return $name === '' ? null : $name;
    }

    private static function referenceDate(): Carbon
    {
        return Carbon::create(now()->year, 7, 1)->startOfDay();
    }

    private static function calculateAgeFromDateOfBirth(Carbon $dob, Carbon $referenceDate): int
    {
        $age = $dob->diffInYears($referenceDate);

        return max(0, $age);
    }

    private static function generateUniqueParticipantCode(self $participant): string
    {
        $base = self::participantCodeBase($participant);
        $code = $base;
        $counter = 1;

        while (self::query()
            ->where('participant_code', $code)
            ->when($participant->exists, fn ($query) => $query->whereKeyNot($participant->getKey()))
            ->exists()) {
            $code = $base.str_pad((string) $counter, 2, '0', STR_PAD_LEFT);
            $counter++;
        }

        return $code;
    }

    private static function participantCodeBase(self $participant): string
    {
        $first = self::initialForCode($participant->first_name);
        $father = self::initialForCode($participant->father_name);
        $grandfather = self::initialForCode($participant->grandfather_name);
        [$year, $month] = self::datePartsForCode($participant->date_of_birth);
        $last4 = self::last4ForCode($participant->mobile_phone);

        return $first.$father.$grandfather.$year.$month.$last4;
    }

    private static function initialForCode(mixed $value): string
    {
        $text = trim((string) $value);

        if ($text === '') {
            return 'X';
        }

        return strtoupper(mb_substr($text, 0, 1));
    }

    private static function datePartsForCode(mixed $dateOfBirth): array
    {
        if (empty($dateOfBirth)) {
            return ['0000', '00'];
        }

        try {
            $dob = $dateOfBirth instanceof Carbon
                ? $dateOfBirth->copy()
                : Carbon::parse((string) $dateOfBirth);
        } catch (\Throwable) {
            return ['0000', '00'];
        }

        return [$dob->format('Y'), $dob->format('m')];
    }

    private static function last4ForCode(mixed $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        $tail = substr($digits, -4);

        return str_pad((string) $tail, 4, '0', STR_PAD_LEFT);
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function woreda()
    {
        return $this->belongsTo(Woreda::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function professionDefinition()
    {
        return $this->belongsTo(Profession::class, 'profession', 'name');
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function trainingEvents()
    {
        return $this->hasMany(TrainingEvent::class);
    }

    public function trainingEventEnrollments()
    {
        return $this->hasMany(TrainingEventParticipant::class);
    }

    public function enrolledTrainingEvents()
    {
        return $this->belongsToMany(TrainingEvent::class, 'training_event_participants')
            ->withPivot('final_score')
            ->withTimestamps();
    }
}
