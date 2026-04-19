<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\ParticipantResource;
use App\Models\Participant;
use Illuminate\Http\Request;

class ParticipantsController extends ApiController
{
    public function index(Request $request)
    {
        $this->ensurePermission($request, 'participants');

        $query = Participant::query()
            ->with(['region', 'zone', 'woreda', 'organization'])
            ->orderBy('name');

        $this->applySearch($query, $request, [
            'participant_code',
            'first_name',
            'father_name',
            'grandfather_name',
            'name',
            'email',
            'profession',
            'mobile_phone',
            'home_phone',
        ]);

        foreach (['region_id', 'zone_id', 'woreda_id', 'organization_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, (int) $request->integer($filter));
            }
        }

        if ($request->filled('gender')) {
            $query->where('gender', (string) $request->query('gender'));
        }

        return $this->paginatedResponse($query->paginate($this->perPage($request)), ParticipantResource::class);
    }

    public function store(Request $request)
    {
        $this->ensurePermission($request, 'participants');

        $participant = Participant::query()->create($this->validated($request))
            ->load(['region', 'zone', 'woreda', 'organization']);

        return $this->itemResponse($participant, ParticipantResource::class, 201);
    }

    public function show(Request $request, Participant $participant)
    {
        $this->ensurePermission($request, 'participants');

        return $this->itemResponse($participant->load(['region', 'zone', 'woreda', 'organization']), ParticipantResource::class);
    }

    public function update(Request $request, Participant $participant)
    {
        $this->ensurePermission($request, 'participants');

        $participant->update($this->validated($request, $participant));

        return $this->itemResponse($participant->fresh()->load(['region', 'zone', 'woreda', 'organization']), ParticipantResource::class);
    }

    public function destroy(Request $request, Participant $participant)
    {
        $this->ensurePermission($request, 'participants');

        $participant->delete();

        return $this->messageResponse('Participant deleted.');
    }

    private function validated(Request $request, ?Participant $participant = null): array
    {
        return $request->validate([
            'first_name' => 'required|string|max:255',
            'father_name' => 'required|string|max:255',
            'grandfather_name' => 'required|string|max:255',
            'date_of_birth' => 'nullable|date|required_without:age',
            'age' => 'nullable|integer|min:0|max:120|required_without:date_of_birth',
            'region_id' => 'required|exists:regions,id',
            'zone_id' => 'required|exists:zones,id',
            'woreda_id' => 'required|exists:woredas,id',
            'organization_id' => 'required|exists:organizations,id',
            'gender' => 'required|in:male,female',
            'home_phone' => 'nullable|string|max:30',
            'mobile_phone' => 'required|string|max:30',
            'email' => 'required|email|max:255|unique:participants,email,'.($participant?->id ?? 'NULL').',id',
            'profession' => 'required|string|max:255|exists:professions,name',
        ]);
    }
}
