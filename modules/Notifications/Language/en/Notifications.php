<?php

return [
    'notifications'    => 'Notifications',
    'title'            => 'Notification Center',
    'unread'           => 'unread',
    'markAllRead'      => 'Mark all as read',
    'markRead'         => 'Mark as read',
    'noNotifications'  => 'No notifications yet.',
    'viewAll'          => 'View all',
    'severityInfo'     => 'Info',
    'severityWarning'  => 'Warning',
    'severityCritical' => 'Critical',

    // SSE stream() 429 body — kept short and unspecific.
    'realtimeConnLimit' => 'Too many open realtime connections.',

    // Preferences (opt-out) screen.
    'preferences'      => 'Notification preferences',
    'prefIntro'        => 'Mute the notification types you do not want to receive. Muting only affects your own account.',
    'prefType'         => 'Notification type',
    'prefChannelAll'   => 'All channels',
    'prefMuted'        => 'Muted',
    'prefSave'         => 'Save preferences',
    'prefSaved'        => 'Notification preferences updated.',
    'prefCriticalNote' => 'Critical notifications are always delivered and cannot be muted.',
    'prefNoTypes'      => 'No mutable notification types are registered yet.',
    'prefTableMissing' => 'The notification preferences table is not migrated yet.',
    'prefTypeAudit'    => 'Security and audit warnings',

    // Composer (send notification) screen.
    'compose'                 => 'Send notification',
    'composeIntro'            => 'Compose a notification and choose who receives it. The sender is always recorded as your own account.',
    'composeFieldTitle'       => 'Title',
    'composeFieldBody'        => 'Message',
    'composeFieldUrl'         => 'Link',
    'composeFieldSeverity'    => 'Severity',
    'composeFieldMode'        => 'Audience',
    'composeFieldUsers'       => 'Users',
    'composeFieldGroups'      => 'Groups',
    'composeFieldExclude'     => 'Excluded users',
    'composeUrlHelp'          => 'Optional. Either a site-relative path starting with / or a full http(s) address.',
    'composeExcludeHelp'      => 'These users never receive the notification, even when they match the audience.',
    'composeModeBroadcast'    => 'Everyone',
    'composeModeTargeted'     => 'Selected users and groups',
    'composeSelectUsers'      => 'Search for users…',
    'composeSelectGroups'     => 'Select groups…',
    'composeNoGroups'         => 'No groups are defined yet.',
    'composePreview'          => 'Preview recipients',
    'composeRecipients'       => 'Estimated recipients: {0}',
    'composeRecipientsFailed' => 'The recipient preview could not be loaded.',
    'composeSubmit'           => 'Send notification',
    'composeSent'             => 'Notification sent to {0} recipient(s).',
    'composeFailed'           => 'The notification could not be delivered.',
    'composePartial'          => 'Only {0} of {1} notification rows could be stored, so part of the audience was not reached. Check the log before sending again.',
    'composeNoTarget'         => 'Select at least one user or group, or send it to everyone.',
    'composeUnknownGroup'     => 'An unknown group was selected.',
    'composeUnknownUser'      => 'An unknown user was selected.',
    'composeTooManyTargets'   => 'Select at most {0} users and groups in a single notification.',
    // Literal braces are ICU-quoted ('{' / '}'): the validator formats this line through
    // MessageFormatter, and an unquoted brace makes the whole pattern fail to parse, so
    // the administrator gets the raw {field} placeholder instead of the field name.
    'composeInvalidText'      => 'The {field} field must not contain the characters < > \'{\' \'}\' =.',
    'composeInvalidUrl'       => 'The link must be a site path starting with / — an external address cannot be sent from here.',
    'composeTitleRequired'    => 'The title is required.',
    'composeInvalidSeverity'  => 'Select a valid severity.',
    'composeInvalidMode'      => 'Select a valid audience.',
];
