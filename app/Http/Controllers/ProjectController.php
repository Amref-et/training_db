<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProjectController extends Controller
{
    public function index()
    {
        return Project::with('participant')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'participant_id' => 'required|exists:participants,id',
            'title' => 'required|string|max:255',
            'coaching_visit_1' => 'nullable|date',
            'coaching_visit_1_notes' => 'nullable|string',
            'coaching_visit_2' => 'nullable|date',
            'coaching_visit_2_notes' => 'nullable|string',
            'coaching_visit_3' => 'nullable|date',
            'coaching_visit_3_notes' => 'nullable|string',
            'project_file' => 'nullable|file|max:10240|mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg',
        ]);

        if ($request->hasFile('project_file')) {
            $data['project_file'] = $request->file('project_file')->store('project-files', 'public');
        }

        return Project::create($data);
    }

    public function show(Project $project)
    {
        return $project->load('participant');
    }

    public function update(Request $request, Project $project)
    {
        $data = $request->validate([
            'participant_id' => 'required|exists:participants,id',
            'title' => 'required|string|max:255',
            'coaching_visit_1' => 'nullable|date',
            'coaching_visit_1_notes' => 'nullable|string',
            'coaching_visit_2' => 'nullable|date',
            'coaching_visit_2_notes' => 'nullable|string',
            'coaching_visit_3' => 'nullable|date',
            'coaching_visit_3_notes' => 'nullable|string',
            'project_file' => 'nullable|file|max:10240|mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg',
        ]);

        if ($request->hasFile('project_file')) {
            if (! empty($project->project_file) && Storage::disk('public')->exists($project->project_file)) {
                Storage::disk('public')->delete($project->project_file);
            }

            $data['project_file'] = $request->file('project_file')->store('project-files', 'public');
        } else {
            unset($data['project_file']);
        }

        $project->update($data);

        return $project;
    }

    public function destroy(Project $project)
    {
        if (! empty($project->project_file) && Storage::disk('public')->exists($project->project_file)) {
            Storage::disk('public')->delete($project->project_file);
        }

        $project->delete();

        return response()->noContent();
    }
}
