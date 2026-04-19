<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $replacements = [
            <<<'SQL'
SELECT gender_label AS label, total AS value
FROM (
    SELECT
        CASE
            WHEN LOWER(TRIM(gender)) IN ('male', 'm') THEN 'Male'
            WHEN LOWER(TRIM(gender)) IN ('female', 'f') THEN 'Female'
            ELSE 'Other'
        END AS gender_label,
        COUNT(*) AS total
    FROM participants
    GROUP BY gender_label
) AS gender_totals
WHERE gender_label IN ('Male', 'Female')
SQL
            =>
            <<<'SQL'
SELECT gender_label AS label, total AS value
FROM (
    SELECT
        CASE
            WHEN LOWER(TRIM(p.gender)) IN ('male', 'm') THEN 'Male'
            WHEN LOWER(TRIM(p.gender)) IN ('female', 'f') THEN 'Female'
            ELSE 'Other'
        END AS gender_label,
        COUNT(*) AS total
    FROM participants p
    WHERE 1 = 1{{participants_filter:p}}
    GROUP BY gender_label
) AS gender_totals
WHERE gender_label IN ('Male', 'Female')
SQL,
            <<<'SQL'
SELECT metric AS label, average_score AS value
FROM (
    SELECT 'Pre-test' AS metric, ROUND(AVG(pre_test_score), 1) AS average_score FROM training_event_workshop_scores
    UNION ALL
    SELECT 'Post-test' AS metric, ROUND(AVG(final_score), 1) AS average_score FROM training_event_participants
) AS score_totals
SQL
            =>
            <<<'SQL'
SELECT metric AS label, average_score AS value
FROM (
    SELECT 'Pre-test' AS metric, ROUND(AVG(w.pre_test_score), 1) AS average_score
    FROM training_event_workshop_scores w
    INNER JOIN training_event_participants ep ON ep.id = w.training_event_participant_id
    INNER JOIN participants p ON p.id = ep.participant_id
    INNER JOIN training_events e ON e.id = ep.training_event_id
    WHERE 1 = 1{{participants_filter:p}}{{events_filter:e}}
    UNION ALL
    SELECT 'Post-test' AS metric, ROUND(AVG(ep.final_score), 1) AS average_score
    FROM training_event_participants ep
    INNER JOIN participants p ON p.id = ep.participant_id
    INNER JOIN training_events e ON e.id = ep.training_event_id
    WHERE 1 = 1{{participants_filter:p}}{{events_filter:e}}
) AS score_totals
SQL,
            <<<'SQL'
SELECT label, series, value
FROM (
    SELECT o.title AS label, 'Pre-test' AS series, ROUND(AVG(w.pre_test_score), 1) AS value
    FROM training_organizers o
    INNER JOIN training_events e ON e.training_organizer_id = o.id
    INNER JOIN training_event_participants p ON p.training_event_id = e.id
    INNER JOIN training_event_workshop_scores w ON w.training_event_participant_id = p.id
    GROUP BY o.title

    UNION ALL

    SELECT o.title AS label, 'Post-test' AS series, ROUND(AVG(p.final_score), 1) AS value
    FROM training_organizers o
    INNER JOIN training_events e ON e.training_organizer_id = o.id
    INNER JOIN training_event_participants p ON p.training_event_id = e.id
    GROUP BY o.title
) AS organizer_scores
ORDER BY label, series
SQL
            =>
            <<<'SQL'
SELECT label, series, value
FROM (
    SELECT o.title AS label, 'Pre-test' AS series, ROUND(AVG(w.pre_test_score), 1) AS value
    FROM training_organizers o
    INNER JOIN training_events e ON e.training_organizer_id = o.id
    INNER JOIN training_event_participants ep ON ep.training_event_id = e.id
    INNER JOIN participants p ON p.id = ep.participant_id
    INNER JOIN training_event_workshop_scores w ON w.training_event_participant_id = ep.id
    WHERE 1 = 1{{participants_filter:p}}{{events_filter:e}}
    GROUP BY o.title

    UNION ALL

    SELECT o.title AS label, 'Post-test' AS series, ROUND(AVG(ep.final_score), 1) AS value
    FROM training_organizers o
    INNER JOIN training_events e ON e.training_organizer_id = o.id
    INNER JOIN training_event_participants ep ON ep.training_event_id = e.id
    INNER JOIN participants p ON p.id = ep.participant_id
    WHERE 1 = 1{{participants_filter:p}}{{events_filter:e}}
    GROUP BY o.title
) AS organizer_scores
ORDER BY label, series
SQL,
            <<<'SQL'
SELECT label, series, value
FROM (
    SELECT r.name AS label, 'Pre-test' AS series, ROUND(AVG(w.pre_test_score), 1) AS value
    FROM regions r
    INNER JOIN participants s ON s.region_id = r.id
    INNER JOIN training_event_participants p ON p.participant_id = s.id
    INNER JOIN training_event_workshop_scores w ON w.training_event_participant_id = p.id
    GROUP BY r.name

    UNION ALL

    SELECT r.name AS label, 'Post-test' AS series, ROUND(AVG(p.final_score), 1) AS value
    FROM regions r
    INNER JOIN participants s ON s.region_id = r.id
    INNER JOIN training_event_participants p ON p.participant_id = s.id
    GROUP BY r.name
) AS region_scores
ORDER BY label, series
SQL
            =>
            <<<'SQL'
SELECT label, series, value
FROM (
    SELECT r.name AS label, 'Pre-test' AS series, ROUND(AVG(w.pre_test_score), 1) AS value
    FROM regions r
    INNER JOIN participants p ON p.region_id = r.id
    INNER JOIN training_event_participants ep ON ep.participant_id = p.id
    INNER JOIN training_events e ON e.id = ep.training_event_id
    INNER JOIN training_event_workshop_scores w ON w.training_event_participant_id = ep.id
    WHERE 1 = 1{{participants_filter:p}}{{events_filter:e}}
    GROUP BY r.name

    UNION ALL

    SELECT r.name AS label, 'Post-test' AS series, ROUND(AVG(ep.final_score), 1) AS value
    FROM regions r
    INNER JOIN participants p ON p.region_id = r.id
    INNER JOIN training_event_participants ep ON ep.participant_id = p.id
    INNER JOIN training_events e ON e.id = ep.training_event_id
    WHERE 1 = 1{{participants_filter:p}}{{events_filter:e}}
    GROUP BY r.name
) AS region_scores
ORDER BY label, series
SQL,
            'SELECT COUNT(*) AS total_participants FROM participants'
            => 'SELECT COUNT(*) AS total_participants FROM participants p WHERE 1 = 1{{participants_filter:p}}',
            'SELECT COUNT(*) AS total_projects FROM projects'
            =>
            <<<'SQL'
SELECT COUNT(*) AS total_projects
FROM projects pr
INNER JOIN participants p ON p.id = pr.participant_id
WHERE 1 = 1{{participants_filter:p}}
SQL,
            <<<'SQL'
SELECT metric, score
FROM (
    SELECT 'Average Pre-test' AS metric, ROUND(AVG(pre_test_score), 1) AS score FROM training_event_workshop_scores
    UNION ALL
    SELECT 'Average Post-test' AS metric, ROUND(AVG(final_score), 1) AS score FROM training_event_participants
) AS score_summary
SQL
            =>
            <<<'SQL'
SELECT metric, score
FROM (
    SELECT 'Average Pre-test' AS metric, ROUND(AVG(w.pre_test_score), 1) AS score
    FROM training_event_workshop_scores w
    INNER JOIN training_event_participants ep ON ep.id = w.training_event_participant_id
    INNER JOIN participants p ON p.id = ep.participant_id
    INNER JOIN training_events e ON e.id = ep.training_event_id
    WHERE 1 = 1{{participants_filter:p}}{{events_filter:e}}
    UNION ALL
    SELECT 'Average Post-test' AS metric, ROUND(AVG(ep.final_score), 1) AS score
    FROM training_event_participants ep
    INNER JOIN participants p ON p.id = ep.participant_id
    INNER JOIN training_events e ON e.id = ep.training_event_id
    WHERE 1 = 1{{participants_filter:p}}{{events_filter:e}}
) AS score_summary
SQL,
            <<<'SQL'
SELECT t.title AS label, ROUND(AVG(p.final_score), 1) AS value
FROM trainings t
INNER JOIN training_events e ON e.training_id = t.id
INNER JOIN training_event_participants p ON p.training_event_id = e.id
GROUP BY t.title
ORDER BY value DESC
LIMIT 10
SQL
            =>
            <<<'SQL'
SELECT t.title AS label, ROUND(AVG(ep.final_score), 1) AS value
FROM trainings t
INNER JOIN training_events e ON e.training_id = t.id
INNER JOIN training_event_participants ep ON ep.training_event_id = e.id
INNER JOIN participants p ON p.id = ep.participant_id
WHERE 1 = 1{{participants_filter:p}}{{events_filter:e}}
GROUP BY t.title
ORDER BY value DESC
LIMIT 10
SQL,
        ];

        foreach ($replacements as $oldSql => $newSql) {
            DB::table('dashboard_widgets')
                ->where('sql_query', $oldSql)
                ->update(['sql_query' => $newSql]);
        }
    }

    public function down(): void
    {
        // no-op
    }
};

