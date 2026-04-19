<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\ProjectResource;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProjectsController extends ApiController
{
    public function index(Request $request)
    {
        $this->ensurePermission($request, 'projects');

        $query = Project::query()->with(['participant', 'participants', 'projectCategory'])->orderBy('title');
        $this->applySearch($query, $request, ['title']);

        if ($request->filled('project_category_id')) {
            $query->where('project_category_id', (int) $request->integer('project_category_id'));
        }

        if ($request->filled('participant_id')) {
            $participantId = (int) $request->integer('participant_id');
            $query->where(function ($inner) use ($participantId) {
                $inner->where('participant_id', $participantId)
                    ->orWhereHas('participants', fn ($relation) => $relation->where('participants.id', $participantId));
            });
        }

        return $this->paginatedResponse($query->paginate($this->perPage($request)), ProjectResource::class);
    }

    public function store(Request $request)
    {
        $this->ensurePermission($request, 'projects');

        [$data, $participantIds] = $this->validated($request);
        $project = Project::query()->create($data);
        $project->participants()->sync($participantIds);

        return $this->itemResponse($project->fresh()->load(['participant', 'participants', 'projectCategory']), ProjectResource::class, 201);
    }

    public function show(Request $request, Project $project)
    {
        $this->ensurePermission($request, 'projects');

        return $this->itemResponse($project->load(['participant', 'participants', 'projectCategory']), ProjectResource::class);
    }

    public function update(Request $request, Project $project)
    {
        $this->ensurePermission($request, 'projects');

        [$data, $participantIds] = $this->validated($request, $project);
        $project->update($data);
        $project->participants()->sync($participantIds);

        return $this->itemResponse($project->fresh()->load(['participant', 'participants', 'projectCategory']), ProjectResource::class);
    }

    public function destroy(Request $request, Project $project)
    {
        $this->ensurePermission($request, 'projects');

        if (! empty($project->project_file) && Storage::disk('public')->exists($project->project_file)) {
            Storage::disk('public')->delete($project->project_file);
        }

        $project->delete();

        return $this->messageResponse('Project deleted.');
    }

    private function validated(Request $request, ?Project $project = null): array
    {
        $data = $request->validate([
            'project_category_id' => 'required|exists:project_categories,id',
            'participant_ids' => 'required|array|min:1',
            'participant_ids.*' => 'required|exists:participants,id',
            'title' => 'required|string|max:255',
            'coaching_visit_1' => 'nullable|date',
            'coaching_visit_1_notes' => 'nullable|string',
            'coaching_visit_2' => 'nullable|date',
            'coaching_visit_2_notes' => 'nullable|string',
            'coaching_visit_3' => 'nullable|date',
            'coaching_visit_3_notes' => 'nullable|string',
            'project_file' => 'nullable|file|max:10240|mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg',
        ]);

        $participantIds = collect($data['participant_ids'])->map(fn ($id) => (int) $id)->unique()->values()->all();
        unset($data['participant_ids']);
        $data['participant_id'] = $participantIds[0] ?? null;

        if ($request->hasFile('project_file')) {
            if ($project && ! empty($project->project_file) && Storage::disk('public')->exists($project->project_file)) {
                Storage::disk('public')->delete($project->project_file);
            }

            $data['project_file'] = $request->file('project_file')->store('project-files', 'public');
        } elseif ($project) {
            unset($data['project_file']);
        }

        return [$data, $participantIds];
    }
}
