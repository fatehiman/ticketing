<?php

return [
    'role' => [
        'admin' => 'Admin',
        'developer' => 'Developer',
        'customer' => 'Customer',
    ],
    'status' => [
        'pending_review' => 'Pending review',
        'backlog' => 'Backlog',
        'in_progress' => 'In progress',
        'testing' => 'Testing',
        'done' => 'Done',
        'cancelled' => 'Cancelled',
        'rejected' => 'Rejected',
    ],
    'priority' => [
        'highest' => 'Highest',
        'high' => 'High',
        'medium' => 'Medium',
        'low' => 'Low',
        'lowest' => 'Lowest',
    ],
    'type' => [
        'task' => 'Task',
        'bug' => 'Bug',
        'feature' => 'New feature',
        'improvement' => 'Improvement',
        'support' => 'Support',
        'question' => 'Question',
    ],
    'story_point' => [
        1 => 'Tiny (under an hour)',
        2 => 'Very small (a few hours)',
        3 => 'Small (about a day)',
        5 => 'Medium (a few days)',
        8 => 'Large (about a week)',
        13 => 'Very large (about two weeks)',
        21 => 'Huge (should be split)',
    ],
    'project_status' => [
        'active' => 'Active',
        'on_hold' => 'On hold',
        'completed' => 'Completed',
        'archived' => 'Archived',
    ],
    'sprint_status' => [
        'planned' => 'Planned',
        'active' => 'Active',
        'closed' => 'Closed',
    ],
];
