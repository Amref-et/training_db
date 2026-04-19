<?php

namespace App\Support;

use App\Models\GeneratedCrud;
use App\Models\Organization;
use App\Models\Participant;
use App\Models\Profession;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\ProjectSubawardee;
use App\Models\Region;
use App\Models\Training;
use App\Models\TrainingCategory;
use App\Models\TrainingEvent;
use App\Models\TrainingEventParticipant;
use App\Models\TrainingEventWorkshopScore;
use App\Models\TrainingMaterial;
use App\Models\TrainingOrganizer;
use App\Models\Woreda;
use App\Models\Zone;
use Illuminate\Support\Facades\Schema;

class ResourceRegistry
{
    private const FACILITY_ORGANIZATION_CATEGORIES = [
        'Community Org.',
        'Faith Based Org.',
        'Government/Public',
        'Military/Police/Prison',
        'NGO/CSO',
        'Private',
        'UN Agency',
    ];

    private const ORGANIZATION_TYPES = [
        'Business/Commercial entity',
        'Club/Association',
        'Community based org.',
        'Defense/Police force/Prison',
        'Faith-based org.',
        'Health Center/Clinic/Division',
        'Health Post',
        'Hospital',
        'International NGO/CSO',
        'Laboratory',
        'Local NGO/CSO',
        'Media Related',
        'MOH/RHB/ZHD/Wor. HO',
        'Other Government org.',
        'Pharmacy',
        'Research Institute',
        'School/University',
        'UN agency',
        'USG agency',
        'Other (specify)',
    ];

    private const PROFESSIONS = [
        'Administration/Management related',
        'Finance related',
        'Midwife',
        'SI related (M&E, Surveillance, IT)',
        'Anesthetist/Anesthesiologist',
        'Health Assistant',
        'Nurse',
        'Sociology/Psychology related',
        'Case Manager/Peer Educator/Expert Patient',
        'Health Extension Worker',
        'Pharmacy professional',
        'Student',
        'Community Volunteer',
        'Health Officer',
        'Physician',
        'Traditional Healer/Birth Attendant',
        'Community/Peer Leader',
        'Journalist',
        'Physiotherapist',
        'Trainer/Teacher/Instructor/Tutor',
        'Counselor',
        'Kebele Health Worker',
        'Public Health/Program related',
        'Youth Worker',
        'Dentist',
        'Laboratory Professional',
        'Religious Leader',
        'Environmental health related',
        'Mentor Mother',
        'Other (specify)',
    ];

    public static function staticResources(): array
    {
        return [
            'regions' => [
                'path' => 'regions', 'permission' => 'regions', 'label' => 'Regions', 'singular' => 'Region', 'model' => Region::class,
                'title_column' => 'name', 'search' => ['name'],
                'columns' => [['label' => 'Name', 'value' => 'name'], ['label' => 'Created', 'value' => 'created_at']],
                'fields' => [['name' => 'name', 'label' => 'Region Name', 'type' => 'text', 'required' => true]],
                'rules' => ['name' => 'required|string|max:255|unique:regions,name,{{id}},id'], 'order_by' => 'name',
            ],
            'woredas' => [
                'path' => 'woredas', 'permission' => 'woredas', 'label' => 'Woredas', 'singular' => 'Woreda', 'model' => Woreda::class,
                'title_column' => 'name', 'eager' => ['region', 'zone'], 'search' => ['name', 'description'],
                'columns' => [['label' => 'Name', 'value' => 'name'], ['label' => 'Region', 'value' => 'region.name'], ['label' => 'Zone', 'value' => 'zone.name'], ['label' => 'Description', 'value' => 'description']],
                'fields' => [
                    ['name' => 'region_id', 'label' => 'Region', 'type' => 'select', 'options' => ['model' => Region::class, 'value' => 'id', 'label' => 'name']],
                    ['name' => 'zone_id', 'label' => 'Zone', 'type' => 'select', 'required' => true, 'options' => ['model' => Zone::class, 'value' => 'id', 'label' => 'name']],
                    ['name' => 'name', 'label' => 'Woreda Name', 'type' => 'text', 'required' => true],
                    ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
                ],
                'rules' => ['region_id' => 'nullable|exists:regions,id', 'zone_id' => 'required|exists:zones,id', 'name' => 'required|string|max:255', 'description' => 'nullable|string'], 'order_by' => 'name',
            ],
            'zones' => [
                'path' => 'zones', 'permission' => 'zones', 'label' => 'Zones', 'singular' => 'Zone', 'model' => Zone::class,
                'title_column' => 'name', 'eager' => ['region'], 'search' => ['name', 'description'],
                'columns' => [
                    ['label' => 'Region', 'value' => 'region.name'],
                    ['label' => 'Name', 'value' => 'name'],
                    ['label' => 'Description', 'value' => 'description'],
                    ['label' => 'Created', 'value' => 'created_at'],
                ],
                'fields' => [
                    ['name' => 'region_id', 'label' => 'Region', 'type' => 'select', 'required' => true, 'options' => ['model' => Region::class, 'value' => 'id', 'label' => 'name']],
                    ['name' => 'name', 'label' => 'Zone Name', 'type' => 'text', 'required' => true],
                    ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
                ],
                'rules' => [
                    'region_id' => 'required|exists:regions,id',
                    'name' => 'required|string|max:255|unique:zones,name,{{id}},id',
                    'description' => 'nullable|string',
                ],
                'order_by' => 'name',
            ],
            'organizations' => [
                'path' => 'organizations', 'permission' => 'organizations', 'label' => 'Organizations', 'singular' => 'Organization', 'model' => Organization::class,
                'title_column' => 'name', 'eager' => ['region', 'zoneDefinition', 'woreda'], 'search' => ['name', 'zone', 'city_town', 'phone'],
                'columns' => [
                    ['label' => 'Name', 'value' => 'name'],
                    ['label' => 'Facility/Organization Category', 'value' => 'category'],
                    ['label' => 'Type', 'value' => 'type'],
                    ['label' => 'Region', 'value' => 'region.name'],
                    ['label' => 'Zone', 'value' => 'zone'],
                    ['label' => 'Woreda', 'value' => 'woreda.name'],
                    ['label' => 'City/Town', 'value' => 'city_town'],
                    ['label' => 'Phone', 'value' => 'phone'],
                    ['label' => 'Fax', 'value' => 'fax'],
                ],
                'fields' => [
                    ['name' => 'name', 'label' => 'Organization Name', 'type' => 'text', 'required' => true],
                    ['name' => 'category', 'label' => 'Facility/Organization Category', 'type' => 'select', 'required' => true, 'choices' => self::FACILITY_ORGANIZATION_CATEGORIES],
                    ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'required' => true, 'choices' => self::ORGANIZATION_TYPES],
                    ['name' => 'region_id', 'label' => 'Region', 'type' => 'select', 'options' => ['model' => Region::class, 'value' => 'id', 'label' => 'name']],
                    ['name' => 'zone_id', 'label' => 'Zone', 'type' => 'select', 'options' => ['model' => Zone::class, 'value' => 'id', 'label' => 'name']],
                    ['name' => 'woreda_id', 'label' => 'Woreda', 'type' => 'select', 'options' => ['model' => Woreda::class, 'value' => 'id', 'label' => 'name']],
                    ['name' => 'city_town', 'label' => 'City/Town', 'type' => 'text'],
                    ['name' => 'phone', 'label' => 'Phone', 'type' => 'text'],
                    ['name' => 'fax', 'label' => 'Fax', 'type' => 'text'],
                ],
                'rules' => [
                    'name' => 'required|string|max:255',
                    'category' => 'required|in:'.implode(',', self::FACILITY_ORGANIZATION_CATEGORIES),
                    'type' => 'required|in:'.implode(',', self::ORGANIZATION_TYPES),
                    'region_id' => 'nullable|exists:regions,id',
                    'zone_id' => 'nullable|exists:zones,id',
                    'woreda_id' => 'nullable|exists:woredas,id',
                    'city_town' => 'nullable|string|max:255',
                    'phone' => 'nullable|string|max:30',
                    'fax' => 'nullable|string|max:30',
                ], 'order_by' => 'name',
            ],
            'participants' => [
                'path' => 'participants', 'permission' => 'participants', 'label' => 'Participants', 'singular' => 'Participant', 'model' => Participant::class,
                'title_column' => 'name', 'eager' => ['region', 'zone', 'woreda', 'organization', 'professionDefinition'], 'search' => ['participant_code', 'first_name', 'father_name', 'grandfather_name', 'name', 'email', 'profession', 'mobile_phone', 'home_phone'],
                'columns' => [
                    ['label' => 'Participant ID', 'value' => 'participant_code'],
                    ['label' => 'First Name', 'value' => 'first_name'],
                    ['label' => "Father's Name", 'value' => 'father_name'],
                    ['label' => "Grandfather's Name", 'value' => 'grandfather_name'],
                    ['label' => 'DOB', 'value' => 'date_of_birth'],
                    ['label' => 'Age', 'value' => 'age'],
                    ['label' => 'Gender', 'value' => 'gender'],
                    ['label' => 'Home Phone', 'value' => 'home_phone'],
                    ['label' => 'Mobile', 'value' => 'mobile_phone'],
                    ['label' => 'Region', 'value' => 'region.name'],
                    ['label' => 'Zone', 'value' => 'zone.name'],
                    ['label' => 'Woreda', 'value' => 'woreda.name'],
                    ['label' => 'Organization', 'value' => 'organization.name'],
                    ['label' => 'Email', 'value' => 'email'],
                    ['label' => 'Profession', 'value' => 'professionDefinition.name'],
                ],
                'fields' => [
                    ['name' => 'first_name', 'label' => 'First Name', 'type' => 'text', 'required' => true],
                    ['name' => 'father_name', 'label' => "Father's Name", 'type' => 'text', 'required' => true],
                    ['name' => 'grandfather_name', 'label' => "Grandfather's Name", 'type' => 'text', 'required' => true],
                    ['name' => 'date_of_birth', 'label' => 'Date of Birth', 'type' => 'date'],
                    ['name' => 'age', 'label' => 'Age', 'type' => 'number'],
                    ['name' => 'region_id', 'label' => 'Region', 'type' => 'select', 'required' => true, 'options' => ['model' => Region::class, 'value' => 'id', 'label' => 'name']],
                    ['name' => 'zone_id', 'label' => 'Zone', 'type' => 'select', 'required' => true, 'options' => ['model' => Zone::class, 'value' => 'id', 'label' => 'name']],
                    ['name' => 'woreda_id', 'label' => 'Woreda', 'type' => 'select', 'required' => true, 'options' => ['model' => Woreda::class, 'value' => 'id', 'label' => 'name']],
                    ['name' => 'organization_id', 'label' => 'Organization', 'type' => 'select', 'required' => true, 'options' => ['model' => Organization::class, 'value' => 'id', 'label' => 'name']],
                    ['name' => 'gender', 'label' => 'Gender', 'type' => 'select', 'required' => true, 'choices' => ['male', 'female']],
                    ['name' => 'home_phone', 'label' => 'Home Phone', 'type' => 'text'],
                    ['name' => 'mobile_phone', 'label' => 'Mobile Phone', 'type' => 'text', 'required' => true],
                    ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
                    ['name' => 'profession', 'label' => 'Profession', 'type' => 'select', 'required' => true, 'options' => ['model' => Profession::class, 'value' => 'name', 'label' => 'name']],
                ],
                'rules' => [
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
                    'email' => 'required|email|max:255|unique:participants,email,{{id}},id',
                    'profession' => 'required|string|max:255|exists:professions,name',
                ], 'order_by' => 'name',
            ],
            'professions' => [
                'path' => 'professions', 'permission' => 'participants', 'label' => 'Professions', 'singular' => 'Profession', 'model' => Profession::class,
                'title_column' => 'name', 'search' => ['name', 'description'],
                'columns' => [
                    ['label' => 'Name', 'value' => 'name'],
                    ['label' => 'Description', 'value' => 'description'],
                    ['label' => 'Order', 'value' => 'sort_order'],
                    ['label' => 'Status', 'value' => 'is_active'],
                ],
                'fields' => [
                    ['name' => 'name', 'label' => 'Profession Name', 'type' => 'text', 'required' => true],
                    ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
                    ['name' => 'sort_order', 'label' => 'Sort Order', 'type' => 'number'],
                    ['name' => 'is_active', 'label' => 'Active', 'type' => 'select', 'choices' => [['value' => '1', 'label' => 'Yes'], ['value' => '0', 'label' => 'No']]],
                ],
                'rules' => [
                    'name' => 'required|string|max:255|unique:professions,name,{{id}},id',
                    'description' => 'nullable|string',
                    'sort_order' => 'nullable|integer|min:0|max:100000',
                    'is_active' => 'nullable|boolean',
                ],
                'order_by' => 'sort_order',
            ],
            'training_organizers' => [
                'path' => 'training-organizers', 'permission' => 'training_organizers', 'label' => 'Projects', 'singular' => 'Project', 'model' => TrainingOrganizer::class,
                'title_column' => 'project_name', 'eager' => ['subawardees'], 'search' => ['project_code', 'project_name', 'title'],
                'columns' => [
                    ['label' => 'Project Code', 'value' => 'project_code'],
                    ['label' => 'Project Name', 'value' => 'project_name'],
                    ['label' => 'Subawardees', 'value' => 'subawardees_list'],
                    ['label' => 'Created', 'value' => 'created_at'],
                ],
                'fields' => [
                    ['name' => 'project_code', 'label' => 'Project Code', 'type' => 'text', 'required' => true],
                    ['name' => 'project_name', 'label' => 'Project Name', 'type' => 'text', 'required' => true],
                    ['name' => 'subawardees', 'label' => 'Subawardees', 'type' => 'repeater', 'relation' => 'subawardees', 'column' => 'subawardee_name', 'item_label' => 'Subawardee', 'add_button' => 'Add Subawardee'],
                ],
                'rules' => [
                    'project_code' => 'required|string|max:255|unique:training_organizers,project_code,{{id}},id',
                    'project_name' => 'required|string|max:255',
                    'subawardees' => 'nullable|array',
                    'subawardees.*' => 'nullable|string|max:255|distinct',
                ], 'order_by' => 'project_name',
            ],
            'trainings' => [
                'path' => 'trainings', 'permission' => 'trainings', 'label' => 'Trainings', 'singular' => 'Training', 'model' => Training::class,
                'title_column' => 'title', 'eager' => ['trainingCategory'], 'search' => ['title', 'description'],
                'columns' => [['label' => 'Title', 'value' => 'title'], ['label' => 'Category', 'value' => 'trainingCategory.name'], ['label' => 'Modality', 'value' => 'modality'], ['label' => 'Type', 'value' => 'type']],
                'fields' => [
                    ['name' => 'training_category_id', 'label' => 'Training Category', 'type' => 'select', 'required' => true, 'options' => ['model' => TrainingCategory::class, 'value' => 'id', 'label' => 'name']],
                    ['name' => 'title', 'label' => 'Training Title', 'type' => 'text', 'required' => true],
                    ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'required' => true],
                    ['name' => 'modality', 'label' => 'Modality', 'type' => 'select', 'required' => true, 'choices' => ['Face 2 face', 'Online', 'Blended']],
                    ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'required' => true, 'choices' => ['Basic', 'Refresher', 'ToT']],
                ],
                'rules' => ['training_category_id' => 'required|exists:training_categories,id', 'title' => 'required|string|max:255', 'description' => 'nullable|string', 'modality' => 'nullable|in:Face 2 face,Online,Blended', 'type' => 'nullable|in:Basic,Refresher,ToT'], 'order_by' => 'title',
            ],
            'trainingcategories' => [
                'path' => 'trainingcategories', 'permission' => 'training_categories', 'label' => 'Training Categories', 'singular' => 'Training Category', 'model' => TrainingCategory::class,
                'title_column' => 'name', 'search' => ['name', 'description'],
                'columns' => [
                    ['label' => 'Name', 'value' => 'name'],
                    ['label' => 'Description', 'value' => 'description'],
                    ['label' => 'Order', 'value' => 'sort_order'],
                    ['label' => 'Status', 'value' => 'is_active'],
                ],
                'fields' => [
                    ['name' => 'name', 'label' => 'Category Name', 'type' => 'text', 'required' => true],
                    ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
                    ['name' => 'sort_order', 'label' => 'Sort Order', 'type' => 'number'],
                    ['name' => 'is_active', 'label' => 'Active', 'type' => 'select', 'choices' => [['value' => '1', 'label' => 'Yes'], ['value' => '0', 'label' => 'No']]],
                ],
                'rules' => [
                    'name' => 'required|string|max:255|unique:training_categories,name,{{id}},id',
                    'description' => 'nullable|string',
                    'sort_order' => 'nullable|integer|min:0|max:100000',
                    'is_active' => 'nullable|boolean',
                ],
                'order_by' => 'sort_order',
            ],
            'projects' => [
                'path' => 'projects', 'permission' => 'projects', 'label' => 'Projects', 'singular' => 'Project', 'model' => Project::class,
                'title_column' => 'title', 'eager' => ['participants', 'participant', 'projectCategory'], 'search' => ['title'],
                'columns' => [
                    ['label' => 'Title', 'value' => 'title'],
                    ['label' => 'Participants', 'value' => 'participants_list'],
                    ['label' => 'Category', 'value' => 'projectCategory.name'],
                    ['label' => 'Coaching Visit 1', 'value' => 'coaching_visit_1'],
                    ['label' => 'Coaching Visit 2', 'value' => 'coaching_visit_2'],
                    ['label' => 'Coaching Visit 3', 'value' => 'coaching_visit_3'],
                    ['label' => 'Project File', 'value' => 'project_file', 'type' => 'file'],
                    ['label' => 'Created', 'value' => 'created_at'],
                ],
                'fields' => [
                    ['name' => 'project_category_id', 'label' => 'Project Category', 'type' => 'select', 'tab' => 'Project Title & Category', 'required' => true, 'options' => ['model' => ProjectCategory::class, 'value' => 'id', 'label' => 'name']],
                    ['name' => 'title', 'label' => 'Project Title', 'type' => 'text', 'tab' => 'Project Title & Category', 'required' => true],
                    [
                        'name' => 'participant_ids',
                        'label' => 'Participants',
                        'type' => 'multiselect',
                        'tab' => 'Participants Assign',
                        'required' => true,
                        'relation' => 'participants',
                        'primary_column' => 'participant_id',
                        'options' => ['model' => Participant::class, 'value' => 'id', 'label' => 'name'],
                    ],
                    ['name' => 'coaching_visit_1', 'label' => 'Coaching Visit 1', 'type' => 'date', 'tab' => 'Coaching Visit 1'],
                    ['name' => 'coaching_visit_1_notes', 'label' => 'Coaching Visit 1 Notes', 'type' => 'tinymce', 'tab' => 'Coaching Visit 1'],
                    ['name' => 'coaching_visit_2', 'label' => 'Coaching Visit 2', 'type' => 'date', 'tab' => 'Coaching Visit 2'],
                    ['name' => 'coaching_visit_2_notes', 'label' => 'Coaching Visit 2 Notes', 'type' => 'tinymce', 'tab' => 'Coaching Visit 2'],
                    ['name' => 'coaching_visit_3', 'label' => 'Coaching Visit 3', 'type' => 'date', 'tab' => 'Coaching Visit 3'],
                    ['name' => 'coaching_visit_3_notes', 'label' => 'Coaching Visit 3 Notes', 'type' => 'tinymce', 'tab' => 'Coaching Visit 3'],
                    ['name' => 'project_file', 'label' => 'Project File', 'type' => 'file', 'tab' => 'Project File', 'disk' => 'public', 'directory' => 'project-files', 'accept' => '.pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg'],
                ],
                'rules' => [
                    'participant_ids' => 'required|array|min:1',
                    'participant_ids.*' => 'required|exists:participants,id',
                    'project_category_id' => 'required|exists:project_categories,id',
                    'title' => 'required|string|max:255',
                    'coaching_visit_1' => 'nullable|date',
                    'coaching_visit_1_notes' => 'nullable|string',
                    'coaching_visit_2' => 'nullable|date',
                    'coaching_visit_2_notes' => 'nullable|string',
                    'coaching_visit_3' => 'nullable|date',
                    'coaching_visit_3_notes' => 'nullable|string',
                    'project_file' => 'nullable|file|max:10240|mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg',
                ],
                'order_by' => 'title',
            ],
            'project_categories' => [
                'path' => 'project-categories', 'permission' => 'project_categories', 'label' => 'Project Categories', 'singular' => 'Project Category', 'model' => ProjectCategory::class,
                'title_column' => 'name', 'search' => ['name', 'description'],
                'columns' => [
                    ['label' => 'Name', 'value' => 'name'],
                    ['label' => 'Description', 'value' => 'description'],
                    ['label' => 'Order', 'value' => 'sort_order'],
                    ['label' => 'Status', 'value' => 'is_active'],
                ],
                'fields' => [
                    ['name' => 'name', 'label' => 'Category Name', 'type' => 'text', 'required' => true],
                    ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
                    ['name' => 'sort_order', 'label' => 'Sort Order', 'type' => 'number'],
                    ['name' => 'is_active', 'label' => 'Active', 'type' => 'select', 'choices' => [['value' => '1', 'label' => 'Yes'], ['value' => '0', 'label' => 'No']]],
                ],
                'rules' => [
                    'name' => 'required|string|max:255|unique:project_categories,name,{{id}},id',
                    'description' => 'nullable|string',
                    'sort_order' => 'nullable|integer|min:0|max:100000',
                    'is_active' => 'nullable|boolean',
                ],
                'order_by' => 'sort_order',
            ],
            'trainingmaterials' => [
                'path' => 'trainingmaterials', 'permission' => 'training_materials', 'label' => 'Training Materials', 'singular' => 'Training Material', 'model' => TrainingMaterial::class,
                'title_column' => 'title', 'eager' => ['training'], 'search' => ['title', 'description', 'external_url'],
                'columns' => [
                    ['label' => 'Title', 'value' => 'title'],
                    ['label' => 'Training', 'value' => 'training.title'],
                    ['label' => 'File', 'value' => 'resource_file', 'type' => 'file'],
                    ['label' => 'External URL', 'value' => 'external_url'],
                    ['label' => 'Order', 'value' => 'sort_order'],
                    ['label' => 'Status', 'value' => 'is_active'],
                ],
                'fields' => [
                    ['name' => 'training_id', 'label' => 'Training', 'type' => 'select', 'options' => ['model' => Training::class, 'value' => 'id', 'label' => 'title']],
                    ['name' => 'title', 'label' => 'Material Title', 'type' => 'text', 'required' => true],
                    ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
                    ['name' => 'resource_file', 'label' => 'Upload Resource File', 'type' => 'file', 'disk' => 'public', 'directory' => 'training-materials', 'accept' => '.pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.zip,.jpg,.jpeg,.png'],
                    ['name' => 'external_url', 'label' => 'External URL', 'type' => 'url'],
                    ['name' => 'sort_order', 'label' => 'Sort Order', 'type' => 'number'],
                    ['name' => 'is_active', 'label' => 'Active', 'type' => 'select', 'choices' => [['value' => '1', 'label' => 'Yes'], ['value' => '0', 'label' => 'No']]],
                ],
                'rules' => [
                    'training_id' => 'nullable|exists:trainings,id',
                    'title' => 'required|string|max:255',
                    'description' => 'nullable|string',
                    'resource_file' => 'nullable|file|max:20480|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,zip,jpg,jpeg,png',
                    'external_url' => 'nullable|url|max:255',
                    'sort_order' => 'nullable|integer|min:0|max:100000',
                    'is_active' => 'nullable|boolean',
                ],
                'order_by' => 'sort_order',
            ],
            'training_events' => [
                'path' => 'training-events', 'permission' => 'training_events', 'label' => 'Training Events', 'singular' => 'Training Event', 'model' => TrainingEvent::class,
                'title_column' => 'event_name', 'eager' => ['training', 'trainingOrganizer', 'trainingRegion', 'projectSubawardee'], 'with_count' => ['enrollments'], 'with_avg' => [['relation' => 'enrollments', 'column' => 'final_score', 'as' => 'avg_final_score']],
                'search' => ['event_name', 'status', 'training_city', 'course_venue'],
                'columns' => [
                    ['label' => 'Event', 'value' => 'event_name'],
                    ['label' => 'Training', 'value' => 'training.title'],
                    ['label' => 'Project Name', 'value' => 'trainingOrganizer.project_name'],
                    ['label' => 'Type of Organizer', 'value' => 'organizer_type'],
                    ['label' => 'Subawardee Name', 'value' => 'projectSubawardee.subawardee_name'],
                    ['label' => 'Training Region', 'value' => 'trainingRegion.name'],
                    ['label' => 'Training City/Town', 'value' => 'training_city'],
                    ['label' => 'Course Venue', 'value' => 'course_venue'],
                    ['label' => 'Workshops', 'value' => 'workshop_count'],
                    ['label' => 'Start', 'value' => 'start_date'],
                    ['label' => 'End', 'value' => 'end_date'],
                    ['label' => 'Status', 'value' => 'status'],
                    ['label' => 'Participants', 'value' => 'enrollments_count'],
                    ['label' => 'Avg Final Score', 'value' => 'avg_final_score'],
                ],
                'fields' => [
                    ['name' => 'event_name', 'label' => 'Event Name', 'type' => 'text', 'required' => true],
                    ['name' => 'training_id', 'label' => 'Training', 'type' => 'select', 'required' => true, 'options' => ['model' => Training::class, 'value' => 'id', 'label' => 'title']],
                    ['name' => 'training_organizer_id', 'label' => 'Project Name', 'type' => 'select', 'required' => true, 'options' => ['model' => TrainingOrganizer::class, 'value' => 'id', 'label' => 'title']],
                    ['name' => 'organizer_type', 'label' => 'Who organized the training', 'type' => 'select', 'required' => true, 'choices' => ['The project', 'Subawardee']],
                    ['name' => 'project_subawardee_id', 'label' => 'Subawardee Name', 'type' => 'select', 'options' => ['model' => ProjectSubawardee::class, 'value' => 'id', 'label' => 'subawardee_name']],
                    ['name' => 'training_region_id', 'label' => 'Training Region', 'type' => 'select', 'options' => ['model' => Region::class, 'value' => 'id', 'label' => 'name']],
                    ['name' => 'training_city', 'label' => 'Training City/Town', 'type' => 'text'],
                    ['name' => 'course_venue', 'label' => 'Course Venue', 'type' => 'text'],
                    ['name' => 'workshop_count', 'label' => 'Number of Workshops', 'type' => 'number'],
                    ['name' => 'start_date', 'label' => 'Start Date', 'type' => 'date', 'required' => true],
                    ['name' => 'end_date', 'label' => 'End Date', 'type' => 'date', 'required' => true],
                    ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'required' => true, 'choices' => ['Pending', 'Ongoing', 'Completed', 'Cancelled']],
                ],
                'rules' => [
                    'event_name' => 'required|string|max:255',
                    'training_id' => 'required|exists:trainings,id',
                    'training_organizer_id' => 'required|exists:training_organizers,id',
                    'organizer_type' => 'required|in:The project,Subawardee',
                    'project_subawardee_id' => 'nullable|exists:project_subawardees,id',
                    'training_region_id' => 'nullable|exists:regions,id',
                    'training_city' => 'nullable|string|max:255',
                    'course_venue' => 'nullable|string|max:255',
                    'workshop_count' => 'nullable|integer|min:1|max:20',
                    'start_date' => 'required|date',
                    'end_date' => 'required|date|after_or_equal:start_date',
                    'status' => 'required|string|max:255',
                ],
                'order_by' => 'created_at', 'order_direction' => 'desc',
            ],
            'training_event_participants' => [
                'path' => 'training-event-participants', 'permission' => 'training_event_participants', 'label' => 'Event Participants', 'singular' => 'Event Participant', 'model' => TrainingEventParticipant::class,
                'title_column' => 'id', 'eager' => ['trainingEvent.training', 'participant'], 'with_count' => ['workshopScores'],
                'columns' => [
                    ['label' => 'Event', 'value' => 'trainingEvent.event_name'],
                    ['label' => 'Training', 'value' => 'trainingEvent.training.title'],
                    ['label' => 'Participant', 'value' => 'participant.name'],
                    ['label' => 'Workshops Scored', 'value' => 'workshop_scores_count'],
                    ['label' => 'Final Score', 'value' => 'final_score'],
                    ['label' => 'Completion', 'value' => 'activity_completion_status'],
                    ['label' => 'Is Now Trainer', 'value' => 'is_trainer'],
                ],
                'fields' => [
                    ['name' => 'training_event_id', 'label' => 'Training Event', 'type' => 'select', 'required' => true, 'options' => ['model' => TrainingEvent::class, 'value' => 'id', 'label' => 'display_label', 'with' => ['training']]],
                    ['name' => 'participant_id', 'label' => 'Participant', 'type' => 'select', 'required' => true, 'options' => ['model' => Participant::class, 'value' => 'id', 'label' => 'name']],
                    ['name' => 'activity_completion_status', 'label' => 'Activity Completion', 'type' => 'select', 'choices' => ['Completed Activity', 'Did Not Complete Activity']],
                    ['name' => 'is_trainer', 'label' => 'Is Now a Trainer', 'type' => 'select', 'choices' => [['value' => '0', 'label' => 'No'], ['value' => '1', 'label' => 'Yes']]],
                    ['name' => 'trainer_comments', 'label' => 'Comments/Evaluation', 'type' => 'textarea'],
                    ['name' => 'trainer_name', 'label' => 'Trainer/Organizer Name', 'type' => 'text'],
                    ['name' => 'trainer_signature', 'label' => 'Trainer/Organizer Signature', 'type' => 'text'],
                ],
                'rules' => [
                    'training_event_id' => 'required|exists:training_events,id',
                    'participant_id' => 'required|exists:participants,id',
                    'activity_completion_status' => 'nullable|in:Completed Activity,Did Not Complete Activity',
                    'is_trainer' => 'nullable|boolean',
                    'trainer_comments' => 'nullable|string',
                    'trainer_name' => 'nullable|string|max:255',
                    'trainer_signature' => 'nullable|string|max:255',
                ],
                'order_by' => 'created_at', 'order_direction' => 'desc',
            ],
            'training_event_workshop_scores' => [
                'path' => 'training-event-workshop-scores', 'permission' => 'training_event_workshop_scores', 'label' => 'Workshop Scores', 'singular' => 'Workshop Score', 'model' => TrainingEventWorkshopScore::class,
                'title_column' => 'id', 'eager' => ['trainingEventParticipant.trainingEvent.training', 'trainingEventParticipant.participant'],
                'columns' => [
                    ['label' => 'Event', 'value' => 'trainingEventParticipant.trainingEvent.event_name'],
                    ['label' => 'Training', 'value' => 'trainingEventParticipant.trainingEvent.training.title'],
                    ['label' => 'Participant', 'value' => 'trainingEventParticipant.participant.name'],
                    ['label' => 'Workshop #', 'value' => 'workshop_number'],
                    ['label' => 'Pre-test Score', 'value' => 'pre_test_score'],
                    ['label' => 'Mid-test Score', 'value' => 'mid_test_score'],
                    ['label' => 'Post-test Score', 'value' => 'post_test_score'],
                ],
                'fields' => [
                    ['name' => 'training_event_participant_id', 'label' => 'Event Participant', 'type' => 'select', 'required' => true, 'options' => ['model' => TrainingEventParticipant::class, 'value' => 'id', 'label' => 'display_label', 'with' => ['trainingEvent', 'participant']]],
                    ['name' => 'workshop_number', 'label' => 'Workshop Number', 'type' => 'number', 'required' => true],
                    ['name' => 'pre_test_score', 'label' => 'Pre-test Score', 'type' => 'number'],
                    ['name' => 'mid_test_score', 'label' => 'Mid-test Score', 'type' => 'number'],
                    ['name' => 'post_test_score', 'label' => 'Post-test Score', 'type' => 'number'],
                ],
                'rules' => [
                    'training_event_participant_id' => 'required|exists:training_event_participants,id',
                    'workshop_number' => 'required|integer|min:1|max:20',
                    'pre_test_score' => 'nullable|numeric|min:0|max:100',
                    'mid_test_score' => 'nullable|numeric|min:0|max:100',
                    'post_test_score' => 'nullable|numeric|min:0|max:100',
                ],
                'order_by' => 'created_at', 'order_direction' => 'desc',
            ],
        ];
    }

    public static function all(): array
    {
        $resources = self::staticResources();

        if (Schema::hasTable('generated_cruds')) {
            foreach (GeneratedCrud::query()->orderBy('name')->get() as $crud) {
                $resources[$crud->slug] = GeneratedCrudConfigFactory::make($crud);
            }
        }

        return $resources;
    }

    public static function get(string $resource): array
    {
        $resources = self::all();
        abort_unless(isset($resources[$resource]), 404);
        return $resources[$resource];
    }
}


