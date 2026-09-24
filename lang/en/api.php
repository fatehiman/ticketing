<?php

return [
    'title' => 'Bot login',
    'choose_intro' => 'A bot wants to use the ticketing system with your account. Choose the project for this bot. The bot will see and create tickets only in this project.',
    'bot_name' => 'Bot',
    'no_fixed_project' => 'No fixed project (the bot must name the project in every request)',
    'confirm' => 'Confirm login',
    'approved' => 'The bot login was successful. Project: :project. Now tell the bot that you have logged in. The access is valid for :days days.',
    'only_staff' => 'Only developers can log in a bot.',
    'no_projects' => 'You have no active project, so a bot cannot be linked.',
    'link_invalid' => 'This login link is not valid.',
    'link_expired' => 'This login link has expired. Ask the bot for a new link.',
    'link_used' => 'This login link was already used. Ask the bot for a new link if needed.',

    'tokens' => 'Bot access (API)',
    'tokens_empty' => 'No bot is using your account.',
    'tokens_note' => 'Bots that you logged in. Each access is valid for :days days. Revoke it to stop a bot at once.',
    'token_project' => 'Project',
    'token_last_used' => 'Last used',
    'token_expires' => 'Expires',
    'revoke' => 'Revoke',
    'revoked' => 'The bot access was revoked.',
];
