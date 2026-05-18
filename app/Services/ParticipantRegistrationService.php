<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Participant;
use App\Models\Profession;
use App\Models\Region;
use App\Models\Woreda;
use App\Models\Zone;
use App\Support\ResourceRegistry;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ParticipantRegistrationService
{
    public function formOptions(): array
    {
        $professionQuery = Profession::query();

        if (Schema::hasColumn('professions', 'is_active')) {
            $professionQuery->where('is_active', true);
        }

        if (Schema::hasColumn('professions', 'sort_order')) {
            $professionQuery->orderBy('sort_order');
        }

        return [
            'regions' => Region::query()->orderBy('name')->get(['id', 'name']),
            'zones' => Zone::query()->orderBy('name')->get(['id', 'name', 'region_id']),
            'woredas' => Woreda::query()->orderBy('name')->get(['id', 'name', 'region_id', 'zone_id']),
            'professions' => $professionQuery->orderBy('name')->get(['name']),
        ];
    }

    public function selectedOrganizationOption(mixed $organizationId): ?array
    {
        $organizationId = $this->nullableInt($organizationId);

        if ($organizationId === null) {
            return null;
        }

        $organization = Organization::query()
            ->select(['id', 'name', 'region_id', 'zone_id', 'woreda_id'])
            ->with(['region'])
            ->find($organizationId);

        if (! $organization) {
            return null;
        }

        return $this->formatOrganizationOption($organization);
    }

    public function organizationOptions(?string $queryTerm, mixed $selectedId, mixed $regionId, mixed $zoneId, mixed $woredaId): array
    {
        $queryTerm = trim((string) $queryTerm);
        $selectedId = $this->nullableInt($selectedId);
        $regionId = $this->nullableInt($regionId);
        $zoneId = $this->nullableInt($zoneId);
        $woredaId = $this->nullableInt($woredaId);

        $query = Organization::query()
            ->select(['id', 'name', 'region_id', 'zone_id', 'woreda_id'])
            ->with(['region']);

        if ($regionId !== null) {
            $query->where('region_id', $regionId);
        }

        if ($zoneId !== null) {
            $query->where('zone_id', $zoneId);
        }

        if ($woredaId !== null) {
            $query->where('woreda_id', $woredaId);
        }

        if ($queryTerm !== '') {
            $query->where('name', 'like', '%'.$queryTerm.'%');
        }

        $limit = $queryTerm !== '' ? 50 : (($regionId !== null || $zoneId !== null || $woredaId !== null) ? 50 : 20);

        $options = $query
            ->orderBy('name')
            ->limit($limit)
            ->get();

        if ($selectedId !== null && ! $options->contains('id', $selectedId)) {
            $selected = Organization::query()
                ->select(['id', 'name', 'region_id', 'zone_id', 'woreda_id'])
                ->with(['region'])
                ->find($selectedId);

            if ($selected) {
                $options->prepend($selected);
            }
        }

        return $options
            ->unique('id')
            ->values()
            ->map(fn (Organization $organization) => $this->formatOrganizationOption($organization))
            ->all();
    }

    public function validateAndPrepare(array $input, ?Participant $participant = null, bool $enforceUniqueEmail = true): array
    {
        $data = Validator::make($input, $this->validationRules($participant, $enforceUniqueEmail), $this->validationMessages())->validate();

        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $data['email'] = $email === '' ? null : $email;
        $data['gender'] = mb_strtolower(trim((string) ($data['gender'] ?? '')));
        $data['home_phone'] = $this->blankToNull($data['home_phone'] ?? null);
        $data['mobile_phone'] = trim((string) ($data['mobile_phone'] ?? ''));
        $data['date_of_birth'] = $this->blankToNull($data['date_of_birth'] ?? null);
        $data['age'] = $this->blankToNull($data['age'] ?? null);

        if ($data['age'] !== null) {
            $data['age'] = (int) $data['age'];
        }

        $this->applyHierarchyConstraints($data);

        return $data;
    }

    public function create(array $data): Participant
    {
        $data['participant_code'] = Participant::generatedParticipantCode($data);

        return Participant::query()->create($data);
    }

    public function existingParticipantForGeneratedCode(array $data): ?Participant
    {
        return Participant::query()
            ->where('participant_code', Participant::generatedParticipantCode($data))
            ->first();
    }

    public function ensureEmailIsAvailable(mixed $email, ?Participant $participant = null): void
    {
        $email = mb_strtolower(trim((string) $email));

        if ($email === '') {
            return;
        }

        $exists = Participant::query()
            ->where('email', $email)
            ->when($participant, fn ($query) => $query->whereKeyNot($participant->getKey()))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'email' => 'The email has already been taken.',
            ]);
        }
    }

    public function formInput(Participant $participant): array
    {
        return [
            'first_name' => $participant->first_name,
            'father_name' => $participant->father_name,
            'grandfather_name' => $participant->grandfather_name,
            'date_of_birth' => $participant->date_of_birth?->toDateString(),
            'age' => $participant->age,
            'region_id' => $participant->region_id,
            'zone_id' => $participant->zone_id,
            'woreda_id' => $participant->woreda_id,
            'organization_id' => $participant->organization_id,
            'gender' => $participant->gender,
            'home_phone' => $participant->home_phone,
            'mobile_phone' => $participant->mobile_phone,
            'email' => $participant->email,
            'profession' => $participant->profession,
        ];
    }

    private function validationRules(?Participant $participant = null, bool $enforceUniqueEmail = true): array
    {
        $config = ResourceRegistry::get('participants');
        $id = $participant?->getKey() ?? 'NULL';

        return collect($config['rules'])
            ->map(fn (string $rule) => str_replace('{{id}}', (string) $id, $rule))
            ->map(function (string $rule, string $field) use ($enforceUniqueEmail): string {
                if ($field !== 'email' || $enforceUniqueEmail) {
                    return $rule;
                }

                return collect(explode('|', $rule))
                    ->reject(fn (string $rulePart) => str_starts_with($rulePart, 'unique:participants,email'))
                    ->implode('|');
            })
            ->all();
    }

    private function validationMessages(): array
    {
        return [
            'date_of_birth.before_or_equal' => 'Date of birth cannot be in the future.',
            'age.integer' => 'Age must be a whole number.',
            'age.min' => 'Age must be between 0 and 120.',
            'age.max' => 'Age must be between 0 and 120.',
            'home_phone.regex' => 'Home phone must be a valid phone number with 7 to 15 digits.',
            'mobile_phone.regex' => 'Mobile phone must be a valid phone number with 7 to 15 digits.',
        ];
    }

    private function applyHierarchyConstraints(array &$data): void
    {
        $messages = [];

        $regionId = $this->nullableInt($data['region_id'] ?? null);
        $zoneId = $this->nullableInt($data['zone_id'] ?? null);
        $woredaId = $this->nullableInt($data['woreda_id'] ?? null);
        $organizationId = $this->nullableInt($data['organization_id'] ?? null);

        $zone = $zoneId ? Zone::query()->find($zoneId) : null;
        $woreda = $woredaId ? Woreda::query()->find($woredaId) : null;
        $organization = $organizationId ? Organization::query()->find($organizationId) : null;

        if ($woreda && $woreda->zone_id === null) {
            $messages['woreda_id'] = 'Selected Woreda is not linked to a Zone.';
        } elseif (! $zone && $woreda && $woreda->zone_id !== null) {
            $zone = Zone::query()->find((int) $woreda->zone_id);
        }

        if (! $zone && $organization && $organization->zone_id !== null) {
            $zone = Zone::query()->find((int) $organization->zone_id);
        }

        if ($zone && $zone->region_id === null) {
            $messages['zone_id'] = 'Selected Zone is not linked to a Region.';
        } elseif ($zone && $zone->region_id !== null) {
            if ($regionId !== null && (int) $zone->region_id !== $regionId) {
                $messages['region_id'] = 'Selected Region does not match the Zone\'s Region.';
            }

            $regionId = (int) $zone->region_id;
        }

        if ($woreda && $zone && (int) $woreda->zone_id !== (int) $zone->id) {
            $messages['woreda_id'] = 'Selected Woreda does not belong to the selected Zone.';
        }

        if ($woreda && $regionId !== null && (int) $woreda->region_id !== $regionId) {
            $messages['woreda_id'] = 'Selected Woreda does not belong to the selected Region.';
        }

        if ($organization && $organization->region_id !== null && $regionId !== null && (int) $organization->region_id !== $regionId) {
            $messages['organization_id'] = 'Selected Organization does not belong to the selected Region.';
        }

        if ($organization && $organization->zone_id !== null && $zone && (int) $organization->zone_id !== (int) $zone->id) {
            $messages['organization_id'] = 'Selected Organization does not belong to the selected Zone.';
        }

        if ($organization && $organization->woreda_id !== null && $woreda && (int) $organization->woreda_id !== (int) $woreda->id) {
            $messages['organization_id'] = 'Selected Organization does not belong to the selected Woreda.';
        }

        $data['region_id'] = $regionId;
        $data['zone_id'] = $zone?->id;

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }

    private function formatOrganizationOption(Organization $organization): array
    {
        $orgName = $organization->name ?? '';
        $regionName = $organization->region?->name ?? '';
        $displayLabel = $orgName;

        if ($orgName && $regionName) {
            $displayLabel = $orgName.' - '.$regionName.' region';
        }

        return [
            'value' => $organization->id,
            'label' => $displayLabel,
            'region_id' => $organization->region_id,
            'zone_id' => $organization->zone_id,
            'woreda_id' => $organization->woreda_id,
        ];
    }

    private function blankToNull(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }
}
