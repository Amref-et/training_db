<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fab_faq_items') || DB::table('fab_faq_items')->exists()) {
            return;
        }

        $now = now();
        $sort = 10;

        foreach ($this->faqTree() as $category) {
            $categoryVisibility = $this->itemVisibility($category);
            $categoryId = $this->insertItem(null, 'category', $category, $sort, $now);
            $subcategorySort = 10;

            foreach ($category['children'] as $subcategory) {
                $subcategoryVisibility = $this->itemVisibility($subcategory, $categoryVisibility);
                $subcategoryId = $this->insertItem($categoryId, 'category', $subcategory, $subcategorySort, $now, $categoryVisibility);
                $questionSort = 10;

                foreach ($subcategory['questions'] as $question) {
                    $this->insertItem($subcategoryId, 'question', $question, $questionSort, $now, $subcategoryVisibility);
                    $questionSort += 10;
                }

                $subcategorySort += 10;
            }

            $sort += 10;
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('fab_faq_items')) {
            return;
        }

        DB::table('fab_faq_items')
            ->whereNull('parent_id')
            ->whereIn('title', collect($this->faqTree())->pluck('title')->all())
            ->delete();
    }

    private function insertItem(?int $parentId, string $type, array $item, int $sortOrder, mixed $timestamp, string $defaultVisibility = 'both'): int
    {
        $metadata = $this->itemMetadata($item['title']);
        $linkUrl = $item['link_url'] ?? $metadata['link_url'] ?? null;
        $linkLabel = $item['link_label'] ?? $metadata['link_label'] ?? null;

        if ($linkUrl === null || trim((string) $linkUrl) === '') {
            $linkUrl = null;
            $linkLabel = null;
        } elseif ($linkLabel === null || trim((string) $linkLabel) === '') {
            $linkLabel = 'Open link';
        }

        return (int) DB::table('fab_faq_items')->insertGetId([
            'parent_id' => $parentId,
            'type' => $type,
            'visibility' => $this->itemVisibility($item, $defaultVisibility),
            'title' => $item['title'],
            'answer' => $type === 'question' ? $item['answer'] : null,
            'link_label' => $linkLabel,
            'link_url' => $linkUrl,
            'sort_order' => $sortOrder,
            'is_active' => true,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    private function itemVisibility(array $item, string $defaultVisibility = 'both'): string
    {
        return $item['visibility'] ?? $this->itemMetadata($item['title'])['visibility'] ?? $defaultVisibility;
    }

    private function itemMetadata(string $title): array
    {
        $metadata = [
            'Participant Support' => ['visibility' => 'both'],
            'Registration' => ['visibility' => 'public'],
            'Participant Records' => ['visibility' => 'admin'],
            'Training Events' => ['visibility' => 'both'],
            'Calendar And Grouped Views' => ['visibility' => 'both'],
            'Workflow Steps' => ['visibility' => 'admin'],
            'Scores And Reports' => ['visibility' => 'admin'],
            'Administration' => ['visibility' => 'admin'],
            'Data Management' => ['visibility' => 'admin'],
            'Website And Content' => ['visibility' => 'admin'],
            'Projects And Learning Materials' => ['visibility' => 'admin'],
            'Reference Data' => ['visibility' => 'admin'],
            'System Monitoring' => ['visibility' => 'admin'],

            'How do I register as a participant?' => ['visibility' => 'public', 'link_label' => 'Open registration', 'link_url' => '/participant-registration'],
            'What happens if my generated participant ID already exists?' => ['visibility' => 'public', 'link_label' => 'Open registration', 'link_url' => '/participant-registration'],
            'Can I join a training event from the public website?' => ['visibility' => 'public', 'link_label' => 'Request enrollment', 'link_url' => '/training-event-join-request'],
            'Can admins search existing participants during enrollment?' => ['visibility' => 'admin', 'link_label' => 'Create participant', 'link_url' => '/admin/participants/create'],
            'Can participant training participation be exported?' => ['visibility' => 'admin', 'link_label' => 'Export participation', 'link_url' => '/admin/participants/training-participation/export'],
            'What does the training calendar show by default?' => ['visibility' => 'public', 'link_label' => 'Open calendar', 'link_url' => '/embed/training-events-calendar'],
            'How are grouped training events displayed?' => ['visibility' => 'admin', 'link_label' => 'Open grouped training', 'link_url' => '/admin/training-events/grouped-training'],
            'Can I edit events from grouped views?' => ['visibility' => 'admin', 'link_label' => 'Open grouped events', 'link_url' => '/admin/training-events/grouped'],
            'What is the training workflow used for?' => ['visibility' => 'admin', 'link_label' => 'Open workflow', 'link_url' => '/admin/training-workflow'],
            'What can admins do in Step 5 Closeout?' => ['visibility' => 'admin', 'link_label' => 'Open closeout', 'link_url' => '/admin/training-workflow?step=5'],
            'Can event pictures be uploaded in bulk?' => ['visibility' => 'admin', 'link_label' => 'Open closeout', 'link_url' => '/admin/training-workflow?step=5'],
            'How are final scores calculated?' => ['visibility' => 'admin', 'link_label' => 'Open workflow', 'link_url' => '/admin/training-workflow?step=4'],
            'Can workshop score sheets be imported or exported?' => ['visibility' => 'admin', 'link_label' => 'Open scores', 'link_url' => '/admin/training-workflow?step=4'],
            'What reports are available for training participation?' => ['visibility' => 'admin', 'link_label' => 'Open participants', 'link_url' => '/admin/participants'],
            'Can dashboards be shared?' => ['visibility' => 'admin', 'link_label' => 'Open dashboard', 'link_url' => '/admin'],
            'Can admins import users?' => ['visibility' => 'admin', 'link_label' => 'Open users', 'link_url' => '/admin/users'],
            'How are temporary passwords shared?' => ['visibility' => 'admin', 'link_label' => 'Open users', 'link_url' => '/admin/users'],
            'How are roles assigned?' => ['visibility' => 'admin', 'link_label' => 'Open roles', 'link_url' => '/admin/roles'],
            'How do I enable the floating FAQ chatbot?' => ['visibility' => 'admin', 'link_label' => 'Open appearance', 'link_url' => '/admin/appearance'],
            'How do I manage chatbot FAQ content?' => ['visibility' => 'admin', 'link_label' => 'Manage FAQs', 'link_url' => '/admin/fab-faqs'],
            'Can FAQs have more than one level?' => ['visibility' => 'admin', 'link_label' => 'Manage FAQs', 'link_url' => '/admin/fab-faqs'],
            'Can organization data be imported?' => ['visibility' => 'admin', 'link_label' => 'Open organizations', 'link_url' => '/admin/organizations'],
            'Can participant data be imported?' => ['visibility' => 'admin', 'link_label' => 'Open participants', 'link_url' => '/admin/participants'],
            'Does the system expose API documentation?' => ['visibility' => 'admin', 'link_label' => 'Open API docs', 'link_url' => '/admin/api-management/docs'],
            'Can training event data be synced to DHIS2?' => ['visibility' => 'admin', 'link_label' => 'Open API management', 'link_url' => '/admin/api-management'],
            'Can admins manage website pages?' => ['visibility' => 'admin', 'link_label' => 'Open pages', 'link_url' => '/admin/pages'],
            'Can the public website menu be managed from admin?' => ['visibility' => 'admin', 'link_label' => 'Open menus', 'link_url' => '/admin/menus'],
            'Can the admin sidebar menu be customized?' => ['visibility' => 'admin', 'link_label' => 'Open sidebar menus', 'link_url' => '/admin/sidebar-menus'],
            'What can be changed from Appearance settings?' => ['visibility' => 'admin', 'link_label' => 'Open appearance', 'link_url' => '/admin/appearance'],
            'Can custom CSS and JavaScript be added?' => ['visibility' => 'admin', 'link_label' => 'Open appearance', 'link_url' => '/admin/appearance'],
            'Can the public login page be branded?' => ['visibility' => 'admin', 'link_label' => 'Open appearance', 'link_url' => '/admin/appearance'],
            'What project information can be tracked?' => ['visibility' => 'admin', 'link_label' => 'Open projects', 'link_url' => '/admin/projects'],
            'Can project categories be managed?' => ['visibility' => 'admin', 'link_label' => 'Open project categories', 'link_url' => '/admin/project-categories'],
            'Can participant projects be linked?' => ['visibility' => 'admin', 'link_label' => 'Open projects', 'link_url' => '/admin/projects'],
            'Can training materials be uploaded?' => ['visibility' => 'admin', 'link_label' => 'Open materials', 'link_url' => '/admin/trainingmaterials'],
            'Can training materials use external links?' => ['visibility' => 'admin', 'link_label' => 'Open materials', 'link_url' => '/admin/trainingmaterials'],
            'Can materials be ordered or hidden?' => ['visibility' => 'admin', 'link_label' => 'Open materials', 'link_url' => '/admin/trainingmaterials'],
            'What location data does the system manage?' => ['visibility' => 'admin', 'link_label' => 'Open regions', 'link_url' => '/admin/regions'],
            'How are organizations connected to locations?' => ['visibility' => 'admin', 'link_label' => 'Open organizations', 'link_url' => '/admin/organizations'],
            'Can imported location IDs be preserved?' => ['visibility' => 'admin', 'link_label' => 'Open organizations', 'link_url' => '/admin/organizations'],
            'What is the difference between Trainings and Training Events?' => ['visibility' => 'admin', 'link_label' => 'Open trainings', 'link_url' => '/admin/trainings'],
            'Can training categories be managed?' => ['visibility' => 'admin', 'link_label' => 'Open training categories', 'link_url' => '/admin/trainingcategories'],
            'Can professions be managed?' => ['visibility' => 'admin', 'link_label' => 'Open professions', 'link_url' => '/admin/professions'],
            'Can admins review user activity?' => ['visibility' => 'admin', 'link_label' => 'Open activity logs', 'link_url' => '/admin/user-activity-logs'],
            'Why was the activity log query optimized?' => ['visibility' => 'admin', 'link_label' => 'Open activity logs', 'link_url' => '/admin/user-activity-logs'],
            'Can environment settings be managed from admin?' => ['visibility' => 'admin', 'link_label' => 'Open env settings', 'link_url' => '/admin/settings/env'],
            'What is API Management used for?' => ['visibility' => 'admin', 'link_label' => 'Open API management', 'link_url' => '/admin/api-management'],
            'Who can access system administration features?' => ['visibility' => 'admin', 'link_label' => 'Open roles', 'link_url' => '/admin/roles'],
        ];

        return $metadata[$title] ?? [];
    }

    private function faqTree(): array
    {
        return [
            [
                'title' => 'Participant Support',
                'children' => [
                    [
                        'title' => 'Registration',
                        'questions' => [
                            [
                                'title' => 'How do I register as a participant?',
                                'answer' => 'Open the Participant Registration page, enter your name, date of birth or age, contact information, location, organization, and profession, then submit the form. The system generates your participant ID from your details.',
                            ],
                            [
                                'title' => 'What happens if my generated participant ID already exists?',
                                'answer' => 'The system does not create a duplicate participant or add a suffix. It warns you and loads the existing participant record so the previous profile can be reused.',
                            ],
                            [
                                'title' => 'Can I join a training event from the public website?',
                                'answer' => 'Yes. Use the public training event enrollment request form. After submission, an admin reviews the request and can approve or reject it from the training workflow.',
                            ],
                        ],
                    ],
                    [
                        'title' => 'Participant Records',
                        'questions' => [
                            [
                                'title' => 'Can admins search existing participants during enrollment?',
                                'answer' => 'Yes. Admins can search existing participants from the participant creation and training workflow screens, then reuse the selected record instead of creating a duplicate.',
                            ],
                            [
                                'title' => 'Can participant training participation be exported?',
                                'answer' => 'Yes. Admins can export participant training participation as CSV, including participant details, organization, event, training, organizer, scores, trainer information, and workshop completion data.',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Training Events',
                'children' => [
                    [
                        'title' => 'Calendar And Grouped Views',
                        'questions' => [
                            [
                                'title' => 'What does the training calendar show by default?',
                                'answer' => 'The training calendar opens on the current month by default, making it easier to see upcoming and active training events immediately.',
                            ],
                            [
                                'title' => 'How are grouped training events displayed?',
                                'answer' => 'The grouped training event pages summarize events and allow admins to expand rows for event details. The Grouped Training page groups events only by training title.',
                            ],
                            [
                                'title' => 'Can I edit events from grouped views?',
                                'answer' => 'Yes. Users with update permission can open event details from grouped views and use the edit action for the selected training event.',
                            ],
                        ],
                    ],
                    [
                        'title' => 'Workflow Steps',
                        'questions' => [
                            [
                                'title' => 'What is the training workflow used for?',
                                'answer' => 'The training workflow guides admins through event setup, participant enrollment, workshop structure, score entry, reporting, and closeout.',
                            ],
                            [
                                'title' => 'What can admins do in Step 5 Closeout?',
                                'answer' => 'Admins can update the event status, upload the final training event report, and upload multiple event pictures. Existing reports or pictures can also be removed.',
                            ],
                            [
                                'title' => 'Can event pictures be uploaded in bulk?',
                                'answer' => 'Yes. The Step 5 closeout form supports selecting multiple image files in one upload, and existing images stay attached unless selected for removal.',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Scores And Reports',
                'children' => [
                    [
                        'title' => 'Workshop Scores',
                        'questions' => [
                            [
                                'title' => 'How are final scores calculated?',
                                'answer' => 'Final scores are calculated from configured workshop post-test scores. A participant final score is completed when all required workshop post-test scores are available.',
                            ],
                            [
                                'title' => 'Can workshop score sheets be imported or exported?',
                                'answer' => 'Yes. Training workflow tools support exporting workshop score templates and importing completed workshop score files for an event.',
                            ],
                        ],
                    ],
                    [
                        'title' => 'Dashboards And Exports',
                        'questions' => [
                            [
                                'title' => 'What reports are available for training participation?',
                                'answer' => 'Admins can export full participant reports from the workflow and export participant training participation CSVs from participant management.',
                            ],
                            [
                                'title' => 'Can dashboards be shared?',
                                'answer' => 'Yes. Dashboard tabs can be shared, and public homepage dashboard content can be selected from appearance settings when configured.',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Administration',
                'children' => [
                    [
                        'title' => 'Users And Access',
                        'questions' => [
                            [
                                'title' => 'Can admins import users?',
                                'answer' => 'Yes. Admins can import users from a CSV file with name, email, and role columns. New accounts receive generated temporary passwords in a one-time import result CSV.',
                            ],
                            [
                                'title' => 'How are temporary passwords shared?',
                                'answer' => 'After user import, download the import result CSV and share the generated temporary password with each new user through your approved secure communication process.',
                            ],
                            [
                                'title' => 'How are roles assigned?',
                                'answer' => 'Each imported or manually created user is assigned a role. Roles control access to administration features through permissions.',
                            ],
                        ],
                    ],
                    [
                        'title' => 'Appearance And FAQ Chatbot',
                        'questions' => [
                            [
                                'title' => 'How do I enable the floating FAQ chatbot?',
                                'answer' => 'Go to Appearance settings and enable the FAQ Chatbot FAB toggle. The floating help button appears on public pages when enabled.',
                            ],
                            [
                                'title' => 'How do I manage chatbot FAQ content?',
                                'answer' => 'Open FAB FAQs from the admin sidebar or Appearance page. You can add categories, subcategories, questions, answers, reorder items, hide items, edit content, or delete items.',
                            ],
                            [
                                'title' => 'Can FAQs have more than one level?',
                                'answer' => 'Yes. FAQ categories can contain subcategories, and subcategories can contain more categories or final question items with answers.',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Data Management',
                'children' => [
                    [
                        'title' => 'Organizations And Participants',
                        'questions' => [
                            [
                                'title' => 'Can organization data be imported?',
                                'answer' => 'Yes. Organization import supports CSV uploads with region, zone, woreda, organization, category, and type data. Skipped rows can be downloaded with reasons.',
                            ],
                            [
                                'title' => 'Can participant data be imported?',
                                'answer' => 'Yes. Participant import supports CSV files and preserves model-generated participant codes while applying date of birth and age logic.',
                            ],
                        ],
                    ],
                    [
                        'title' => 'API And Integrations',
                        'questions' => [
                            [
                                'title' => 'Does the system expose API documentation?',
                                'answer' => 'Yes. API management includes documentation for available API endpoints and integration-related controls for authorized administrators.',
                            ],
                            [
                                'title' => 'Can training event data be synced to DHIS2?',
                                'answer' => 'The API management area includes DHIS2 integration support for syncing training event data when the integration is configured.',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Website And Content',
                'children' => [
                    [
                        'title' => 'Pages And Navigation',
                        'questions' => [
                            [
                                'title' => 'Can admins manage website pages?',
                                'answer' => 'Yes. Admins can create and edit CMS pages, publish or draft content, configure homepage behavior, and use page sections or content blocks for public pages.',
                            ],
                            [
                                'title' => 'Can the public website menu be managed from admin?',
                                'answer' => 'Yes. Website menu items can be managed from the admin area so public navigation can point to pages, custom URLs, or other configured destinations.',
                            ],
                            [
                                'title' => 'Can the admin sidebar menu be customized?',
                                'answer' => 'Yes. The Sidebar Menus area lets authorized admins manage admin navigation items, sections, order, icons, permissions, and nested menu structure.',
                            ],
                        ],
                    ],
                    [
                        'title' => 'Appearance Settings',
                        'questions' => [
                            [
                                'title' => 'What can be changed from Appearance settings?',
                                'answer' => 'Appearance settings control site name, tagline, favicon, header and footer logos, colors, border radius, login page copy, custom CSS, custom JavaScript, and visibility toggles such as admin links and the FAQ chatbot FAB.',
                            ],
                            [
                                'title' => 'Can custom CSS and JavaScript be added?',
                                'answer' => 'Yes. Appearance includes controlled fields for custom CSS and custom JavaScript that are loaded on public pages for targeted presentation or interaction refinements.',
                            ],
                            [
                                'title' => 'Can the public login page be branded?',
                                'answer' => 'Yes. Login page headings, helper text, feature copy, colors, and visual styling can be managed from Appearance settings.',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Projects And Learning Materials',
                'children' => [
                    [
                        'title' => 'Projects And Coaching',
                        'questions' => [
                            [
                                'title' => 'What project information can be tracked?',
                                'answer' => 'Projects can track title, category, linked participants, uploaded project files, and coaching visit dates and notes for multiple coaching visits.',
                            ],
                            [
                                'title' => 'Can project categories be managed?',
                                'answer' => 'Yes. Project categories can be added, edited, ordered, activated, or hidden from the admin reference data screens.',
                            ],
                            [
                                'title' => 'Can participant projects be linked?',
                                'answer' => 'Yes. Project records can be linked to one or more participants, helping admins connect training participants with project follow-up and coaching activity.',
                            ],
                        ],
                    ],
                    [
                        'title' => 'Training Materials',
                        'questions' => [
                            [
                                'title' => 'Can training materials be uploaded?',
                                'answer' => 'Yes. Training materials can be attached to a training using uploaded files such as PDF, Word, PowerPoint, Excel, ZIP, or image files.',
                            ],
                            [
                                'title' => 'Can training materials use external links?',
                                'answer' => 'Yes. A training material can include an external URL when the resource is hosted outside the system.',
                            ],
                            [
                                'title' => 'Can materials be ordered or hidden?',
                                'answer' => 'Yes. Training material records include sort order and active status controls so admins can organize and hide resources as needed.',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Reference Data',
                'children' => [
                    [
                        'title' => 'Locations And Facilities',
                        'questions' => [
                            [
                                'title' => 'What location data does the system manage?',
                                'answer' => 'The system manages regions, zones, and woredas. These are used by participant profiles, organizations, event locations, dashboards, and reports.',
                            ],
                            [
                                'title' => 'How are organizations connected to locations?',
                                'answer' => 'Organizations are linked to region, zone, and woreda records and can store organization category, type, city or town, phone, and fax details.',
                            ],
                            [
                                'title' => 'Can imported location IDs be preserved?',
                                'answer' => 'Yes. Imported region, zone, woreda, and organization IDs can be stored and shown in admin lists to preserve external reference identifiers.',
                            ],
                        ],
                    ],
                    [
                        'title' => 'Training Setup Data',
                        'questions' => [
                            [
                                'title' => 'What is the difference between Trainings and Training Events?',
                                'answer' => 'Trainings define the curriculum, category, modality, and type. Training Events are scheduled deliveries of a training with date, location, organizer, workshop count, participants, scores, and status.',
                            ],
                            [
                                'title' => 'Can training categories be managed?',
                                'answer' => 'Yes. Training categories can be created, edited, ordered, activated, or hidden to organize training curricula.',
                            ],
                            [
                                'title' => 'Can professions be managed?',
                                'answer' => 'Yes. Profession values can be managed from admin and used when registering or importing participants.',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'System Monitoring',
                'children' => [
                    [
                        'title' => 'Activity Logs',
                        'questions' => [
                            [
                                'title' => 'Can admins review user activity?',
                                'answer' => 'Yes. The User Activity Log records user actions and can be filtered so administrators can review important account and system activity.',
                            ],
                            [
                                'title' => 'Why was the activity log query optimized?',
                                'answer' => 'The activity log page avoids heavy sorting on large datasets and uses supporting indexes so recent activity can load without exhausting database sort memory.',
                            ],
                        ],
                    ],
                    [
                        'title' => 'Environment And API Settings',
                        'questions' => [
                            [
                                'title' => 'Can environment settings be managed from admin?',
                                'answer' => 'Yes. Authorized admins can open Env Settings from the Appearance area to manage supported application environment values.',
                            ],
                            [
                                'title' => 'What is API Management used for?',
                                'answer' => 'API Management provides controls and documentation for integrations, API access, and supported sync workflows such as DHIS2 training event sync.',
                            ],
                            [
                                'title' => 'Who can access system administration features?',
                                'answer' => 'Access is controlled by roles and permissions. Users only see and use features allowed by their assigned role permissions.',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
};
