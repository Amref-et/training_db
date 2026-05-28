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
            $categoryId = $this->insertItem(null, 'category', $category['title'], null, $sort, $now);
            $subcategorySort = 10;

            foreach ($category['children'] as $subcategory) {
                $subcategoryId = $this->insertItem($categoryId, 'category', $subcategory['title'], null, $subcategorySort, $now);
                $questionSort = 10;

                foreach ($subcategory['questions'] as $question) {
                    $this->insertItem($subcategoryId, 'question', $question['title'], $question['answer'], $questionSort, $now);
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

    private function insertItem(?int $parentId, string $type, string $title, ?string $answer, int $sortOrder, mixed $timestamp): int
    {
        return (int) DB::table('fab_faq_items')->insertGetId([
            'parent_id' => $parentId,
            'type' => $type,
            'title' => $title,
            'answer' => $answer,
            'sort_order' => $sortOrder,
            'is_active' => true,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
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
